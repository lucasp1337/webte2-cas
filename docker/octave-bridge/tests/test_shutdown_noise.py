"""Tests for the _strip_known_shutdown_noise helper and its integration in run_command.

The helper exists to suppress a cosmetic stderr line that Octave emits on every
invocation when the system octaverc loads the `control` and `signal` Forge
packages (see Dockerfile lines 17-18).  The exit code is 0 even when the line
appears; without the filter the user sees a red error block for every successful
command.

Integration test: if real Octave is available (discovered via OCTAVE_BIN /
PATH) we run an actual assignment and assert stderr is clean.  If Octave is not
available the integration test is skipped — the subprocess mock variant below
still verifies the runner plumbing.
"""

from __future__ import annotations

import uuid
from pathlib import Path
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from src.runner import _strip_known_shutdown_noise, run_command

# ---------------------------------------------------------------------------
# Unit tests for the helper
# ---------------------------------------------------------------------------

_NOISE = "error: ignoring const execution_exception& while preparing to exit"


def test_strip_only_noise_line_returns_empty() -> None:
    assert _strip_known_shutdown_noise(_NOISE + "\n") == ""
    assert _strip_known_shutdown_noise(_NOISE) == ""


def test_strip_noise_with_real_diagnostic_keeps_diagnostic() -> None:
    real = "error: 'x' undefined\n"
    combined = real + _NOISE + "\n"
    result = _strip_known_shutdown_noise(combined)
    assert _NOISE not in result
    assert "error: 'x' undefined" in result


def test_strip_noise_line_embedded_in_multiline() -> None:
    lines = "warning: something\n" + _NOISE + "\nwarning: something else\n"
    result = _strip_known_shutdown_noise(lines)
    assert _NOISE not in result
    assert "warning: something" in result
    assert "warning: something else" in result


def test_strip_no_noise_returns_input_unchanged() -> None:
    clean = "warning: implicit conversion from complex to real\n"
    assert _strip_known_shutdown_noise(clean) == clean


def test_strip_empty_string_returns_empty() -> None:
    assert _strip_known_shutdown_noise("") == ""


def test_partial_match_not_stripped() -> None:
    # A line that *contains* the noise text but has extra content must survive.
    partial = "warning: " + _NOISE + " (extra)\n"
    result = _strip_known_shutdown_noise(partial)
    assert _NOISE in result


# ---------------------------------------------------------------------------
# Mock-subprocess integration: runner applies filter to real-shaped output
# ---------------------------------------------------------------------------


def _sid() -> str:
    return uuid.uuid4().hex[:16]


@pytest.fixture()
def patch_session_dir(tmp_path: Path, monkeypatch: pytest.MonkeyPatch) -> None:
    import src.runner as runner_mod

    monkeypatch.setattr(runner_mod, "SESSION_DIR", tmp_path)
    monkeypatch.setattr(runner_mod, "SLOWDOWN_MS", 0)


@pytest.mark.usefixtures("patch_session_dir")
async def test_runner_strips_shutdown_noise_from_subprocess_stderr(
    tmp_path: Path,
) -> None:
    """Verify run_command returns empty stderr when Octave emits only the noise line."""
    noise_bytes = (_NOISE + "\n").encode()

    mock_proc = MagicMock()
    mock_proc.returncode = 0
    communicate_mock = AsyncMock(return_value=(b"", noise_bytes))
    mock_proc.communicate = communicate_mock

    with patch(
        "asyncio.create_subprocess_exec",
        new=AsyncMock(return_value=mock_proc),
    ):
        result = await run_command(_sid(), "a = 5;", 10.0)

    assert result.exit_code == 0
    assert result.stderr == ""


@pytest.mark.usefixtures("patch_session_dir")
async def test_runner_keeps_real_diagnostic_alongside_noise(
    tmp_path: Path,
) -> None:
    """Verify a legitimate stderr line is preserved even when noise is present."""
    real_diag = "warning: matrix singular to machine precision\n"
    mixed = (real_diag + _NOISE + "\n").encode()

    mock_proc = MagicMock()
    mock_proc.returncode = 0
    mock_proc.communicate = AsyncMock(return_value=(b"", mixed))

    with patch(
        "asyncio.create_subprocess_exec",
        new=AsyncMock(return_value=mock_proc),
    ):
        result = await run_command(_sid(), "inv([0 0; 0 0]);", 10.0)

    assert _NOISE not in result.stderr
    assert "warning: matrix singular" in result.stderr


# ---------------------------------------------------------------------------
# Real-Octave integration (skipped when Octave is absent)
# ---------------------------------------------------------------------------


def _octave_available() -> bool:
    import shutil

    import src.runner as runner_mod

    return bool(shutil.which(runner_mod.OCTAVE_BIN))


@pytest.mark.skipif(not _octave_available(), reason="Octave not installed")
@pytest.mark.usefixtures("patch_session_dir")
async def test_real_octave_assignment_has_clean_stderr() -> None:
    """With real Octave the known shutdown noise must not appear in stderr."""
    result = await run_command(_sid(), "a = 5;", 10.0)
    assert result.exit_code == 0
    assert result.stderr == "", f"unexpected stderr: {result.stderr!r}"
