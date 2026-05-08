# ARCHITECTURE.md

System shape, key decisions, and the catalog of events / jobs / observers that the codebase relies on.

---

## 1. Decisions log

| Decision | Why | Alternatives rejected |
|---|---|---|
| Octave bridge as separate Python service | Sandbox blast radius. Octave can call `system()`; not safe inside the Laravel container. | `shell_exec` in PHP — too tightly coupled, no language-level isolation. |
| Workspace persistence via `.mat` files keyed by session | Stateless bridge process. Trivial to clean up. Survives bridge restarts. | Persistent Octave subprocesses per session — leaks memory, fragile. |
| Pre-compute trajectories, animate client-side | Perfect chart/animation sync. No websockets. | SSE streaming — overkill for ≤ 5 s simulations. |
| MySQL 9 | Project choice; fine for the workload. | Postgres — equally fine. |
| Redis for cache + queue + session | Async work, Horizon dashboard, fast session reads. | DB queue — works but slower; file sessions — doesn't survive multi-container. |
| Web/cli container split | `web` is fast & request-bound, `cli` runs queue workers and the scheduler. Clean failure isolation. | Single container with supervisord — works but couples lifecycles. |
| Inertia + React | Server-driven routing without API ceremony. | Pure SPA + REST — duplicates work. |
| Konva for 2D animation | Higher-level than canvas, lighter than PixiJS. | Raw canvas, SVG — perf concerns at high frame rate. |
| Browsershot for PDF | Real browser rendering = real CSS, header/footer page numbering works. | DomPDF, mPDF — limited CSS. |
| Scramble for OpenAPI | Auto-derives from routes/Form Requests. | L5-Swagger — verbose annotations. |
| Events + queued listeners for cross-cutting concerns | Stats, metrics, audit decoupled from request path. Testable in isolation. | Direct calls — couples controllers to side effects. |

---

## 2. Container topology

```
                          ┌─────────┐
                          │  nginx  │
                          │  :80    │
                          │  :443   │
                          └────┬────┘
                               │ FPM
                               ▼
                          ┌─────────┐         ┌─────────┐
                          │   web   │◀───────▶│  redis  │
                          │ Laravel │         │ cache+  │
                          │  FPM    │         │ queue+  │
                          └────┬────┘         │ session │
                               │              └────┬────┘
        ┌──────────────────────┼───────────────────┘
        ▼                      ▼                   ▲
   ┌─────────┐            ┌─────────┐              │
   │  mysql  │            │   cli   │──────────────┘
   │   :3306 │            │ horizon │   (consumes queue)
   └─────────┘            │ +sched  │
                          └────┬────┘
                               │
                               ▼
                       ┌──────────────┐
                       │ octave-bridge│
                       │ Python       │
                       │ aiohttp      │──▶ subprocess: octave
                       │ no network   │
                       └──────┬───────┘
                              │
                              ▼
                        ┌──────────┐
                        │ workspace│
                        │ volume   │
                        │  (.mat)  │
                        └──────────┘
```

### Per-service detail

| Service | Image base | Notes |
|---|---|---|
| `nginx` | `nginx:1.27-alpine` | Static asset serving, FPM upstream, TLS termination in prod |
| `web` | custom on `php:8.5-fpm-alpine` | Laravel FPM. Synchronous request handling only |
| `cli` | same image as `web` | Runs `php artisan horizon` + `php artisan schedule:work` |
| `mysql` | `mysql:9.0` | Primary persistence |
| `redis` | `redis:7-alpine` | Cache, queue, session backend |
| `octave-bridge` | custom on `python:3.13-slim` + apt octave | **No network egress.** Read-only FS except session volume |
| `workspace volume` | named volume `octave_sessions` | `.mat` workspace files |

`web` and `cli` build from the same `Dockerfile` with different `CMD`. Source tree mounted in dev; baked into the image for prod.

---

## 3. Key request flows

### 3.1 Console: `a = 1+1; a+2`

