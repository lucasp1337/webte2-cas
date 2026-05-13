"""Command sanitiser for the Octave bridge.

Octave block comments (%{ ... %}) and line comments (% ...) are stripped
before scanning, so forbidden tokens that appear only inside comments are
allowed — Octave never executes comment text.

KNOWN FALSE-NEGATIVE: the comment stripper is not string-aware. A `%`
character inside a string literal (e.g. `disp("a % system fake")`) starts
a "comment" from the regex's perspective and the rest of the line is
ignored. A determined attacker could in principle hide a forbidden token
behind a `%` inside a string. This is acceptable because the container
sandbox is the load-bearing control: `internal: true` network blocks
egress, `cap_drop: ALL` blocks privilege escalation, `read_only: true`
blocks filesystem damage, ulimits cap CPU/memory/files. The blocklist
is defence-in-depth that produces a clear early error for legitimate
typos; it is not the security boundary.
"""

from __future__ import annotations

import re

from .errors import CommandRejected

# Each entry is (human_readable_name, compiled_pattern).
# Patterns use \b word boundaries so substrings inside identifiers are not
# flagged (e.g. 'mysavedvar' does not trigger the 'save' rule).
FORBIDDEN_PATTERNS: tuple[tuple[str, re.Pattern[str]], ...] = (
    ("system", re.compile(r"\bsystem\b", re.IGNORECASE)),
    ("unix", re.compile(r"\bunix\b", re.IGNORECASE)),
    ("dos", re.compile(r"\bdos\b", re.IGNORECASE)),
    ("popen", re.compile(r"\bpopen\b", re.IGNORECASE)),
    ("eval", re.compile(r"\beval\b", re.IGNORECASE)),
    ("exec", re.compile(r"\bexec\b", re.IGNORECASE)),
    ("source", re.compile(r"\bsource\b", re.IGNORECASE)),
    ("mkfifo", re.compile(r"\bmkfifo\b", re.IGNORECASE)),
    ("fopen", re.compile(r"\bfopen\b", re.IGNORECASE)),
    ("fclose", re.compile(r"\bfclose\b", re.IGNORECASE)),
    ("fwrite", re.compile(r"\bfwrite\b", re.IGNORECASE)),
    ("load", re.compile(r"\bload\b", re.IGNORECASE)),
    ("save", re.compile(r"\bsave\b", re.IGNORECASE)),
    ("__octave_config_info__", re.compile(r"\b__octave_config_info__\b", re.IGNORECASE)),
    ("addpath", re.compile(r"\baddpath\b", re.IGNORECASE)),
    ("rmpath", re.compile(r"\brmpath\b", re.IGNORECASE)),
    ("shell escape (!)", re.compile(r"!")),
    # exit/quit kill the subprocess but leave the .mat workspace untouched, so
    # variables "reappear" on the next request.  Users who want to wipe their
    # workspace should use the Clear Session button (DELETE /sessions/{id}).
    ("exit/quit (use Clear Session)", re.compile(r"\b(?:exit|quit)\b", re.IGNORECASE)),
    # pkg load/install can pull in forge packages that expose network or
    # filesystem operations.  Control/signal packages are auto-loaded via the
    # system octaverc; user-side `pkg` calls are unnecessary and must be blocked.
    ("pkg/package loader", re.compile(r"\bpkg\b", re.IGNORECASE)),
    # dlmwrite / csvwrite write directly to a filename argument, bypassing the
    # fopen/fwrite controls that are already blocked above.
    ("dlmwrite", re.compile(r"\bdlmwrite\b", re.IGNORECASE)),
    ("csvwrite", re.compile(r"\bcsvwrite\b", re.IGNORECASE)),
    # urlread / urlwrite make explicit HTTP/HTTPS requests.  Network egress is
    # blocked at the container level, but reject upfront for a clear user error.
    ("urlread", re.compile(r"\burlread\b", re.IGNORECASE)),
    ("urlwrite", re.compile(r"\burlwrite\b", re.IGNORECASE)),
)

# Block-comment pattern: %{ ... %} (non-greedy, dotall so it spans lines).
_BLOCK_COMMENT_RE = re.compile(r"%\{.*?%\}", re.DOTALL)
# Line-comment pattern: % to end of line (but not inside a string — best-effort).
_LINE_COMMENT_RE = re.compile(r"%[^\n]*")


def _strip_comments(command: str) -> str:
    """Remove Octave comments so forbidden tokens in comments are not flagged."""
    without_block = _BLOCK_COMMENT_RE.sub("", command)
    return _LINE_COMMENT_RE.sub("", without_block)


def sanitise(command: str, *, max_length: int) -> None:
    """Validate *command* against length and forbidden-token rules.

    Raises :class:`~errors.CommandRejected` with a descriptive reason on the
    first violation found.  Returns ``None`` on success.

    Args:
        command: The raw Octave command string submitted by the user.
        max_length: Maximum allowed byte length.  Commands exceeding this are
            rejected before pattern scanning.
    """
    if len(command) == 0:
        raise CommandRejected("length: command must not be empty")

    if len(command) > max_length:
        raise CommandRejected(f"length: {len(command)}/{max_length}")

    scannable = _strip_comments(command)

    for human_name, pattern in FORBIDDEN_PATTERNS:
        if pattern.search(scannable):
            raise CommandRejected(f"forbidden token: {human_name}")
