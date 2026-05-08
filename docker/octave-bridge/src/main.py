"""Octave bridge HTTP service.

Phase 01 ships only `GET /health`. Phase 02 Stage A adds the error types,
DTO, and middlewares. Stage C wires in the full handler set.
"""

from __future__ import annotations

from aiohttp import web

from .middleware import error_middleware, request_id_middleware


async def health(_request: web.Request) -> web.Response:
    return web.json_response({"status": "ok"})


def build_app() -> web.Application:
    app = web.Application(middlewares=[error_middleware, request_id_middleware])
    app.router.add_get("/health", health)
    return app


def main() -> None:
    web.run_app(build_app(), port=8001)


if __name__ == "__main__":
    main()
