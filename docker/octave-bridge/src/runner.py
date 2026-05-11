"""Octave subprocess runner with .mat workspace persistence.

Each call to run_command spawns a fresh Octave process (spawn-per-request
model). Workspace state is persisted between calls via a per-session .mat
file loaded at the start and saved at the end of every invocation.
"""

from __future__ import annotations

import asyncio
import contextlib
import logging
import os
import re
import tempfile
import time
from pathlib import Path

from .dto import ExecResult
from .errors import CommandRejected, OctaveTimeout

logger = logging.getLogger(__name__)

# Anchored pattern — rejects path-traversal attempts and shell metacharacters.
SESSION_ID_PATTERN: re.Pattern[str] = re.compile(r"^[A-Za-z0-9_-]{8,64}$")

# Exact text of the cosmetic noise line emitted by Octave's shutdown path when
# packages loaded via the system octaverc (pkg load control; pkg load signal)
# have destructors that throw during process exit.  The exit code is still 0 so
# the command succeeded; the line is pure noise.
#
# Root cause: the `control` and `signal` Forge packages register C++ objects
# whose destructors call into Octave's interpreter.  When the interpreter is
# tearing itself down those calls throw a `const execution_exception&`.  Octave
# catches it in main() and prints this message before returning 0.  Reported on
# the Octave mailing list for multiple Octave versions; no upstream fix as of
# Ubuntu 24.04 / Octave 8.x.
#
# Reference: this conversation (2026-05-11) and the bridge Dockerfile lines
# 17-18 which add the `pkg load` lines to the system octaverc — that is exactly
# why the message appears on every invocation.
_SHUTDOWN_NOISE_LINE = "error: ignoring const execution_exception& while preparing to exit"


def _strip_known_shutdown_noise(stderr: str) -> str:
    """Remove the known Octave shutdown noise line from *stderr*.

    Matches only the exact line text (with or without a trailing newline) so
    that legitimate diagnostics containing those words are never silently
    discarded.  If the result is blank after filtering, returns an empty string
    with no stray newline.

    Args:
        stderr: Raw stderr string decoded from an Octave subprocess.

    Returns:
        Filtered stderr with the cosmetic shutdown line removed.
    """
    filtered = "\n".join(line for line in stderr.splitlines() if line != _SHUTDOWN_NOISE_LINE)
    # splitlines() + join strips the trailing newline; restore it only when
    # there is still content so we do not return a bare "\n" for empty output.
    if filtered:
        return filtered + "\n"
    return ""


SESSION_DIR: Path = Path(os.environ.get("SESSION_DIR", "/var/octave/sessions"))

# Server-side throttle applied after each Octave call to slow down scripted
# abuse and make the UX feel intentional.  0 = no slowdown.
SLOWDOWN_MS: int = int(os.environ.get("CAS_SLOWDOWN_MS", "500"))

OCTAVE_BIN: str = os.environ.get("OCTAVE_BIN", "octave")


def _validate_session_id(session_id: str) -> None:
    """Raise CommandRejected if session_id fails the regex or escapes SESSION_DIR.

    Called first in every public entry point that accepts a session_id.
    Path-traversal protection is non-negotiable.
    """
    if not SESSION_ID_PATTERN.match(session_id):
        raise CommandRejected("invalid session_id")

    # Defence in depth: confirm the resolved path stays inside SESSION_DIR even
    # if the regex is ever loosened in the future.
    mat_path = (SESSION_DIR / f"{session_id}.mat").resolve()
    resolved_dir = SESSION_DIR.resolve()
    if not str(mat_path).startswith(str(resolved_dir) + os.sep):
        raise CommandRejected("invalid session_id")


def _mat_path(session_id: str) -> Path:
    """Return the .mat file path for a validated session_id."""
    return SESSION_DIR / f"{session_id}.mat"


