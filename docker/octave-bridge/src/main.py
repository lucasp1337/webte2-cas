"""Octave bridge HTTP service.

Phase 01 ships only `GET /health`. Phase 02 adds `/exec`, `/session`, sandbox
hardening, and the Octave subprocess pool.
"""

from __future__ import annotations

from aiohttp import web


async def health(_request: web.Request) -> web.Response:
    return web.json_response({"status": "ok"})


def build_app() -> web.Application:
    app = web.Application()
    app.router.add_get("/health", health)
    return app


def main() -> None:
    web.run_app(build_app(), port=8001)


if __name__ == "__main__":
    main()