1. Browser → POST `/api/v1/octave/exec` with `{command, console_session_id}` and `X-API-Key` header.
2. `web`: `ApiKeyMiddleware` verifies the key (constant-time compare against the hashed value).
3. `web`: `LogRequestMiddleware` opens a `RequestLog` row (ULID via observer).
4. `web`: fires `ApiKeyUsed` event → queued listener updates `last_used_at` on `cli`.
5. `web`: `ExecuteOctaveCommandController` → `ExecuteOctaveCommand` action → `OctaveBridgeClient::execute()`.
6. Bridge: looks up `.mat`, sanitises, runs Octave with timeout, returns `{stdout, stderr, exit_code, duration_ms}`.
7. `web`: action wraps result in DTO; controller returns `OctaveExecutionResource`.
8. `web`: fires `OctaveCommandExecuted` event → queued listener writes metrics on `cli`.
9. `web`: `LogRequestMiddleware` finalises the `RequestLog` row (status, duration, command).

### 3.2 Pendulum simulation

1. Browser → POST `/api/v1/simulations/pendulum` with parameter DTO.
2. `web`: auth + log middleware as above.
3. `web`: `RunPendulumSimulationController` → `RunPendulumSimulation` action.
4. `web`: action dispatches `SimulationStarted` event → queued listener handles stats on `cli` (cooldown, geo lookup, insert).
5. `web`: action builds the Octave script from the Blade template, runs it via the bridge with timeout 15s.
6. `web`: `TrajectoryParser` extracts `t`, `y`, `x` matrices into `SimulationTrajectory` DTO.
7. Frontend gets the full trajectory, plays it via `requestAnimationFrame`, syncs the chart cursor to the frame index.

### 3.3 PDF download

1. Browser → POST `/api/v1/api-docs/pdf` returns `{job_id, status: 'queued'}` immediately.
2. `cli`: `GenerateApiDocsPdfJob` runs Browsershot, stores result in `storage/app/exports/{job_id}.pdf`.
3. Browser polls `/api/v1/api-docs/pdf/{job_id}` until `status: 'ready'`, then downloads.

For a small project, this could be sync — but the queued path showcases the pattern and keeps the request fast even if Chromium hiccups.

### 3.4 Large CSV export

Same shape as PDF: `web` enqueues `GenerateLargeCsvExportJob`; `cli` streams chunks to disk; user polls then downloads. Falls back to streamed response when row count is small (< 10k).

---

## 4. Octave session model

| Kind | Lifetime | ID source | Cleanup |
|---|---|---|---|
| Console session | 24h idle, persists across requests | UUID stored in Laravel session | `PruneStaleOctaveSessionsJob` daily |
| Simulation session | Single request | `sim-{ulid}` | Deleted at end of request |

Bridge doesn't distinguish; it sees only session IDs.

---

## 5. Authentication model

Two trust boundaries:

| Boundary | Mechanism |
|---|---|
| Browser → Laravel HTML routes | Laravel session + CSRF |
| Anyone → `/api/v1/*` | API key in `X-API-Key`, hashed in DB |
| Anyone → `/horizon/*` | Gate by `HORIZON_ADMIN_TOKEN` query param |

API routes accept either the session (when called from the Inertia frontend) or the API key (when called externally). The brief tests both.

---

## 6. Events catalog

| Event | Payload | When fired | Listener(s) | Sync? |
|---|---|---|---|---|
| `OctaveCommandExecuted` | `OctaveExecutionResult` | After `ExecuteOctaveCommand::handle()` returns | `RecordOctaveMetricsListener` | queued |
| `SimulationStarted` | `AnimationName`, `string $anonToken`, `string $ip`, `string $parameterHash` | Top of `RunPendulumSimulation::handle()` and `RunBallBeamSimulation::handle()` | `RecordAnimationUsageListener` | queued |
| `ApiKeyUsed` | `ApiKey $apiKey` | `ApiKeyMiddleware::handle` after successful auth | `UpdateApiKeyLastUsedListener` | queued |

Listeners live in `app/Listeners/`. Each is `final readonly` and `implements ShouldQueue`. Each has a feature test.

---

## 7. Jobs catalog

| Job | Trigger | Frequency | Notes |
|---|---|---|---|
| `PruneStaleOctaveSessionsJob` | Scheduler | Daily 02:00 | Calls bridge endpoint to delete `.mat > 24h` |
| `PruneOldRequestLogsJob` | Scheduler | Weekly Sunday 03:00 | `RequestLog::where('created_at', '<', now()->subDays(90))->chunkById(1000)->delete()` |
| `RegenerateApiDocsCacheJob` | Scheduler + post-deploy | Daily 04:00 | Calls Scramble's generator; caches to Redis |
| `GenerateApiDocsPdfJob` | User action | On demand | Browsershot; stores PDF in `storage/app/exports/` |
| `GenerateLargeCsvExportJob` | User action when log count > 10k | On demand | Streams CSV to disk in chunks |
| `RefreshGeolocationDatabaseJob` | Scheduler | Monthly day 1, 05:00 | Optional — re-downloads GeoLite2 if `GEOLITE_LICENSE_KEY` set |

