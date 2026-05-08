# CLAUDE.md

Operating instructions for any coding agent working on this repository. **Read this file at the start of every session before touching code.**

The single most important rule: **if a quality gate fails, the change is not done.** No "I'll fix the lint later", no merging red CI.

---

## 1. Project context

WEBTE2 final assignment. A web application that exposes Octave (CAS) over a REST API and provides:

- Octave console with syntax highlighting and persistent workspace
- Two animated dynamic-system simulations (inverted pendulum, ball on beam) with synchronised charts
- Bilingual UI (SK/EN), responsive, dark/light
- Request logs with CSV export
- Dynamically generated OpenAPI documentation in HTML and PDF (with header/footer page numbering)
- Anonymous usage statistics with IP geolocation and cooldown debouncing
- Fully containerised via Docker Compose

Stack is locked. Do not change without an ADR in `docs/decisions/`.

---

## 2. Stack (locked)

| Layer | Choice |
|---|---|
| PHP | 8.5 |
| Framework | Laravel 13 |
| Frontend bridge | Inertia.js v2 |
| UI | React 19 + TypeScript 5.7 strict |
| Styling | Tailwind CSS 4 |
| Database | MySQL 9 |
| Cache / queue / session driver | Redis 7 |
| Queue dashboard | Laravel Horizon |
| Octave bridge | Python 3.13 + aiohttp 3.x in a separate container |
| CAS | GNU Octave (latest stable in Ubuntu 24.04 image) |
| Code editor (frontend) | CodeMirror 6 + `@codemirror/legacy-modes/mode/octave` |
| Charting | Chart.js 4 |
| 2D animation | Konva.js |
| 3D animation (optional) | Three.js |
| API docs | dedoc/scramble |
| PDF generation | spatie/browsershot |
| DTOs | spatie/laravel-data |
| Tests (PHP) | Pest 4 (see ADR-0001) |
| Tests (JS) | Vitest + Testing Library |
| Static analysis | PHPStan **level max** + larastan |
| Formatter (PHP) | Laravel Pint |
| Linter (JS) | ESLint 9 flat + `@typescript-eslint` + Prettier 3 |
| Geolocation | MaxMind GeoLite2-City (embedded in image) |
| Reverse proxy | nginx 1.27 |
| Container | Docker Compose v2 |

---

## 3. Container split (web vs cli)

There are two Laravel containers built from the same image:

- **`web`** — runs PHP-FPM, serves HTTP via nginx upstream. Synchronous request/response only.
- **`cli`** — runs `php artisan horizon` (which spawns queue workers) and `php artisan schedule:work`. Long-running, restarts on failure.

**Rules**:

- Anything slow, fallible, or schedulable goes into a queued job processed by `cli`.
- Anything that responds to a user click runs synchronously in `web`.
- Both containers share the same source code and `.env`.

See ARCHITECTURE.md §3 for the full topology.

---

## 4. Code quality gates (non-negotiable)

```bash
# PHP
composer qa   # pint --test && phpstan analyse && pest --parallel

# JS
npm run qa    # tsc --noEmit && eslint . --max-warnings=0 && prettier --check . && vitest run

# Python (Octave bridge)
make qa       # ruff check && ruff format --check && mypy --strict && pytest
```

CI runs all three on every PR. Status checks are required for merge.

- **Pint**: zero diff. `laravel` preset + `declare_strict_types`, `no_unused_imports`, `ordered_imports`, `final_class`, `void_return`.
- **PHPStan**: level **max**, no baseline. Adding to `phpstan-baseline.neon` requires written justification in the PR.
- **TypeScript**: `strict: true`, `noUncheckedIndexedAccess: true`, `exactOptionalPropertyTypes: true`, no `any`, no `// @ts-ignore` (use `// @ts-expect-error` with a reason).
- **ESLint**: `--max-warnings=0`.
- **mypy**: `--strict`.

---

## 5. Laravel patterns — heavy use, deliberately

This project is an opportunity to build a properly-engineered Laravel app. Don't write fat controllers; lean on the framework.

### 5.1 Actions

Business logic lives in `app/Actions/`. One public method per action: `handle(...)`. Actions are `final readonly`, dependencies injected via the constructor.

```php
final readonly class ExecuteOctaveCommand
{
    public function __construct(private OctaveBridgeClient $bridge) {}
    public function handle(string $sessionId, string $command): OctaveExecutionResult { /* ... */ }
}
```

### 5.2 Events & Listeners

Cross-cutting side effects fire through events. The producer doesn't know who listens. **Listeners are queued by default** (`implements ShouldQueue`).

Events fired in this app (full catalog in ARCHITECTURE.md §6):

| Event | Fired when | Listeners |
|---|---|---|
| `OctaveCommandExecuted` | Command runs in console | `RecordOctaveMetricsListener` (queued) |
| `SimulationStarted` | Pendulum/ball-beam sim runs | `RecordAnimationUsageListener` (queued) |
| `ApiKeyUsed` | Authenticated request hits any API route | `UpdateApiKeyLastUsedListener` (queued) |

