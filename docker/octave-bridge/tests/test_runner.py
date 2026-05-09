"""Runner integration tests — require a real Octave installation.

These tests run inside the built bridge image in CI (which has octave
installed).  Run locally via:

    docker run --rm -v $(pwd)/docker/octave-bridge:/work -w /work -u root \\
        webte2-octave-bridge sh -c 'uv sync && uv run pytest tests/test_runner.py'
"""

from __future__ import annotations

import os
import time
import uuid
from pathlib import Path

import pytest

from src.errors import CommandRejected, OctaveTimeout
from src.runner import _validate_session_id, clear_session, prune_sessions, run_command


def _sid() -> str:
    """Generate a fresh 16-char hex session id for each test."""
    return uuid.uuid4().hex[:16]


# ---------------------------------------------------------------------------
# Fixture: redirect SESSION_DIR to a temp directory and silence slowdown
# ---------------------------------------------------------------------------


@pytest.fixture(autouse=True)
def patch_session_dir(tmp_path: Path, monkeypatch: pytest.MonkeyPatch) -> None:
    """Point runner.SESSION_DIR at a throwaway temp directory for every test."""
    import src.runner as runner_mod

    monkeypatch.setattr(runner_mod, "SESSION_DIR", tmp_path)
    # Zero the slowdown so tests finish quickly (overridden in the slowdown test).
    monkeypatch.setattr(runner_mod, "SLOWDOWN_MS", 0)


# ---------------------------------------------------------------------------
# Basic arithmetic
# ---------------------------------------------------------------------------


async def test_run_simple_arithmetic() -> None:
    sid = _sid()
    result = await run_command(sid, "disp(1+1)", 10.0)
    assert result.exit_code == 0
    assert "2" in result.stdout
    assert result.stderr == "" or result.stderr.strip() == ""


# ---------------------------------------------------------------------------
# Workspace persistence — the load-bearing assertion of the whole bridge
# ---------------------------------------------------------------------------


async def test_workspace_persists_across_calls() -> None:
    sid = _sid()
    # First call: assign silently (semicolon suppresses output)
    r1 = await run_command(sid, "a = 1+1;", 10.0)
    assert r1.exit_code == 0

    # Second call: use the variable from the previous invocation
    r2 = await run_command(sid, "disp(a + 2)", 10.0)
    assert r2.exit_code == 0
    assert "4" in r2.stdout


# ---------------------------------------------------------------------------
# clear_session
# ---------------------------------------------------------------------------


async def test_clear_session_drops_workspace() -> None:
    sid = _sid()
    # Establish a workspace
    await run_command(sid, "a = 42;", 10.0)

    removed = await clear_session(sid)
    assert removed is True

    # After clearing, 'a' should no longer exist in a fresh invocation
    r = await run_command(sid, "disp(exist('a', 'var'))", 10.0)
    assert r.exit_code == 0
    assert "0" in r.stdout


async def test_clear_session_returns_false_when_no_session() -> None:
    sid = _sid()
    result = await clear_session(sid)
    assert result is False


# ---------------------------------------------------------------------------
# Timeout
# ---------------------------------------------------------------------------


async def test_timeout_kills_long_running_command() -> None:
    sid = _sid()
    wall_start = time.monotonic()

    with pytest.raises(OctaveTimeout):
        await run_command(sid, "pause(30)", 0.5)

    wall_elapsed = time.monotonic() - wall_start
    # Should return well within 2 s (timeout=0.5 + process cleanup margin)
    assert wall_elapsed < 2.0


# ---------------------------------------------------------------------------
# Octave parse / runtime error — must NOT raise, must return nonzero exit_code
# ---------------------------------------------------------------------------


async def test_octave_parse_error_returns_nonzero_exit() -> None:
    sid = _sid()
    result = await run_command(sid, "this is not valid octave syntax %%%", 10.0)
    assert result.exit_code != 0
    assert result.stderr != ""


# ---------------------------------------------------------------------------
# Slowdown coefficient is applied
# ---------------------------------------------------------------------------


async def test_slowdown_ms_actually_delays(monkeypatch: pytest.MonkeyPatch) -> None:
    import src.runner as runner_mod

    monkeypatch.setattr(runner_mod, "SLOWDOWN_MS", 300)

    sid = _sid()
    wall_start = time.monotonic()
    await run_command(sid, "1+1;", 10.0)
    elapsed = time.monotonic() - wall_start

    # 300 ms slowdown; allow generous margin for slow CI machines
    assert elapsed >= 0.25


# ---------------------------------------------------------------------------
# Path-traversal session_id is rejected
# ---------------------------------------------------------------------------


def test_path_outside_session_dir_rejected() -> None:
    with pytest.raises(CommandRejected):
        _validate_session_id("../../etc/passwd")


async def test_run_command_rejects_traversal_session_id() -> None:
    with pytest.raises(CommandRejected):
        await run_command("../../etc/passwd", "1+1", 5.0)


# ---------------------------------------------------------------------------
# prune_sessions
# ---------------------------------------------------------------------------


def test_prune_sessions_removes_old_files(
    tmp_path: Path,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    import src.runner as runner_mod

    monkeypatch.setattr(runner_mod, "SESSION_DIR", tmp_path)

    # Create a file and backdate its mtime to 25 hours ago
    old_mat: Path = tmp_path / "aabbccdd11223344.mat"
    old_mat.write_bytes(b"dummy")
    old_mtime = time.time() - 25 * 3600
    os.utime(old_mat, (old_mtime, old_mtime))

    # Create a recent file that should NOT be pruned
    new_mat: Path = tmp_path / "11223344aabbccdd.mat"
    new_mat.write_bytes(b"dummy")

    count = prune_sessions(24)
    assert count == 1
    assert not old_mat.exists()
    assert new_mat.exists()
