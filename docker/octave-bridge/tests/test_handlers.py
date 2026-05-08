"""HTTP handler tests — runner is monkey-patched; Octave is never invoked here.

All tests use aiohttp's TestClient + TestServer against build_app() so that
middlewares (request_id, error) are exercised exactly as they would be in
production.
"""

from __future__ import annotations

import os

import pytest
from aiohttp.test_utils import TestClient, TestServer

from src import runner as runner_module
from src.dto import ExecResult
from src.errors import OctaveTimeout
from src.main import build_app

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

_VALID_SESSION_ID = "abcdefgh"  # 8 chars — satisfies SESSION_ID_PATTERN
_VALID_COMMAND = "1 + 1"

_STUB_RESULT = ExecResult(
    request_id="",
    stdout="2\n",
    stderr="",
    exit_code=0,
    duration_ms=42,
)


# ---------------------------------------------------------------------------
# POST /exec — happy path
# ---------------------------------------------------------------------------


async def test_exec_happy_path(monkeypatch: pytest.MonkeyPatch) -> None:
    call_args: list[tuple[str, str, int]] = []

    async def fake_run_command(
        session_id: str, command: str, timeout_seconds: float
    ) -> ExecResult:
        call_args.append((session_id, command, int(timeout_seconds)))
        return _STUB_RESULT

    monkeypatch.setattr(runner_module, "run_command", fake_run_command)

    async with TestClient(TestServer(build_app())) as client:
        resp = await client.post(
            "/exec",
            json={"session_id": _VALID_SESSION_ID, "command": _VALID_COMMAND},
        )

    assert resp.status == 200
    body = await resp.json()
    assert body["stdout"] == "2\n"
    assert body["request_id"] != ""
    assert len(body["request_id"]) == 32  # token_hex(16)
    assert call_args == [(_VALID_SESSION_ID, _VALID_COMMAND, 10)]


# ---------------------------------------------------------------------------
# POST /exec — sanitiser rejects forbidden command
# ---------------------------------------------------------------------------


async def test_exec_returns_422_when_sanitiser_rejects() -> None:
    # Do NOT patch run_command — the sanitiser should reject before it is reached.
    async with TestClient(TestServer(build_app())) as client:
        resp = await client.post(
            "/exec",
            json={"session_id": _VALID_SESSION_ID, "command": "system('ls')"},
        )

    assert resp.status == 422
    body = await resp.json()
    assert body["error"] == "command_rejected"
    assert "system" in body["reason"]
    assert "request_id" in body


# ---------------------------------------------------------------------------
# POST /exec — invalid session_id rejected by runner (no Octave spawned)
# ---------------------------------------------------------------------------


async def test_exec_returns_422_when_session_id_invalid() -> None:
    # session_id "../foo" fails regex validation inside run_command before any
    # subprocess is launched.  The sanitiser passes "1+1" fine, so this test
    # exercises the runner's path-traversal guard directly.
    async with TestClient(TestServer(build_app())) as client:
        resp = await client.post(
            "/exec",
            json={"session_id": "../foo", "command": "1+1"},
        )

    assert resp.status == 422
    body = await resp.json()
    assert body["error"] == "command_rejected"
    assert "request_id" in body


# ---------------------------------------------------------------------------
# POST /exec — timeout
# ---------------------------------------------------------------------------


async def test_exec_returns_408_on_timeout(monkeypatch: pytest.MonkeyPatch) -> None:
    async def fake_run_command(
        session_id: str, command: str, timeout_seconds: float
    ) -> ExecResult:
        raise OctaveTimeout(f"Octave exceeded {timeout_seconds}s")

    monkeypatch.setattr(runner_module, "run_command", fake_run_command)

    async with TestClient(TestServer(build_app())) as client:
        resp = await client.post(
            "/exec",
            json={"session_id": _VALID_SESSION_ID, "command": _VALID_COMMAND},
        )

    assert resp.status == 408
    body = await resp.json()
    assert body["error"] == "octave_timeout"
    assert "request_id" in body


# ---------------------------------------------------------------------------
# POST /exec — malformed request body
# ---------------------------------------------------------------------------


async def test_exec_returns_400_on_malformed_json() -> None:
    async with TestClient(TestServer(build_app())) as client:
        resp = await client.post(
            "/exec",
            data=b"not json",
            headers={"Content-Type": "application/json"},
        )

    assert resp.status == 400
    body = await resp.json()
    assert body["error"] == "malformed_request"


async def test_exec_returns_400_when_required_field_missing() -> None:
    async with TestClient(TestServer(build_app())) as client:
        resp = await client.post("/exec", json={})

    assert resp.status == 400
    body = await resp.json()
    assert body["error"] == "malformed_request"


# ---------------------------------------------------------------------------
# POST /exec — timeout clamping
# ---------------------------------------------------------------------------