**Why this matters**: the simulation controller doesn't know about statistics. The auth middleware doesn't write to the DB. Each concern lives in one listener and is testable in isolation with `Event::fake()` and `Bus::fake()`.

### 5.3 Observers

Model lifecycle hooks live in `app/Observers/`. Use observers, never model events inside the model.

| Observer | Hooks |
|---|---|
| `RequestLogObserver` | `creating`: assign ULID `request_id` and `created_at` if not set |
| `ApiKeyObserver` | `creating`: hash the plaintext key, set the prefix |
| `AnimationUsageObserver` | `creating`: backfill geolocation if absent |

Register in `AppServiceProvider::boot()`.

### 5.4 Jobs (queued)

Long or scheduled work goes into `app/Jobs/`. Every job:

- `implements ShouldQueue`
- Sets `public int $tries` and `public int $timeout` explicitly
- Has `public function backoff(): array` returning a retry schedule
- Is `final` (Pint enforces)

Jobs in this app:

| Job | Triggered by | Why |
|---|---|---|
| `PruneStaleOctaveSessionsJob` | Scheduler (daily 02:00) | Delete `.mat` files older than 24h |
| `PruneOldRequestLogsJob` | Scheduler (weekly Sunday 03:00) | Delete request logs older than 90 days |
| `RegenerateApiDocsCacheJob` | Scheduler (daily 04:00) + post-deploy hook | Pre-warm the OpenAPI spec cache |
| `GenerateApiDocsPdfJob` | User clicks "Download PDF" | Run Browsershot off the request thread |
| `GenerateLargeCsvExportJob` | User exports >10k log rows | Avoid request-time memory pressure |

### 5.5 Scheduled tasks

Defined in `routes/console.php` (Laravel 13 style). Each scheduled invocation **dispatches a job**, never runs work in the scheduler process itself. The cli container runs `schedule:work`.

```php
Schedule::job(new PruneStaleOctaveSessionsJob)->dailyAt('02:00');
Schedule::job(new PruneOldRequestLogsJob)->weeklyOn(0, '03:00');
Schedule::job(new RegenerateApiDocsCacheJob)->dailyAt('04:00');
```

### 5.6 Custom validation rules

Physical bounds for simulations live in dedicated rule classes:

- `ValidPendulumParameters` — checks M > 0, m > 0, l > 0, etc.
- `ValidBallBeamParameters`

Used in Form Requests:

```php
public function rules(): array
{
    return ['parameters' => ['required', 'array', new ValidPendulumParameters]];
}
```

### 5.7 Eloquent: scopes, casts, factories

- **Scopes**: `RequestLog::successful()`, `RequestLog::forApiKey($id)`, `AnimationUsage::within(Period $p)`. Reuse query logic.
- **Casts**: `parameters` columns cast to DTOs via custom casts wrapping `spatie/laravel-data`.
- **Factories**: every model has one. Used by tests and the `DemoSeeder`.

### 5.8 Form Requests, Resources, DTOs

- **Form Requests** validate every endpoint. Authorisation in `authorize()`.
- **API Resources** for every JSON response. No `return $model` from a controller.
- **DTOs** (`spatie/laravel-data`) for every cross-boundary data shape.

### 5.9 Service container

Bind interfaces in `AppServiceProvider`:

```php
$this->app->bind(OctaveBridgeClient::class, HttpOctaveBridgeClient::class);
$this->app->singleton(GeolocationReader::class, fn () => new Reader(config('cas.geolite_db_path')));
```

In tests, swap to fakes:

```php
$this->app->bind(OctaveBridgeClient::class, FakeOctaveBridgeClient::class);
```

---

## 6. PHP / type system rules

- `declare(strict_types=1);` in every file.
- Type every parameter and return type.
- No `array` as a type when the shape is known. Use a DTO.
- No `mixed` to dodge the type system.
- Backed enums for closed sets.
- `readonly` properties and classes wherever mutation isn't required.
- `final` on every non-Eloquent / non-abstract / non-extension-point class.

PHP 8.5 features to use deliberately:

- Pipe operator `|>` for chained transformations
- `array_first()` / `array_last()` instead of manual indexing
- `#[\NoDiscard]` on action return values that callers must use

---

## 7. React / TypeScript rules

- Functional components only. No classes.
- Default-export the component, named-export everything else.
- Props as `type ${Name}Props`.
- Custom hooks in `resources/js/hooks/`, one per file, named `use*`.
- Inertia page props over `useEffect(fetch, [])` for initial data.
- Tailwind tokens only — no arbitrary colours in JSX, no `style={{}}` for what Tailwind expresses.
- Forms: React Hook Form + Zod, schema mirrors backend `Data` class.

---

## 8. Security rules

