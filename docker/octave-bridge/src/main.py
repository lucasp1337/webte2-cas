"""Octave bridge HTTP service.

Phase 01 ships only `GET /health`. Phase 02 Stage A adds the error types,
DTO, and middlewares. Stage C wires in the full handler set.
"""

from __future__ import annotations

from aiohttp import web

from .handlers import (
    delete_session_handler,
    exec_handler,
    health_handler,
    prune_handler,
)
from .middleware import error_middleware, request_id_middleware


def build_app() -> web.Application:
    # request_id_middleware runs first so the request ID is available to
    # error_middleware when it formats error responses.
    app = web.Application(middlewares=[request_id_middleware, error_middleware])
    app.router.add_get("/health", health_handler)
    app.router.add_post("/exec", exec_handler)
    app.router.add_delete("/sessions/{session_id}", delete_session_handler)
    app.router.add_post("/sessions/prune", prune_handler)
    return app


def main() -> None:
    web.run_app(build_app(), port=8001)


if __name__ == "__main__":
    main()