Every job:
- `final class`
- `implements ShouldQueue, ShouldBeUnique` (the unique key prevents double-runs from the scheduler clock skew)
- explicit `$tries`, `$timeout`, `backoff()`
- `failed(\Throwable $e)` method that logs with structured context

---

## 8. Observers catalog

| Observer | Model | Hooks | What it does |
|---|---|---|---|
| `RequestLogObserver` | `RequestLog` | `creating` | Assigns `request_id` (ULID) and `created_at` if not set |
| `ApiKeyObserver` | `ApiKey` | `creating`, `updating` | Hashes plaintext key on create; rejects updates that touch `key_hash` |
| `AnimationUsageObserver` | `AnimationUsage` | `creating` | Backfills city/country via `GeolocationService` if absent |

Registered in `AppServiceProvider::boot()`.

---

## 9. Animation renderer interface

All animation components conform to:

```typescript
export type AnimationRenderer<TFrame> = ComponentType<{
  trajectory: TFrame[] | null;
  frameIndex: number;
  width: number;
  height: number;
}>;

export type PendulumFrame = { cartPosition: number; angle: number };
export type BallBeamFrame = { ballPosition: number; beamAngle: number };
```

Phase 06/07 ship `Pendulum2D` and `BallBeam2D` (Konva). Phase 10 may add `Pendulum3D` / `BallBeam3D` (Three.js) behind a UI toggle without parents knowing.

---

## 10. Bilingual strategy

- URL strategy: language prefix `/sk/...` and `/en/...`
- Default: SK
- `SetLocale` middleware reads the route param, calls `App::setLocale()`, persists in cookie
- UI strings: `resources/js/i18n/{sk,en}.ts` with `Translation` type enforcing parity
- Backend strings: standard `lang/{sk,en}/*.php`
- Language switcher swaps the prefix on the *current* path, preserving query and hash

---

## 11. PDF generation

`/api-docs/pdf` enqueues a job. The job:

1. Reads cached OpenAPI spec from Redis (regenerated daily by `RegenerateApiDocsCacheJob`)
2. Renders `resources/views/pdf/api-docs.blade.php` (print-styled, **not** Swagger UI)
3. Pipes through Browsershot with header/footer template:

```html
<div style="font-size:10px; width:100%; padding:0 1.5cm;">
  <span>WEBTE2 — REST API Documentation</span>
  <span style="float:right">
    <span class="pageNumber"></span> / <span class="totalPages"></span>
  </span>
</div>
```

That covers the brief's "5/8" requirement.

---

## 12. Statistics — how the cooldown actually works

```
Event: SimulationStarted(animation, anonToken, ip)
  ↓
RecordAnimationUsageListener::handle (queued, runs on cli):
  ↓
  cooldown = config('cas.stats_cooldown_minutes')   // default 10
  recent = AnimationUsage::query()
              ->where('anon_token', anonToken)
              ->where('animation', animation->value)
              ->where('started_at', '>=', now()->subMinutes(cooldown))
              ->exists()
  if recent: return            // skip, within cooldown
  ↓
  geo = GeolocationService::lookup(ip)
  AnimationUsage::create([anon_token, animation, started_at: now(), city, country, country_iso])
```

`anon_token` is set by `EnsureAnonTokenMiddleware` on `/api/v1/simulations/*` — generates UUID if cookie missing, sets HttpOnly + SameSite=Lax + 1-year expiry on the response.

---

## 13. Scope cuts if behind

In priority order — drop these first:

1. **3D animations** — never planned for v1.
2. **Horizon dashboard polish** — keep the workers running, drop the auth UI.
3. **History dropdown in console** — replace with browser back-button.
4. **Stats drilldown UI polish** — keep raw table view.
5. **Dark mode** — light-only.

Do **not** drop:
- Anything in the rubric
- Either animation in 2D
- PDF dynamic generation
- Logging + CSV export
- Statistics + cooldown
- Bilingual
- Docker
- Queue infrastructure (it's wired throughout — pulling it out is more work than keeping it)
