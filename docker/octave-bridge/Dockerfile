# Image digest pinning deferred — follow-up task to lock python:3.13-slim to a specific SHA.
FROM python:3.13-slim AS base

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      octave \
      octave-control \
      octave-signal \
      curl \
 && rm -rf /var/lib/apt/lists/*

# Auto-load Octave Forge packages on every interpreter start. The bridge's
# `--no-init-file` flag skips per-user octaverc but NOT the system-site
# octaverc — packages still load before user scripts. The sanitiser blocks
# `pkg load` in user input as defence-in-depth (`pkg` is blocklisted), so
# injecting it server-side here is the right place.
RUN echo 'pkg load control;' >> /usr/share/octave/site/m/startup/octaverc \
 && echo 'pkg load signal;'  >> /usr/share/octave/site/m/startup/octaverc

WORKDIR /app

# uv: fast python resolver. Pinned via the lockfile generated alongside this image.
RUN pip install --no-cache-dir uv

COPY pyproject.toml uv.lock ./
RUN uv sync --no-dev --frozen

COPY src /app/src

RUN mkdir -p /var/octave/sessions /tmp/uv-cache \
 && chown -R nobody:nogroup /var/octave /app /tmp/uv-cache

ENV UV_CACHE_DIR=/tmp/uv-cache
# Use the venv directly so the runtime never needs to contact PyPI.
# `uv run` would re-sync on startup, which fails on the internal-only network.
ENV PATH="/app/.venv/bin:$PATH"

USER nobody
EXPOSE 8001

HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
  CMD curl -fsS http://localhost:8001/health || exit 1

CMD ["python", "-m", "src.main"]
