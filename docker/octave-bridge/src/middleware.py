"""aiohttp middlewares for the Octave bridge.

Two middlewares are provided:
- request_id_middleware: generates a per-request ID, attaches it to the
  request object and injects it into JSON response bodies + X-Request-Id header.
- error_middleware: maps BridgeError subclasses to appropriate HTTP status codes.
"""

from __future__ import annotations

import json
import logging
import secrets
from collections.abc import Awaitable, Callable

from aiohttp import web

from .errors import CommandRejected, OctaveBridgeUnavailable, OctaveTimeout

Handler = Callable[[web.Request], Awaitable[web.StreamResponse]]

logger = logging.getLogger(__name__)

REQUEST_ID_KEY = "request_id"


@web.middleware
async def request_id_middleware(request: web.Request, handler: Handler) -> web.StreamResponse:
    """Assign a unique request ID to every request.

    The ID is stored on ``request[REQUEST_ID_KEY]``, echoed in the
    ``X-Request-Id`` response header, and injected into any JSON response
    body as a top-level ``request_id`` field.
    """
    request_id = secrets.token_hex(16)
    request[REQUEST_ID_KEY] = request_id

    response = await handler(request)

    response.headers["X-Request-Id"] = request_id

    # Inject request_id into JSON response bodies where possible.
    # Only plain Response objects (not StreamResponse) carry a mutable body.
    if (
        isinstance(response, web.Response)
        and response.content_type == "application/json"
        and isinstance(response.body, (bytes, bytearray))
    ):
        try:
            body = json.loads(response.body)
            if isinstance(body, dict) and REQUEST_ID_KEY not in body:
                body[REQUEST_ID_KEY] = request_id
                response.text = json.dumps(body)
        except (json.JSONDecodeError, UnicodeDecodeError):
            pass  # leave non-parseable bodies untouched

    return response


@web.middleware
async def error_middleware(request: web.Request, handler: Handler) -> web.StreamResponse:
    """Map domain exceptions to structured HTTP error responses.

    CommandRejected → 422
    OctaveTimeout   → 408
    OctaveBridgeUnavailable → 503
    Any other Exception → 500  (logged, command NOT included per secrets policy)
    """
    try:
        return await handler(request)
    except CommandRejected as exc:
        # Read request_id after the inner middlewares have run (request_id_middleware
        # sets the key before the actual handler fires, so it is available here).
        request_id: str = request.get(REQUEST_ID_KEY, "")
        return web.json_response(
            {
                "error": "command_rejected",
                "reason": exc.reason,
                "request_id": request_id,
            },
            status=422,
        )
    except OctaveTimeout:
        request_id = request.get(REQUEST_ID_KEY, "")
        return web.json_response(
            {"error": "octave_timeout", "request_id": request_id},
            status=408,
        )
    except OctaveBridgeUnavailable:
        request_id = request.get(REQUEST_ID_KEY, "")
        return web.json_response(
            {"error": "bridge_unavailable", "request_id": request_id},
            status=503,
        )
    except Exception:
        # Log without including any user data (command, session_id) per secrets policy.
        logger.exception("unhandled error in request handler")
        request_id = request.get(REQUEST_ID_KEY, "")
        return web.json_response(
            {"error": "internal_error", "request_id": request_id},
            status=500,
        )
