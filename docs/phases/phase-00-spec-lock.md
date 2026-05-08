# Phase 00 — Spec lock

**Duration**: 0.5 d
**Tier**: any
**Required reading**: `CLAUDE.md`, `docs/ARCHITECTURE.md`

## Goal

Lock every decision before code: repo, branch protection, PR template, the OpenAPI route shape, the env-var inventory, and the project board.

## Definition of Done

- [ ] Repository created on GitHub with branch protection on `main` (PR required, CI required, linear history, no force-push)
- [ ] `docs/README.md`, `CLAUDE.md`, `docs/ARCHITECTURE.md` committed (use the files in this plan)
- [ ] All 12 phase docs committed under `docs/phases/`
- [ ] `openapi.yaml` hand-drafted at the repo root (skeleton — Scramble enriches later)
- [ ] `.env.example` committed with every config key the project will use
- [ ] GitHub Project board with one issue per phase, labelled `phase:00`–`phase:11`
- [ ] PR template (`.github/pull_request_template.md`) committed (use the template from `CLAUDE.md §12`)
- [ ] `docs/decisions/` directory with a placeholder ADR file (`.gitkeep` plus a README explaining the format)

## Prerequisites

None.

## Tasks

### 0.1 Repo setup

```bash
gh repo create webte2-cas --private --clone
cd webte2-cas
git checkout -b main
mkdir -p docs/phases docs/decisions .github/workflows
```

Branch protection on `main`: PR required, status checks required (will be wired in Phase 01), linear history, disallow force-push.

### 0.2 Drop the plan into the repo

Copy the markdowns from this plan into `docs/`. Keep the directory structure: phases under `docs/phases/`.

### 0.3 Draft `openapi.yaml`

Hand-draft the route shape so the contract is locked early. Scramble fills bodies in Phase 08.

Routes:

```
POST   /api/v1/octave/exec
DELETE /api/v1/octave/session
POST   /api/v1/simulations/pendulum
POST   /api/v1/simulations/ball-beam
GET    /api/v1/logs
GET    /api/v1/logs/export.csv
POST   /api/v1/api-docs/pdf            (returns {job_id, status})
GET    /api/v1/api-docs/pdf/{id}       (poll status, then download)
GET    /api/v1/stats
GET    /api/v1/stats/{animation}
GET    /api/v1/health
```

Each with: summary, security (`X-API-Key`), request/response shape, error responses (401, 422, 429, 500, 503).

### 0.4 `.env.example`

```ini
# App
APP_NAME=WEBTE2
APP_ENV=local
APP_KEY=
APP_URL=http://localhost
APP_LOCALE=sk
APP_FALLBACK_LOCALE=en

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=webte2
DB_USERNAME=webte2
DB_PASSWORD=changeme

# Redis (cache, queue, session)
REDIS_HOST=redis
REDIS_PORT=6379
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Horizon
HORIZON_ADMIN_TOKEN=

# CAS / Octave bridge
OCTAVE_BRIDGE_URL=http://octave-bridge:8001
OCTAVE_BRIDGE_TIMEOUT_SECONDS=10
CAS_SLOWDOWN_MS=500
CAS_API_KEY_PLAINTEXT=
CAS_COMMAND_MAX_LENGTH=4096
CAS_RATE_LIMIT_PER_MINUTE=30

# Statistics
STATS_COOLDOWN_MINUTES=10
GEOLITE_DB_PATH=/var/geolite/GeoLite2-City.mmdb
GEOLITE_LICENSE_KEY=

# Frontend
VITE_APP_URL="${APP_URL}"
```

### 0.5 PR template

Copy `CLAUDE.md §12` template into `.github/pull_request_template.md`.

### 0.6 Project board

GitHub Project with one issue per phase (12 issues, labelled `phase:00`–`phase:11`). Single board view "By phase".

### 0.7 First commit

```bash
git add .
git commit -m "chore: lock spec, plan, and repo conventions"
git push origin main
```

## Quality gates

- [ ] `npx @redocly/cli lint openapi.yaml` passes
- [ ] All 12 phase docs reachable from `docs/README.md`
- [ ] `.env.example` covers every key referenced anywhere in the plan
- [ ] PR template exists and is non-trivial

## Risks

| Risk | Mitigation |
|---|---|
| Spec drift | Update `openapi.yaml` in the same PR that changes a route |

## Hand-off to next phase

Phase 01 needs: the repo, the PR template, branch protection (so CI checks can be marked required when added).

## Agent brief (copy-paste)

> Read `CLAUDE.md` and this phase markdown end to end before starting.
>
> Set up the repo per this phase: drop the plan markdowns into `docs/`, hand-draft `openapi.yaml` with the routes listed in §0.3 (use OpenAPI 3.1, security scheme `apiKey` in header `X-API-Key`, every route documents 200/401/422/429/500/503 with example payloads), commit `.env.example` with every key from §0.4, commit the PR template from `CLAUDE.md §12`.
>
> Validate `openapi.yaml` with redocly before opening the PR.
>
> Open one PR labelled `phase:00`. The PR description follows the template in `CLAUDE.md §12`.
