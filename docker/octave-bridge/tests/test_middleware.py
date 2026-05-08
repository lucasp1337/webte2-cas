from __future__ import annotations

import logging
from collections.abc import Awaitable, Callable

import pytest
from aiohttp import web
from aiohttp.test_utils import TestClient, TestServer

from src.errors import CommandRejected, OctaveTimeout
from src.middleware import error_middleware, request_id_middleware

_Handler = Callable[[web.Request], Awaitable[web.Response]]


def _build_app(handler: _Handler) -> web.Application:
    app = web.Application(middlewares=[error_middleware, request_id_middleware])
    app.router.add_get("/test", handler)
    return app


async def test_request_id_in_response_header_and_body() -> None:
    async def handler(_request: web.Request) -> web.Response:
        return web.json_response({"x": 1})

    async with TestClient(TestServer(_build_app(handler))) as client:
        resp = await client.get("/test")
        assert resp.status == 200
        header_id = resp.headers.get("X-Request-Id")
        assert header_id is not None
        assert len(header_id) == 32  # secrets.token_hex(16) → 32 hex chars
        body = await resp.json()
        assert body["request_id"] == header_id
        assert body["x"] == 1


async def test_error_middleware_maps_command_rejected() -> None:
    async def handler(_request: web.Request) -> web.Response:
        raise CommandRejected("eval banned")

    async with TestClient(TestServer(_build_app(handler))) as client:
        resp = await client.get("/test")
        assert resp.status == 422
        body = await resp.json()
        assert body["error"] == "command_rejected"
        assert body["reason"] == "eval banned"
        assert "request_id" in body


async def test_error_middleware_maps_timeout() -> None:
    async def handler(_request: web.Request) -> web.Response:
        raise OctaveTimeout("exceeded 10s")

    async with TestClient(TestServer(_build_app(handler))) as client:
        resp = await client.get("/test")
        assert resp.status == 408
        body = await resp.json()
        assert body["error"] == "octave_timeout"
        assert "request_id" in body


async def test_error_middleware_maps_internal_error(
    caplog: pytest.LogCaptureFixture,
) -> None:
    # This sentinel value stands in for a hypothetical user command that must
    # never appear in the error response or in any log output.
    sensitive_sentinel = "SENSITIVE_USER_COMMAND_MUST_NOT_LEAK"

    async def handler(_request: web.Request) -> web.Response:
        # The RuntimeError message is internal; it must not surface to the caller.
        raise RuntimeError(f"boom (unrelated to {sensitive_sentinel})")

    async with TestClient(TestServer(_build_app(handler))) as client:
        with caplog.at_level(logging.ERROR, logger="src.middleware"):
            resp = await client.get("/test")

        assert resp.status == 500
        body = await resp.json()
        assert body["error"] == "internal_error"
        assert "request_id" in body

        # The response body must not include the exception detail.
        body_text = str(body)
        assert "boom" not in body_text

        # The sentinel must not appear in response (proves no passthrough of
        # internal detail that could contain user-supplied data).
        assert sensitive_sentinel not in body_text
