# Phase 02 — Octave bridge

**Duration**: 2–3 d
**Tier**: **senior**
**Required reading**: `CLAUDE.md`, `docs/ARCHITECTURE.md` §§ 4, 7

## Goal

A sandboxed Python service that takes `(session_id, command)`, executes it in Octave, persists workspace state across calls, and returns structured output. The riskiest phase in the project — get it right, everything else depends on it.

## Definition of Done

- [ ] `POST /exec` accepts `{session_id, command}`, returns `{stdout, stderr, exit_code, duration_ms}`
- [ ] `DELETE /sessions/{id}` removes a workspace (used by console "Clear" button)
- [ ] `POST /sessions/prune` deletes `.mat` files older than 24h (used by the queued job)
- [ ] Workspace persists between calls within the same `session_id`: `a = 1+1; a+2` returns `4` across two requests
- [ ] Forbidden commands rejected before reaching Octave (with a clear error)
- [ ] Hard timeout enforced (default 10s, per-request override allowed up to 30s)
- [ ] Server-side slowdown coefficient applied (configurable via env)
- [ ] Bridge container has **no network egress** (verified by `wget` failing inside the container)
- [ ] Container is read-only except for `/var/octave/sessions` and `/tmp`
- [ ] Python test suite covers: happy path, every forbidden pattern, timeout, malformed input, workspace persistence
- [ ] PHP-side `OctaveBridgeClient` interface + real impl + `FakeOctaveBridgeClient` for tests
- [ ] PHP-side `PruneStaleOctaveSessionsJob` (queued, scheduled daily)
- [ ] PHP feature test runs an end-to-end command against the real bridge in CI

## Prerequisites

Phase 01 complete.

## Tasks

### 2.1 HTTP surface

`docker/octave-bridge/src/main.py`:

```python
from aiohttp import web
from .handlers import exec_handler, health_handler, delete_session_handler, prune_handler
from .middleware import error_middleware, request_id_middleware

def build_app() -> web.Application:
    app = web.Application(middlewares=[error_middleware, request_id_middleware])
    app.router.add_post('/exec', exec_handler)
    app.router.add_delete('/sessions/{session_id}', delete_session_handler)
    app.router.add_post('/sessions/prune', prune_handler)
    app.router.add_get('/health', health_handler)
    return app

if __name__ == '__main__':
    web.run_app(build_app(), host='0.0.0.0', port=8001)
```

Request/response shapes:

```jsonc
// POST /exec
{ "session_id": "uuid-or-ulid", "command": "a = 1+1; a+2", "timeout_seconds": 10 }
// → 200
{ "stdout": "ans = 4\n", "stderr": "", "exit_code": 0, "duration_ms": 42, "request_id": "req-..." }
// → 422 forbidden
{ "error": "command_rejected", "reason": "Forbidden token: 'system'", "request_id": "req-..." }
// → 408 timeout
{ "error": "octave_timeout", "request_id": "req-..." }

// POST /sessions/prune
{ "older_than_hours": 24 }
// → 200
{ "deleted_count": 7, "request_id": "req-..." }
```

### 2.2 Forbidden-symbol blocklist

`src/sanitiser.py`:

```python
import re

FORBIDDEN_PATTERNS = [
    r'\bsystem\b', r'\bunix\b', r'\bdos\b', r'\bpopen\b',
    r'\beval\b', r'\bexec\b', r'\bsource\b',
    r'\bmkfifo\b', r'\bfopen\b', r'\bfclose\b', r'\bfwrite\b',
    r'\bload\b', r'\bsave\b',                  # bridge controls these
    r'\b__octave_config_info__\b',
    r'\baddpath\b', r'\brmpath\b',
    r'!',                                      # shell escape
]

_compiled = [re.compile(p) for p in FORBIDDEN_PATTERNS]

def sanitise(command: str, max_length: int = 4096) -> None:
    """Raises CommandRejected on a forbidden token."""
    if len(command) > max_length:
        raise CommandRejected(f"Command exceeds max length ({max_length})")
    for pattern in _compiled:
        if pattern.search(command):
            raise CommandRejected(f"Forbidden token: {pattern.pattern}")
```

This is **defence in depth**. The container sandbox is the primary defence; the blocklist is a clear and helpful early error.

### 2.3 Workspace persistence

`src/runner.py`:

```python
import asyncio, os, time
from pathlib import Path

SESSION_DIR = Path(os.environ.get('SESSION_DIR', '/var/octave/sessions'))
SESSION_ID_PATTERN = re.compile(r'^[A-Za-z0-9_-]{8,64}$')

async def run_command(session_id: str, command: str, timeout_seconds: int) -> ExecResult:
    if not SESSION_ID_PATTERN.match(session_id):
        raise CommandRejected("Invalid session_id")

    workspace = SESSION_DIR / f'{session_id}.mat'
    parts: list[str] = []
    if workspace.exists():
        parts.append(f"load('{workspace}');")
    parts.append(command if command.rstrip().endswith(';') else command.rstrip() + ';')
    parts.append(f"save('-binary', '{workspace}');")
    script = ' '.join(parts)

    started = time.monotonic()
    proc = await asyncio.create_subprocess_exec(
        '/usr/bin/octave', '--no-init-file', '--no-gui', '--quiet',
        '--eval', script,
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    try:
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout_seconds)
    except asyncio.TimeoutError:
        proc.kill()
        raise OctaveTimeout(f"Octave exceeded {timeout_seconds}s")

    duration_ms = int((time.monotonic() - started) * 1000)

    slowdown = int(os.environ.get('SLOWDOWN_MS', '0'))
    if slowdown > 0:
        await asyncio.sleep(slowdown / 1000)

    return ExecResult(
        stdout=stdout.decode('utf-8', errors='replace'),
        stderr=stderr.decode('utf-8', errors='replace'),
        exit_code=proc.returncode or 0,
        duration_ms=duration_ms,
    )
```

