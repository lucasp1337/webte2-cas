"""HTTP request handlers for the Octave bridge.

Each handler is responsible for parsing input, calling into the runner or
sanitiser, and returning a structured JSON response.  Domain exceptions
(CommandRejected, OctaveTimeout) are intentionally *not* caught here —
error_middleware handles them and formats the appropriate HTTP status codes.
"""

from __future__ import annotations

import dataclasses
import json
import logging
import os
from typing import Any

from aiohttp import web

from . import runner as runner_module
from .dto import ExecResult
from .sanitiser import sanitise

logger = logging.getLogger(__name__)

# Hard cap on per-request timeout overrides: callers cannot exceed this even
# if they pass a larger value.  Silently clamped, not rejected, so valid
# payloads with a slightly too-large value are not broken.
_MAX_TIMEOUT_SECONDS: int = 30

_DEFAULT_TIMEOUT_SECONDS: int = int(os.environ.get("OCTAVE_BRIDGE_TIMEOUT_SECONDS", "10"))
_DEFAULT_IDLE_HOURS: int = int(os.environ.get("OCTAVE_SESSION_IDLE_HOURS", "24"))
_MAX_COMMAND_LENGTH: int = int(os.environ.get("CAS_COMMAND_MAX_LENGTH", "4096"))


def _bad_request(reason: str) -> web.HTTPBadRequest:
    """Return a 400 HTTPException with a structured JSON body."""
    return web.HTTPBadRequest(
        text=json.dumps({"error": "malformed_request", "reason": reason}),
        content_type="application/json",
    )


async def _parse_json_body(request: web.Request, *required_fields: str) -> dict[str, Any]:
    """Parse the request body as JSON and check that all required fields are present.

    Raises web.HTTPBadRequest (400) if the body is not valid JSON or if any
    required field is absent.
    """
    try:
        body: Any = await request.json()
    except (json.JSONDecodeError, UnicodeDecodeError) as exc:
        raise _bad_request(f"invalid JSON: {exc}") from exc

    if not isinstance(body, dict):
        raise _bad_request("request body must be a JSON object")

    for field in required_fields:
        if field not in body:
            raise _bad_request(f"missing required field: '{field}'")

    return body


async def exec_handler(request: web.Request) -> web.Response:
    """POST /exec — execute an Octave command in a persistent session workspace.

    Required body fields: session_id (str), command (str).
    Optional: timeout_seconds (number, clamped to [1, 30]).

    Returns 200 with stdout/stderr/exit_code/duration_ms/request_id on success.
    Raises CommandRejected (→ 422) if the command is blocked by the sanitiser or
    if session_id is invalid.
    Raises OctaveTimeout (→ 408) if Octave does not finish within the timeout.
    """
    body = await _parse_json_body(request, "session_id", "command")

    session_id: Any = body["session_id"]
    command: Any = body["command"]

    if not isinstance(session_id, str):
        raise _bad_request("'session_id' must be a string")
    if not isinstance(command, str):
        raise _bad_request("'command' must be a string")

    raw_timeout = body.get("timeout_seconds", _DEFAULT_TIMEOUT_SECONDS)
    try:
        timeout_seconds = int(raw_timeout)
    except (TypeError, ValueError) as exc:
        raise _bad_request("'timeout_seconds' must be a number") from exc
    # Silently clamp — do not error; the call is still valid, just throttled.
    timeout_seconds = min(max(timeout_seconds, 1), _MAX_TIMEOUT_SECONDS)

    # Sanitise before touching the runner.  CommandRejected propagates to
    # error_middleware which returns 422.
    sanitise(command, max_length=_MAX_COMMAND_LENGTH)

    # Truncate command to 1024 chars in logs — commands may contain user data.
    logger.debug(
        "exec request session=%s command=%.1024s timeout=%ds",
        session_id,
        command,
        timeout_seconds,
    )

    result: ExecResult = await runner_module.run_command(session_id, command, timeout_seconds)

    # Stamp the request_id assigned by request_id_middleware onto the result.
    result = dataclasses.replace(result, request_id=request["request_id"])

    return web.json_response(result.to_dict(), status=200)


async def delete_session_handler(request: web.Request) -> web.Response:
    """DELETE /sessions/{session_id} — remove a session workspace file.

    Returns 200 with {cleared: bool, request_id}.
    Raises CommandRejected (→ 422) if session_id is invalid.
    """
    session_id = request.match_info.get("session_id")
    if not session_id:
        raise _bad_request("missing session_id in path")

    cleared: bool = await runner_module.clear_session(session_id)
    return web.json_response({"cleared": cleared}, status=200)


async def prune_handler(request: web.Request) -> web.Response:
    """POST /sessions/prune — delete workspace files older than a given age.

    Optional body field: older_than_hours (int, default from OCTAVE_SESSION_IDLE_HOURS env).

    prune_sessions() is synchronous but only iterates the local sessions
    directory — no I/O multiplexing needed and no Octave subprocess is spawned,
    so the overhead is negligible and run_in_executor is not warranted.

    Returns 200 with {removed: int, older_than_hours: int, request_id}.
    """
    older_than_hours = _DEFAULT_IDLE_HOURS

    # Body is optional for this endpoint.
    content_type = request.content_type or ""
    has_body = request.content_length not in (None, 0) or content_type.startswith(
        "application/json"
    )

    if has_body:
        body = await _parse_json_body(request)
        raw_hours = body.get("older_than_hours", _DEFAULT_IDLE_HOURS)
        try:
            older_than_hours = int(raw_hours)
        except (TypeError, ValueError) as exc:
            raise _bad_request("'older_than_hours' must be an integer") from exc

    if older_than_hours < 0:
        raise _bad_request("'older_than_hours' must be non-negative")

    removed: int = runner_module.prune_sessions(older_than_hours)
    return web.json_response(
        {"removed": removed, "older_than_hours": older_than_hours},
        status=200,
    )


async def health_handler(_request: web.Request) -> web.Response:
    """GET /health — liveness probe."""
    return web.json_response({"status": "ok"})