async def test_exec_clamps_timeout_above_30s(monkeypatch: pytest.MonkeyPatch) -> None:
    recorded_timeouts: list[float] = []

    async def fake_run_command(
        session_id: str, command: str, timeout_seconds: float
    ) -> ExecResult:
        recorded_timeouts.append(timeout_seconds)
        return _STUB_RESULT

    monkeypatch.setattr(runner_module, "run_command", fake_run_command)

    async with TestClient(TestServer(build_app())) as client:
        resp = await client.post(
            "/exec",
            json={
                "session_id": _VALID_SESSION_ID,
                "command": _VALID_COMMAND,
                "timeout_seconds": 60,
            },
        )

    assert resp.status == 200
    assert recorded_timeouts == [30]


# ---------------------------------------------------------------------------
# DELETE /sessions/{session_id}
# ---------------------------------------------------------------------------


async def test_delete_session_happy_path(monkeypatch: pytest.MonkeyPatch) -> None:
    async def fake_clear_session(session_id: str) -> bool:
        return True

    monkeypatch.setattr(runner_module, "clear_session", fake_clear_session)

    async with TestClient(TestServer(build_app())) as client:
        resp = await client.delete(f"/sessions/{_VALID_SESSION_ID}")

    assert resp.status == 200
    body = await resp.json()
    assert body["cleared"] is True
    assert "request_id" in body


async def test_delete_session_invalid_id_returns_422() -> None:
    # "short" is only 5 chars — SESSION_ID_PATTERN requires 8-64 chars.
    # clear_session raises CommandRejected before touching the filesystem.
    async with TestClient(TestServer(build_app())) as client:
        resp = await client.delete("/sessions/short")

    assert resp.status == 422
    body = await resp.json()
    assert body["error"] == "command_rejected"


# ---------------------------------------------------------------------------
# POST /sessions/prune
# ---------------------------------------------------------------------------


async def test_prune_happy_path(monkeypatch: pytest.MonkeyPatch) -> None:
    def fake_prune_sessions(older_than_hours: int) -> int:
        return 7

    monkeypatch.setattr(runner_module, "prune_sessions", fake_prune_sessions)

    async with TestClient(TestServer(build_app())) as client:
        resp = await client.post("/sessions/prune", json={"older_than_hours": 48})

    assert resp.status == 200
    body = await resp.json()
    assert body["removed"] == 7
    assert body["older_than_hours"] == 48
    assert "request_id" in body


async def test_prune_uses_default_hours_when_body_empty(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    recorded_hours: list[int] = []

    def fake_prune_sessions(older_than_hours: int) -> int:
        recorded_hours.append(older_than_hours)
        return 0

    monkeypatch.setattr(runner_module, "prune_sessions", fake_prune_sessions)

    expected_default = int(os.environ.get("OCTAVE_SESSION_IDLE_HOURS", "24"))

    async with TestClient(TestServer(build_app())) as client:
        # Explicitly send no body (not even Content-Type: application/json).
        resp = await client.post("/sessions/prune")

    assert resp.status == 200
    assert recorded_hours == [expected_default]


async def test_prune_negative_hours_returns_400(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    def fake_prune_sessions(older_than_hours: int) -> int:  # pragma: no cover
        return 0

    monkeypatch.setattr(runner_module, "prune_sessions", fake_prune_sessions)

    async with TestClient(TestServer(build_app())) as client:
        resp = await client.post("/sessions/prune", json={"older_than_hours": -1})

    assert resp.status == 400
    body = await resp.json()
    assert body["error"] == "malformed_request"


# ---------------------------------------------------------------------------
# Sanity: every successful endpoint includes request_id
# ---------------------------------------------------------------------------


async def test_request_id_in_every_response(monkeypatch: pytest.MonkeyPatch) -> None:
    async def fake_run_command(
        session_id: str, command: str, timeout_seconds: float
    ) -> ExecResult:
        return _STUB_RESULT

    def fake_prune_sessions(older_than_hours: int) -> int:
        return 0

    async def fake_clear_session(session_id: str) -> bool:
        return False

    monkeypatch.setattr(runner_module, "run_command", fake_run_command)
    monkeypatch.setattr(runner_module, "prune_sessions", fake_prune_sessions)
    monkeypatch.setattr(runner_module, "clear_session", fake_clear_session)

    async with TestClient(TestServer(build_app())) as client:
        exec_resp = await client.post(
            "/exec",
            json={"session_id": _VALID_SESSION_ID, "command": _VALID_COMMAND},
        )
        prune_resp = await client.post("/sessions/prune")
        delete_resp = await client.delete(f"/sessions/{_VALID_SESSION_ID}")
        health_resp = await client.get("/health")

    for resp in (exec_resp, prune_resp, delete_resp, health_resp):
        body = await resp.json()
        assert "request_id" in body, f"missing request_id in {body}"
        assert body["request_id"] != ""
