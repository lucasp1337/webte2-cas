"""Session-ID validation tests — no Octave required.

Every rejection case uses pytest.mark.parametrize and asserts that
CommandRejected is raised.  Acceptance cases assert no exception is raised.
"""

from __future__ import annotations

import pytest

from src.errors import CommandRejected
from src.runner import _validate_session_id

# ---------------------------------------------------------------------------
# Rejection cases
# ---------------------------------------------------------------------------

_REJECT_CASES: list[str] = [
    # Path traversal
    "../../etc/passwd",
    "..\\..\\windows",
    "/etc/passwd",
    "foo/bar",
    "foo\\bar",
    # Length bounds — too short (< 8)
    "",
    "a",
    "abcdefg",  # 7 chars
    # Length bounds — too long (> 64)
    "a" * 65,
    # Forbidden characters
    "session with spaces",
    "session.with.dots",
    "session@domain",
    "session$var",
    "séssion12",  # non-ASCII unicode
    "session\tid",  # tab
    "session\nid",  # newline
    # Null byte
    "valid_id\x00trailing",
    # Shell metacharacters
    "session;id",
    "session|id",
    "session`id",
    "session>id",
    "session<id",
    "session&id",
    # Spaces / special
    " leadingspace",
    "trailingspace ",
    "   ",
]


@pytest.mark.parametrize("session_id", _REJECT_CASES)
def test_invalid_session_id_rejected(session_id: str) -> None:
    with pytest.raises(CommandRejected):
        _validate_session_id(session_id)


# ---------------------------------------------------------------------------
# Acceptance cases — exactly at the boundary or using all allowed chars
# ---------------------------------------------------------------------------

_ACCEPT_CASES: list[str] = [
    "a" * 8,  # minimum length
    "a" * 64,  # maximum length
    "abcdefgh",
    "ABCDEFGH",
    "12345678",
    "abc-def-",
    "abc_def_",
    "Session123",
    "UPPER_LOWER-mixed1234",
    "A1b2C3d4E5f6G7h8",
]


@pytest.mark.parametrize("session_id", _ACCEPT_CASES)
def test_valid_session_id_accepted(session_id: str) -> None:
    # Must not raise
    _validate_session_id(session_id)