- Octave commands sanitised via blocklist + sandboxed bridge container with no network egress.
- API keys: `hash_equals()` only for comparison; hashed in DB; plain text shown once on creation.
- CSP strict; `unsafe-inline` allowed only on the `/api-docs` route (Swagger UI requirement).
- Rate limits on every API route; per-API-key bucket on `/api/v1/octave/exec`.
- Input validation via Form Requests. Physical bounds in custom rules.
- Secrets never logged. Octave commands truncated to 1024 chars in logs.
- Headers: HSTS, X-Content-Type-Options, X-Frame-Options DENY, Referrer-Policy strict-origin-when-cross-origin.
- Horizon dashboard gated behind `HORIZON_ADMIN_TOKEN` env var (see `app/Providers/HorizonServiceProvider.php`).

---

## 9. Testing rules

- Pest for PHP. Feature tests > unit tests for business code.
- Every controller endpoint: happy path, validation failure (422), auth failure (401).
- Every event firer: `Event::fake([SpecificEvent::class])` + assert dispatched with the right payload.
- Every listener: `Bus::fake()` or `Queue::fake()` + assert the side effect.
- Every job: assert dispatch from caller; test the job's `handle()` directly.
- The `OctaveBridgeClient` interface has `FakeOctaveBridgeClient` swapped in via the container.
- Real Octave never runs in CI tests; reserve real-Octave runs for the bridge's own pytest suite.
- React: Vitest + Testing Library, behaviour over implementation.

---

## 10. Agent handoff protocol

This repo is built by multiple AI agents in parallel and sequence. Discipline matters.

### Starting a phase

1. Read `CLAUDE.md` (this file).
2. Read the relevant `docs/ARCHITECTURE.md` sections (each phase markdown lists which).
3. Read `docs/phases/phase-XX-*.md` end to end.
4. Pull the latest `main`, branch as `phase-XX/short-slug`.
5. Walk the **Definition of Done** checklist before starting work. If anything is unclear, stop and ask the human running the agent.

### During the phase

- One commit per logical unit. Conventional Commits subject (`feat(scope): ...`, `fix(scope): ...`).
- Run quality gates after each unit. **Don't accumulate failures.**
- If you discover the phase markdown is wrong (missing dep, contradictory requirement), stop and surface it. Do not invent a workaround silently.

### Finishing a phase

1. All quality gates green locally.
2. Open a PR using the template (see §12).
3. Confirm the **Hand-off** section of the phase markdown is satisfied — the next phase's prerequisites are in place.
4. Tag the PR `phase:XX`.

### Cross-phase contracts

Phase markdowns reference shared types (`OctaveBridgeClient`, `SimulationTrajectory` DTO, `AnimationName` enum, `SimulationStarted` event). The earlier phase that introduces a contract is the source of truth. Later phases must not redefine; they import.

---

## 11. Git conventions

- `main` is protected. PR required, CI required, linear history.
- Branch naming: `phase-XX/short-slug`.
- Conventional Commits: `feat(scope): subject`, `fix(scope): subject`, `chore: subject`. Imperative, lowercase, no trailing period, ≤ 72 chars.
- Squash-merge into `main`.
- One PR per phase by default. Sub-PRs allowed for very large phases (02, 06) — coordinate with the human.

---

## 12. PR template

```markdown
## What
Short description.

## Why
Link to the phase doc and any issue.

## How
Notable design decisions, trade-offs.

## Quality gates
- [ ] Pint
- [ ] PHPStan max
- [ ] Pest (all green, including new tests)
- [ ] tsc --noEmit
- [ ] ESLint --max-warnings=0
- [ ] Prettier --check
- [ ] Vitest
- [ ] mypy --strict (if Python touched)
- [ ] Manually tested in Chrome and Firefox
- [ ] Mobile viewport (375 px) sanity-checked

## Risks
What could break, follow-ups required.
```

---

## 13. Anti-patterns to reject on sight

- `dd()`, `dump()`, `print_r()`, `var_dump()`, `console.log()` in committed code.
- `// TODO` without an issue link.
- Magic numbers — extract to a named constant or config.
- Catching `\Exception` or `\Throwable` and silently logging.
- `useEffect` for derived data that should be computed on render.
- `as` type assertions in TypeScript without a comment explaining why.
- Inline SQL in Eloquent models.
- Business logic in Eloquent model methods (use actions).
- Migrations that contain seeded data (use seeders).
- Service container bindings outside Service Providers.
- Direct `new` of services inside actions (use DI).
- Tailwind `!important` (`!`) — fix the cascade properly.
- Comments that restate the code instead of explaining intent.
- Synchronous slow work in `web` that belongs in a queued job on `cli`.
- Logic that should fire an event but is wired directly into a controller (Phase 09's `RecordAnimationUsage` is the canonical example — driven by listener, not by direct call).

---

## 14. When in doubt

- A pattern not covered? Mirror the closest existing one.
- The closest existing pattern is bad? Open a discussion in the PR before rolling a third pattern.
- Genuinely stuck? Stop and surface it. Don't invent abstractions to defer the decision.
