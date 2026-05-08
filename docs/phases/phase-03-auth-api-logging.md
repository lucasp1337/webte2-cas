# Phase 03 — Auth, API surface, logging, events, observers

**Duration**: 1.5–2 d
**Tier**: any
**Required reading**: `CLAUDE.md` §§ 5, 8, `docs/ARCHITECTURE.md` §§ 5–8

## Goal

Every public route is API-key-protected, every call is logged, logs export to CSV. The first events, observers, and listeners land in this phase — establishing the patterns for everything that follows.

## Definition of Done

- [ ] `ApiKeyMiddleware` rejects requests without a valid `X-API-Key`; constant-time compare; key hashed in DB
- [ ] `ApiKeyMiddleware` fires `ApiKeyUsed` event after successful auth
- [ ] `UpdateApiKeyLastUsedListener` (queued) updates `last_used_at` — middleware does **not** write to the DB
- [ ] `LogRequestMiddleware` writes a `RequestLog` row for every `/api/v1/*` call
- [ ] `RequestLogObserver` assigns ULID `request_id` on creating
- [ ] `ApiKeyObserver` hashes plaintext on creating; rejects updates that mutate `key_hash`
- [ ] All API routes registered (stub handlers; real ones in 05/06/07/09)
- [ ] `GET /api/v1/logs` paginated JSON
- [ ] `GET /api/v1/logs/export.csv` streams CSV (≤ 10k rows) or enqueues `GenerateLargeCsvExportJob` (> 10k rows)
- [ ] `php artisan cas:create-api-key {name}` issues a key and prints it once
- [ ] Eloquent factories for `ApiKey`, `RequestLog`
- [ ] Feature tests for every endpoint: happy path, 401 (missing/invalid key), 422 where applicable
- [ ] Event/listener tests: `Event::fake()` for firers, `Bus::fake()` / direct call for listeners

## Prerequisites

Phases 01 and 02 complete.

## Tasks

### 3.1 Migrations

`api_keys`:

```php
$table->id();
$table->string('name');
$table->string('key_hash')->unique();
$table->string('key_prefix', 8);
$table->timestamp('last_used_at')->nullable();
$table->timestamps();
```

`request_logs`:

```php
$table->id();
$table->ulid('request_id')->unique();
$table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
$table->string('route', 128);
$table->string('method', 8);
$table->string('anon_token', 36)->nullable()->index();
$table->string('ip_hash', 64)->nullable();
$table->text('command')->nullable();
$table->boolean('success');
$table->smallInteger('status_code');
$table->integer('duration_ms')->nullable();
$table->text('error_message')->nullable();
$table->timestamp('created_at')->index();
```

### 3.2 Models, observers, factories

`app/Models/ApiKey.php`:

- Static `findByPlaintextKey(string $key): ?self` does the SHA-256 + DB lookup
- `scopeActive()` for queries
- Observer registered

`app/Observers/ApiKeyObserver.php`:

```php
final readonly class ApiKeyObserver
{
    public function creating(ApiKey $apiKey): void
    {
        if (str_starts_with($apiKey->key_hash, '$')) return; // already hashed
        $plain = $apiKey->key_hash; // it was set as plaintext
        $apiKey->key_hash = hash('sha256', $plain . config('app.key'));
        $apiKey->key_prefix = substr($plain, 0, 8);
    }

    public function updating(ApiKey $apiKey): void
    {
        if ($apiKey->isDirty('key_hash')) {
            throw new \LogicException('API key hash is immutable');
        }
    }
}
```

`app/Observers/RequestLogObserver.php`:

```php
final readonly class RequestLogObserver
{
    public function creating(RequestLog $log): void
    {
        $log->request_id ??= (string) Str::ulid();
        $log->created_at ??= now();
    }
}
```

Register both in `AppServiceProvider::boot()`:

```php
ApiKey::observe(ApiKeyObserver::class);
RequestLog::observe(RequestLogObserver::class);
```

Factories under `database/factories/`.

### 3.3 Console command

`app/Console/Commands/CreateApiKeyCommand.php`:

```php
final class CreateApiKeyCommand extends Command
{
    protected $signature = 'cas:create-api-key {name}';
    protected $description = 'Create a new API key and print it once';

    public function handle(): int
    {
        $plain = 'webte2_' . Str::random(48);
        ApiKey::create(['name' => $this->argument('name'), 'key_hash' => $plain]);
        $this->warn('Save this key now. It will not be shown again:');
        $this->line($plain);
        return self::SUCCESS;
    }
}
```