async def run_command(
    session_id: str,
    command: str,
    timeout_seconds: float,
) -> ExecResult:
    """Execute *command* in Octave, loading and saving workspace state.

    The runner trusts its input — sanitisation is the caller's responsibility
    (handlers call sanitiser before invoking this function).

    ``request_id`` in the returned ExecResult is left as an empty string;
    the HTTP handler overwrites it with the middleware-assigned request ID.

    Args:
        session_id: Validated session identifier (regex-checked first).
        command: Raw Octave command to execute.
        timeout_seconds: Hard timeout for the Octave subprocess.

    Returns:
        ExecResult with stdout, stderr, exit_code, duration_ms; request_id="".

    Raises:
        CommandRejected: If session_id fails validation.
        OctaveTimeout: If Octave does not complete within timeout_seconds.
    """
    _validate_session_id(session_id)

    mat = _mat_path(session_id)
    mat.parent.mkdir(parents=True, exist_ok=True)

    # Build the wrapped script: load existing workspace (if present), run the
    # user command, then unconditionally save the resulting workspace.
    lines: list[str] = []
    if mat.exists():
        lines.append(f"if exist('{mat}', 'file'); load('{mat}'); endif")
    lines.append(command)
    lines.append(f"save('-binary', '{mat}');")
    script_content = "\n".join(lines) + "\n"

    # Write to a temp file so we never pass arbitrary user content through
    # --eval (shell quoting of the whole script is fragile and risky).
    tmp_file: str
    with tempfile.NamedTemporaryFile(
        mode="w",
        suffix=".m",
        dir="/tmp",
        delete=False,
        prefix="octave_wrap_",
    ) as fh:
        fh.write(script_content)
        tmp_file = fh.name

    started = time.monotonic()

    async def _spawn_and_wait() -> tuple[bytes, bytes, int]:
        proc = await asyncio.create_subprocess_exec(
            OCTAVE_BIN,
            "--no-init-file",
            "--no-gui",
            "--quiet",
            tmp_file,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        try:
            out, err = await asyncio.wait_for(
                proc.communicate(),
                timeout=timeout_seconds,
            )
        except TimeoutError as exc:
            proc.kill()
            await proc.wait()  # reap the zombie — never leave a dangling subprocess
            raise OctaveTimeout(f"Octave exceeded {timeout_seconds}s") from exc
        rc = proc.returncode if proc.returncode is not None else 0
        return out, err, rc

    try:
        stdout_bytes, stderr_bytes, exit_code = await _spawn_and_wait()
    finally:
        with contextlib.suppress(OSError):
            os.unlink(tmp_file)

    # duration_ms reflects actual Octave execution time, not the slowdown below.
    duration_ms = int((time.monotonic() - started) * 1000)

    if SLOWDOWN_MS > 0:
        # Configurable server-side throttle to discourage high-frequency scripted
        # use.  The sleep happens after duration_ms is recorded so callers see
        # real Octave time, not the artificial delay.
        await asyncio.sleep(SLOWDOWN_MS / 1000)

    raw_stderr = stderr_bytes.decode("utf-8", errors="replace")

    return ExecResult(
        request_id="",  # filled by the HTTP handler from request_id_middleware
        stdout=stdout_bytes.decode("utf-8", errors="replace"),
        stderr=_strip_known_shutdown_noise(raw_stderr),
        exit_code=exit_code,
        duration_ms=duration_ms,
    )


async def clear_session(session_id: str) -> bool:
    """Delete the workspace file for *session_id*.

    Args:
        session_id: Session whose .mat file should be removed.

    Returns:
        True if the file existed and was removed, False if no .mat was present.

    Raises:
        CommandRejected: If session_id fails validation.
    """
    _validate_session_id(session_id)
    mat = _mat_path(session_id)
    if not mat.exists():
        return False
    mat.unlink()
    return True


def prune_sessions(older_than_hours: int) -> int:
    """Delete .mat files that have not been touched for *older_than_hours* hours.

    This function is synchronous — it is called from a queued job on the PHP
    side (PruneStaleOctaveSessionsJob) via a blocking HTTP request, not from
    inside an async handler.

    Returns:
        Number of files removed.
    """
    cutoff = time.time() - older_than_hours * 3600
    removed = 0
    for mat_file in SESSION_DIR.glob("*.mat"):
        try:
            if mat_file.stat().st_mtime < cutoff:
                mat_file.unlink()
                removed += 1
        except OSError as exc:
            # File may have been concurrently removed — log but continue.
            logger.info("prune_sessions: could not remove %s: %s", mat_file, exc)
    return removed
