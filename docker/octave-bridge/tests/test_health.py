from __future__ import annotations

from aiohttp.test_utils import TestClient, TestServer

from src.main import build_app


async def test_health_returns_ok() -> None:
    async with TestClient(TestServer(build_app())) as client:
        resp = await client.get("/health")
        assert resp.status == 200
        body = await resp.json()
        assert body["status"] == "ok"
        assert "request_id" in body  # injected by request_id_middleware