(The observer hashes `key_hash` on `creating` — that's why it's set to plaintext above.)

The seeder uses the same flow with the `CAS_API_KEY_PLAINTEXT` env var so the demo deployment has a known key.

### 3.4 Event + listener: `ApiKeyUsed`

`app/Events/ApiKeyUsed.php`:

```php
final readonly class ApiKeyUsed
{
    use Dispatchable, SerializesModels;
    public function __construct(public ApiKey $apiKey) {}
}
```

`app/Listeners/UpdateApiKeyLastUsedListener.php`:

```php
final class UpdateApiKeyLastUsedListener implements ShouldQueue
{
    public int $tries = 3;
    public function handle(ApiKeyUsed $event): void
    {
        $event->apiKey->forceFill(['last_used_at' => now()])->save();
    }
}
```

Auto-discovered (Laravel 13). No manual binding required.

### 3.5 Middleware

`app/Http/Middleware/ApiKeyMiddleware.php`:

```php
final class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-API-Key') ?? '';
        abort_if($key === '', 401, 'Missing API key');

        $apiKey = ApiKey::findByPlaintextKey($key);
        abort_if($apiKey === null, 401, 'Invalid API key');

        $request->attributes->set('api_key', $apiKey);
        ApiKeyUsed::dispatch($apiKey);
        return $next($request);
    }
}
```

Notice: middleware no longer writes to the DB. Side effect lives in the listener, which runs on `cli`.

`app/Http/Middleware/LogRequestMiddleware.php`:

```php
public function handle(Request $request, Closure $next): Response
{
    $log = RequestLog::create([     // observer assigns request_id and created_at
        'api_key_id' => $request->attributes->get('api_key')?->id,
        'route' => $request->route()?->getName() ?? $request->path(),
        'method' => $request->method(),
        'anon_token' => $request->cookie('anon_token'),
        'ip_hash' => hash('sha256', $request->ip() . config('app.key')),
        'success' => false,
        'status_code' => 0,
    ]);

    $request->attributes->set('request_id', $log->request_id);
    $started = hrtime(true);
    $response = $next($request);
    $durationMs = (int) ((hrtime(true) - $started) / 1e6);

    $log->update([
        'success' => $response->isSuccessful(),
        'status_code' => $response->status(),
        'duration_ms' => $durationMs,
        'command' => Str::limit((string) ($request->input('command') ?? ''), 1024),
    ]);

    $response->headers->set('X-Request-Id', $log->request_id);
    return $response;
}
```

Register in `bootstrap/app.php`:

```php
$middleware->group('api-protected', [
    ApiKeyMiddleware::class,
    LogRequestMiddleware::class,
    'throttle:60,1',
]);
```

### 3.6 Routes

`routes/api.php`:

```php
Route::prefix('v1')->middleware('api-protected')->group(function () {
    Route::post('/octave/exec', ExecuteOctaveCommandController::class)->name('octave.exec');
    Route::delete('/octave/session', ClearOctaveSessionController::class)->name('octave.clear-session');

    Route::post('/simulations/pendulum', RunPendulumSimulationController::class)->name('simulations.pendulum');
    Route::post('/simulations/ball-beam', RunBallBeamSimulationController::class)->name('simulations.ball-beam');

    Route::get('/logs', ListRequestLogsController::class)->name('logs.index');
    Route::get('/logs/export.csv', ExportRequestLogsCsvController::class)->name('logs.export');

    Route::post('/api-docs/pdf', RequestApiDocsPdfController::class)->name('api-docs.pdf.request');
    Route::get('/api-docs/pdf/{exportId}', DownloadApiDocsPdfController::class)->name('api-docs.pdf.download');

    Route::get('/stats', AnimationStatsController::class)->name('stats.index');
    Route::get('/stats/{animation}', AnimationStatsDetailController::class)->name('stats.detail');
});

Route::get('/health', fn () => response()->json(['status' => 'ok']));
```

Stub controllers return `200` with a TODO body — real handlers fill in later phases.

### 3.7 Logs listing + CSV export

`ListRequestLogsController` — paginated, sortable, filterable by date range and success.

`ExportRequestLogsCsvController`:

```php
public function __invoke(Request $request): Response
{
    $count = RequestLog::count();
    if ($count > 10_000) {
        $exportId = (string) Str::ulid();
        GenerateLargeCsvExportJob::dispatch($exportId, $request->user()?->id);
        return response()->json(['export_id' => $exportId, 'status' => 'queued'], 202);
    }
    return $this->streamSync();
}

private function streamSync(): StreamedResponse
{
    return response()->streamDownload(function () {
        $out = fopen('php://output', 'w');
        fputcsv($out, ['request_id', 'created_at', 'route', 'method', 'success', 'status_code', 'duration_ms', 'command_truncated']);
        RequestLog::orderBy('created_at')->chunkById(500, function ($chunk) use ($out) {
            foreach ($chunk as $log) {
                fputcsv($out, [
                    $log->request_id, $log->created_at?->toIso8601String(),
                    $log->route, $log->method, (int) $log->success,
                    $log->status_code, $log->duration_ms,
                    Str::limit($log->command ?? '', 200),
                ]);
            }
        });
        fclose($out);
    }, 'request-logs-' . now()->format('Y-m-d-His') . '.csv', ['Content-Type' => 'text/csv']);
}
```

`GenerateLargeCsvExportJob` — stores the file in `storage/app/exports/{exportId}.csv`. A poll endpoint `GET /api/v1/logs/export/{id}` returns status (`queued|processing|ready|failed`) and a download URL when ready. Job uses `chunkById` with `lazy()` to keep memory flat.

### 3.8 Tests

- `ApiKeyMiddlewareTest`: missing key (401), invalid key (401), valid key (200) + `Event::fake([ApiKeyUsed::class])` asserts dispatch
- `UpdateApiKeyLastUsedListenerTest`: instantiate listener, call `handle()` with event, assert `last_used_at` updated
- `LogRequestMiddlewareTest`: a request creates a `RequestLog` row with the right shape and ULID
- `RequestLogObserverTest`: creating without `request_id` yields a ULID; `created_at` defaults to `now()`
- `ApiKeyObserverTest`: creating with plaintext hashes; updating `key_hash` throws
- `CreateApiKeyCommandTest`: command produces a key, key works on a subsequent request
- `ExportRequestLogsCsvControllerTest`: small dataset → streamed CSV; large dataset → `Queue::fake()` asserts `GenerateLargeCsvExportJob` dispatched
- `GenerateLargeCsvExportJobTest`: writes a file with the right rows

## Quality gates

- [ ] All endpoints respond per `openapi.yaml`
- [ ] Feature tests green
- [ ] PHPStan max clean
- [ ] CSV export verified with a 50k-row seeder (memory < 64 MB during export)
- [ ] `X-Request-Id` header present on every response
- [ ] `ApiKeyUsed` listener appears in Horizon dashboard during a manual smoke test

## Risks

| Risk | Mitigation |
|---|---|
| `last_used_at` write contention | Now async via queue; bursts are absorbed |
| Request log table growth | `PruneOldRequestLogsJob` (Phase 11 ships the schedule) |
| Listener failures silently lost | Horizon dashboard surfaces failures; listener has `$tries = 3` |

## Hand-off to next phase

Phases 04, 05, 06, 07, 09 depend on:
- The `api-protected` middleware group
- The `ApiKey` model + auth
- The `ApiKeyUsed` event pattern (other events follow the same shape)
- The observer pattern (other models will get observers)

## Agent brief (copy-paste)

> Read `CLAUDE.md` §§ 5, 8 and `docs/ARCHITECTURE.md` §§ 5–8 and this phase markdown.
>
> Build the migrations, models, observers, factories, middleware, event + listener, console command, controllers, resources, and feature tests per the phase doc.
>
> Patterns to follow strictly:
> - Middleware does NOT write to the DB. It fires `ApiKeyUsed`. The queued listener writes.
> - Models get observers; observers register in `AppServiceProvider::boot()`.
> - Each event has `Event::fake()` test asserting dispatch from the firer.
> - Each listener has a direct `handle()` test asserting the side effect.
> - CSV export uses `chunkById` for memory safety.
> - Large CSV exports (> 10k rows) enqueue a job; poll endpoint returns status; fall back to sync streaming for small datasets.
>
> Run `composer qa`. Manually smoke-test with `php artisan cas:create-api-key demo` then curl an endpoint with the printed key — Horizon should show `UpdateApiKeyLastUsedListener` ran.
>
> PR labelled `phase:03`.
