FROM python:3.13-slim AS base

RUN apt-get update \
 && apt-get install -y --no-install-recommends octave curl \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# uv: fast python resolver. Pinned via the lockfile generated alongside this image.
RUN pip install --no-cache-dir uv

COPY pyproject.toml ./
# uv.lock is generated on first `uv sync`; in dev it's mounted via the volume bind.
RUN uv sync --no-dev || true

COPY src /app/src

RUN mkdir -p /var/octave/sessions \
 && chown -R nobody:nogroup /var/octave /app

USER nobody
EXPOSE 8001

HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
  CMD curl -fsS http://localhost:8001/health || exit 1

CMD ["uv", "run", "python", "-m", "src.main"]
