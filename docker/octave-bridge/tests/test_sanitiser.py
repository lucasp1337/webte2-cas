from __future__ import annotations

import pytest

from src.errors import CommandRejected
from src.sanitiser import FORBIDDEN_PATTERNS, sanitise

_MAX = 4096

# ---------------------------------------------------------------------------
# Forbidden-pattern coverage — one triggering input per entry in the blocklist
# ---------------------------------------------------------------------------

# Each tuple: (input_str, expected_reason_substring)
_FORBIDDEN_CASES: list[tuple[str, str]] = [
    ("system('ls')", "system"),
    ("unix('ls')", "unix"),
    ("dos('dir')", "dos"),
    ("popen('cat /etc/passwd', 'r')", "popen"),
    ("eval('a = 1')", "eval"),
    ("exec('rm -rf /')", "exec"),
    ("source('/etc/profile')", "source"),
    ("mkfifo('/tmp/pipe')", "mkfifo"),
    ("fopen('/etc/passwd', 'r')", "fopen"),
    ("fclose(fid)", "fclose"),
    ("fwrite(fid, data)", "fwrite"),
    ("load('workspace.mat')", "load"),
    ("save('out.mat')", "save"),
    ("__octave_config_info__('octave_home')", "__octave_config_info__"),
    ("addpath('/usr/share/octave')", "addpath"),
    ("rmpath('/usr/share/octave')", "rmpath"),
    ("!ls -la", "shell escape (!)"),
    # Case-insensitivity checks
    ("SYSTEM('id')", "system"),
    ("EVAL('1+1')", "eval"),
    ("Load('x.mat')", "load"),
    ("Save('x.mat')", "save"),
    # exit / quit — kill only the subprocess, not the .mat workspace
    ("exit", "exit/quit (use Clear Session)"),
    ("exit;", "exit/quit (use Clear Session)"),
    ("exit(0)", "exit/quit (use Clear Session)"),
    ("exit; disp(a);", "exit/quit (use Clear Session)"),
    ("quit", "exit/quit (use Clear Session)"),
    ("quit()", "exit/quit (use Clear Session)"),
]


@pytest.mark.parametrize(("input_str", "expected_reason_substring"), _FORBIDDEN_CASES)
def test_forbidden_pattern_rejected(input_str: str, expected_reason_substring: str) -> None:
    with pytest.raises(CommandRejected) as exc_info:
        sanitise(input_str, max_length=_MAX)
    assert expected_reason_substring in exc_info.value.reason


# ---------------------------------------------------------------------------
# Legitimate commands — must all pass without raising
# ---------------------------------------------------------------------------

_LEGITIMATE_CASES: list[str] = [
    "a = 1 + 1",
    "b = sqrt(2)",
    "x = linspace(0, 2*pi, 100); y = sin(x);",
    "A = [1 2; 3 4]; eig(A)",
    "polyfit(x, y, 3)",
    "disp('hello')",
    "plot(x, y, 'r-')",
    "function y = f(x); y = x.^2; endfunction",
    # 'save' appears as a substring but NOT as a word-boundary token
    "my_save_path = 1",
    # 'exec' appears as a substring only
    "execute_count = 42",
    # 'load' appears as a substring only
    "reload_flag = true",
    # 'exit' and 'quit' appear as substrings inside identifiers only
    "exit_code = 1;",
    "existing = 5;",
    "quit_flag = false;",
]


@pytest.mark.parametrize("input_str", _LEGITIMATE_CASES)
def test_legitimate_commands_allowed(input_str: str) -> None:
    # Should not raise
    sanitise(input_str, max_length=_MAX)


# ---------------------------------------------------------------------------
# Forbidden token inside a comment is allowed
# ---------------------------------------------------------------------------


def test_token_in_line_comment_is_allowed() -> None:
    sanitise("% I would never call eval here", max_length=_MAX)


def test_token_in_block_comment_is_allowed() -> None:
    sanitise("%{ system('ls') %}", max_length=_MAX)


def test_token_after_comment_delimiter_allowed() -> None:
    # 'load' appears only in a comment; the real code is clean
    sanitise("a = 1; % load is forbidden outside comments\na + 1", max_length=_MAX)


# ---------------------------------------------------------------------------
# Length overflow
# ---------------------------------------------------------------------------


def test_length_overflow_rejected() -> None:
    max_len = 128
    with pytest.raises(CommandRejected) as exc_info:
        sanitise("a" * (max_len + 1), max_length=max_len)
    assert exc_info.value.reason.startswith("length:")


def test_exact_max_length_allowed() -> None:
    max_len = 128
    # 'a' repeated exactly max_len times — clean command, should pass
    sanitise("a" * max_len, max_length=max_len)


# ---------------------------------------------------------------------------
# Empty string
# ---------------------------------------------------------------------------


def test_empty_string_rejected() -> None:
    # An empty command has no useful meaning; reject it early.
    with pytest.raises(CommandRejected) as exc_info:
        sanitise("", max_length=_MAX)
    assert exc_info.value.reason.startswith("length:")


# ---------------------------------------------------------------------------
# Sanity-check: every FORBIDDEN_PATTERNS entry is covered by the parametrised
# test above (catch future additions to the blocklist that lack test coverage).
# ---------------------------------------------------------------------------


def test_all_forbidden_pattern_names_have_a_test_case() -> None:
    """Each name in FORBIDDEN_PATTERNS must appear in _FORBIDDEN_CASES."""
    tested_names = {reason for _, reason in _FORBIDDEN_CASES}
    # Lower-case the tested names for normalised comparison
    tested_lower = {n.lower() for n in tested_names}
    for human_name, _ in FORBIDDEN_PATTERNS:
        assert human_name.lower() in tested_lower, (
            f"No test case covers FORBIDDEN_PATTERNS entry: {human_name!r}"
        )