### 2.4 Container lockdown

In `docker-compose.yml` for `octave-bridge`:

```yaml
octave-bridge:
  read_only: true
  tmpfs: [/tmp]
  cap_drop: ["ALL"]
  security_opt: ["no-new-privileges:true"]
  ulimits:
    cpu: 30
    nproc: 64
    fsize: 10485760  # 10 MB max file
  deploy:
    resources:
      limits: { memory: 512M, cpus: '1.0' }
```

Verify in CI:

```bash
docker compose exec octave-bridge sh -c "wget -q -T 3 http://example.com" && exit 1 || echo OK
```

### 2.5 Cleanup endpoint + queued job

Bridge endpoint `POST /sessions/prune` deletes `.mat` files older than the requested age.

PHP side: `app/Jobs/PruneStaleOctaveSessionsJob.php`:

```php
final class PruneStaleOctaveSessionsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;
    public int $uniqueFor = 3600;

    public function backoff(): array { return [60, 300, 900]; }
    public function uniqueId(): string { return 'prune-octave-sessions'; }

    public function handle(OctaveBridgeClient $bridge): void
    {
        $deleted = $bridge->pruneSessions(olderThanHours: 24);
        Log::info('octave sessions pruned', ['deleted' => $deleted]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('PruneStaleOctaveSessionsJob failed', ['exception' => $e]);
    }
}
```

In `routes/console.php`:

```php
Schedule::job(new PruneStaleOctaveSessionsJob)->dailyAt('02:00');
```

### 2.6 PHP client

`app/Services/Octave/OctaveBridgeClient.php` (interface):

```php
interface OctaveBridgeClient
{
    public function execute(string $sessionId, string $command, ?int $timeoutSeconds = null): OctaveExecutionResult;
    public function clearSession(string $sessionId): void;
    public function pruneSessions(int $olderThanHours = 24): int;
}
```

`HttpOctaveBridgeClient` — uses Laravel's `Http` facade. Maps:

- 200 → `OctaveExecutionResult`
- 422 → throw `OctaveCommandRejectedException`
- 408 → throw `OctaveTimeoutException`
- 5xx / network → throw `OctaveBridgeUnavailableException`

`FakeOctaveBridgeClient` for tests — records calls, returns canned responses.

Bind in `AppServiceProvider`:

```php
$this->app->bind(OctaveBridgeClient::class, HttpOctaveBridgeClient::class);
```

### 2.7 Tests

**Python** (`docker/octave-bridge/tests/`):
- `test_sanitiser.py` — every forbidden pattern rejected; legitimate accepted
- `test_runner.py` — workspace persistence (real Octave; runs in CI)
- `test_handlers.py` — HTTP layer with mocked runner
- `test_session_id_validation.py` — path traversal attempts rejected (`../../etc/passwd`, `..\\..\\`, etc.)

**PHP** (`tests/Feature/Services/Octave/`):
- `OctaveBridgeClientTest.php` — `Http::fake()` exhaustively covers the status code → exception map
- `PruneStaleOctaveSessionsJobTest.php` — `Queue::fake()`, dispatch from scheduler asserted; job's `handle()` called with fake bridge
- `EndToEndOctaveTest.php` (CI marker `@group integration`) — real bridge call

## Quality gates

- [ ] All forbidden-pattern tests pass
- [ ] Path-traversal session_id rejected
- [ ] Network-isolation check passes in CI
- [ ] Workspace persistence verified across two real Octave invocations
- [ ] PHPStan max + mypy strict clean
- [ ] Job is dispatched from scheduler (verify via `Queue::fake()` + `Schedule::call()` test)

## Risks

| Risk | Mitigation |
|---|---|
| Sandbox escape | Defence in depth: blocklist + container isolation + no network + `cap_drop: ALL` + read-only FS |
| Octave hangs | Hard timeout via `asyncio.wait_for` |
| Disk fill | `fsize` ulimit + scheduled cleanup + 10 MB session cap |
| Path injection via session_id | Regex validation before any FS use |
| Slow Octave cold start (~300 ms) | Acceptable for v1 |

## Hand-off to next phase

Phase 03 needs: `OctaveBridgeClient` interface + bound impl. Phases 05/06/07 will inject the client.

## Agent brief (copy-paste)

> Read `CLAUDE.md`, `docs/ARCHITECTURE.md` §§ 4, 7, and this phase markdown.
>
> Implement `docker/octave-bridge/src/{main,handlers,runner,sanitiser,errors,middleware}.py` per the phase doc. Tests under `docker/octave-bridge/tests/` cover: every forbidden pattern, workspace persistence with real Octave (two consecutive runs), timeout enforcement, malformed input, path-traversal session IDs.
>
> Container lockdown: `read_only: true`, `cap_drop: ALL`, `tmpfs /tmp`, ulimits per the doc. Verify network egress is blocked.
>
> Implement `app/Services/Octave/{OctaveBridgeClient,HttpOctaveBridgeClient,FakeOctaveBridgeClient,OctaveExecutionResult}.php` with the three exceptions. Bind in `AppServiceProvider`. Tests use `Http::fake()` to cover status code mapping.
>
> Implement `app/Jobs/PruneStaleOctaveSessionsJob.php` (`ShouldQueue` + `ShouldBeUnique`, explicit `$tries`, `$timeout`, `backoff()`, `failed()`). Schedule daily at 02:00 in `routes/console.php`. Test with `Queue::fake()`.
>
> All quality gates green (`composer qa`, `npm run qa`, `make qa` in the bridge directory). PR labelled `phase:02`.
