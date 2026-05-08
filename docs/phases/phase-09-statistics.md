# Phase 09 — Statistics (event-driven)

**Duration**: 1 d
**Tier**: any
**Required reading**: `CLAUDE.md` §§ 5, `docs/ARCHITECTURE.md` §§ 6, 8, 12

## Goal

Anonymous usage statistics for the two animations: per-day call counts and country/city breakdown via IP geolocation, with a 10-minute cooldown to debounce rapid reloads from the same anonymous user.

The whole feature is driven by a queued listener for `SimulationStarted`. Phase 06 and Phase 07 controllers don't know stats exist.

## Definition of Done

- [ ] `animation_usages` migration: id, animation, anon_token, started_at, country_iso, country, city, created_at
- [ ] `EnsureAnonTokenMiddleware` sets `anon_token` cookie (HttpOnly, SameSite=Lax, 1 year) on `/api/v1/simulations/*`
- [ ] `RecordAnimationUsageListener` (queued) handles `SimulationStarted`:
  - Skips if same anon_token + animation has a record within `STATS_COOLDOWN_MINUTES`
  - Otherwise resolves geolocation and inserts
- [ ] `AnimationUsageObserver` backfills geolocation if the listener didn't (defence in depth)
- [ ] `GeolocationService` reads from GeoLite2-City via `geoip2/geoip2`
- [ ] `GET /api/v1/stats` returns: total per animation, per-day for the last 30 days, top countries
- [ ] `GET /api/v1/stats/{animation}` returns the per-animation drill-down (per-day, per-country, per-city)
- [ ] Frontend `/stats` page renders all of the above (Chart.js bars + table)
- [ ] `RefreshGeolocationDatabaseJob` runs monthly (only if `GEOLITE_LICENSE_KEY` is set)

## Prerequisites

Phase 03 (events/observer infra), Phase 06 (`SimulationStarted` event exists), Phase 07 (second firer of the same event).

## Tasks

### 9.1 Migration

```php
Schema::create('animation_usages', function (Blueprint $table) {
    $table->id();
    $table->string('animation', 32)->index();
    $table->string('anon_token', 36)->index();
    $table->timestamp('started_at')->index();
    $table->string('country_iso', 2)->nullable()->index();
    $table->string('country', 64)->nullable();
    $table->string('city', 96)->nullable();
    $table->timestamps();
    $table->index(['anon_token', 'animation', 'started_at'], 'idx_cooldown');
});
```

### 9.2 Anon token middleware

`app/Http/Middleware/EnsureAnonTokenMiddleware.php`:

```php
final class EnsureAnonTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('anon_token');
        if ($token === null || !Str::isUuid($token)) {
            $token = (string) Str::uuid();
        }
        $request->attributes->set('anon_token', $token);
        $response = $next($request);
        $response->cookie(
            'anon_token', $token,
            minutes: 60 * 24 * 365,
            path: '/', domain: null,
            secure: !app()->environment('local'),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
        return $response;
    }
}
```

Apply in `bootstrap/app.php` to `/api/v1/simulations/*` routes.

The simulation actions (Phases 06 / 07) read this attribute when dispatching `SimulationStarted`:

```php
SimulationStarted::dispatch(
    AnimationName::Pendulum,
    $request->attributes->get('anon_token'),
    $request->ip(),
    md5(serialize($params->toArray())),
);
```

### 9.3 Geolocation service

```php
final class GeolocationService
{
    public function __construct(private readonly Reader $reader) {}

    public function lookup(string $ip): GeolocationResult
    {
        if ($this->isPrivateIp($ip)) {
            return GeolocationResult::unknown();
        }
        try {
            $record = $this->reader->city($ip);
            return new GeolocationResult(
                countryIso: $record->country->isoCode,
                country:    $record->country->name,
                city:       $record->city->name,
            );
        } catch (\Throwable) {
            return GeolocationResult::unknown();
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
```

Bind in `AppServiceProvider`:

```php
$this->app->singleton(Reader::class, fn () => new Reader(config('cas.geolite_db_path')));
```

### 9.4 The listener (the heart of this phase)

`app/Listeners/RecordAnimationUsageListener.php`:

```php
final class RecordAnimationUsageListener implements ShouldQueue
{
    public int $tries = 3;
    public int $timeout = 10;

    public function __construct(
        private readonly GeolocationService $geo,
    ) {}

    public function handle(SimulationStarted $event): void
    {
        $cooldown = (int) config('cas.stats_cooldown_minutes', 10);

        // Race-safe cooldown via Redis lock
        $lockKey = "stats:cooldown:{$event->animation->value}:{$event->anonToken}";
        $lock = Cache::lock($lockKey, $cooldown * 60);
        if (!$lock->get()) {
            // Another worker holds the lock — skip this hit
            return;
        }

        try {
            $recent = AnimationUsage::query()
                ->where('animation', $event->animation->value)
                ->where('anon_token', $event->anonToken)
                ->where('started_at', '>=', now()->subMinutes($cooldown))
                ->exists();

            if ($recent) {
                return; // within cooldown, skip
            }

            $geo = $this->geo->lookup($event->ip);

            AnimationUsage::create([
                'animation'   => $event->animation->value,
                'anon_token'  => $event->anonToken,
                'started_at'  => now(),
                'country_iso' => $geo->countryIso,
                'country'     => $geo->country,
                'city'        => $geo->city,
            ]);
        } finally {
            $lock->release();
        }
    }
}
```

The cooldown is enforced by **both** a Redis lock (race-safe across multiple workers) **and** a DB read (durable). Belt and braces.

### 9.5 Observer (defence in depth)

`AnimationUsageObserver`:

```php
final readonly class AnimationUsageObserver
{
    public function __construct(private GeolocationService $geo) {}

    public function creating(AnimationUsage $usage): void
    {
        // The listener should have set these, but if a row is created elsewhere (seeder, console),
        // backfill from the request IP if available.
        if ($usage->country_iso === null && request()?->ip() !== null) {
            $g = $this->geo->lookup(request()->ip());
            $usage->country_iso = $g->countryIso;
            $usage->country = $g->country;
            $usage->city = $g->city;
        }
    }
}
```

### 9.6 Stats endpoints

`AnimationStatsController`:

```php
public function __invoke(): AnimationStatsResource
{
    $cooldownDays = 30;
    $perAnimation = AnimationUsage::query()
        ->select('animation', DB::raw('count(*) as c'))
        ->where('started_at', '>=', now()->subDays($cooldownDays))
        ->groupBy('animation')
        ->pluck('c', 'animation');

    $perDay = AnimationUsage::query()
        ->select(DB::raw('date(started_at) as d'), 'animation', DB::raw('count(*) as c'))
        ->where('started_at', '>=', now()->subDays($cooldownDays))
        ->groupBy('d', 'animation')
        ->orderBy('d')
        ->get();

    $topCountries = AnimationUsage::query()
        ->select('country_iso', 'country', DB::raw('count(*) as c'))
        ->whereNotNull('country_iso')
        ->groupBy('country_iso', 'country')
        ->orderByDesc('c')
        ->limit(10)
        ->get();

    return AnimationStatsResource::make(compact('perAnimation', 'perDay', 'topCountries'));
}
```

`AnimationStatsDetailController` — the same shape filtered by `animation` + a `cities` breakdown.

### 9.7 Frontend

`resources/js/Pages/Stats.tsx` — Chart.js bar + line for per-day, table for countries, link to per-animation detail. Uses Inertia props for SSR-friendly initial load.

### 9.8 Geolocation refresh job

`RefreshGeolocationDatabaseJob` (monthly, day 1, 05:00):

```php
public function handle(): void
{
    $key = config('cas.geolite_license_key');
    if ($key === null || $key === '') return; // skip if no license configured

    $url = "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key={$key}&suffix=tar.gz";
    // ... download, extract, atomic-replace the .mmdb at config('cas.geolite_db_path')
}
```

Schedule:

```php
Schedule::job(new RefreshGeolocationDatabaseJob)->monthlyOn(1, '05:00');
```

### 9.9 Tests

- `EnsureAnonTokenMiddlewareTest`: sets cookie when missing; preserves on return; HttpOnly + SameSite asserted
- `RecordAnimationUsageListenerTest`:
  - Within cooldown → no row inserted
  - Outside cooldown → row inserted with geo
  - Concurrent dispatch → only one row (lock test using `Cache::lock` spy)
- `AnimationUsageObserverTest`: creates a row from a console context, geo backfilled
- `AnimationStatsControllerTest`: seed via factory, assert response shape and aggregations
- `RefreshGeolocationDatabaseJobTest`: skipped when no license key; downloads + replaces when key present (mocked HTTP)
- `GeolocationServiceTest`: private IPs return unknown; public IPs use Reader; `Reader` exception falls back to unknown

## Quality gates

- [ ] All tests green
- [ ] Manual: hit `/sk/pendulum`, run sim, check Horizon — `RecordAnimationUsageListener` ran
- [ ] Manual: rapid-fire 5 sims → 1 row in `animation_usages` (cooldown working)
- [ ] Manual: clear cookie, run sim again → 1 new row (different anon_token)
- [ ] `/sk/stats` page renders without errors against seeded data

## Risks

| Risk | Mitigation |
|---|---|
| Cooldown race conditions | Redis `Cache::lock` + DB read (both) |
| Listener failures lose stats | `$tries = 3`; failures visible in Horizon |
| GeoLite2 DB missing in image | Bake an initial copy into the image; refresh job updates monthly |
| Privacy / GDPR | Anon token only, IP hashed in logs (Phase 03), no PII stored |

## Hand-off to next phase

Phase 10 needs: `EnsureAnonTokenMiddleware` registered correctly; rate-limiting strategy decisions on stats endpoints (should they require API key? — yes, keep them under `api-protected`).

## Agent brief (copy-paste)

> Read `CLAUDE.md` § 5, `docs/ARCHITECTURE.md` §§ 6, 8, 12, and this phase markdown.
>
> Build:
> - `animation_usages` migration with the cooldown index
> - `EnsureAnonTokenMiddleware` (HttpOnly, SameSite=Lax, 1y) applied to `/api/v1/simulations/*`
> - `GeolocationService` + `GeolocationResult` DTO + container binding for `Reader`
> - `RecordAnimationUsageListener` (queued, with `Cache::lock` for race safety + DB exists() for cooldown)
> - `AnimationUsageObserver` backfilling geo as defence in depth
> - `AnimationStatsController` + `AnimationStatsDetailController` + Resources
> - Stats page in React with Chart.js
> - `RefreshGeolocationDatabaseJob`, scheduled monthly, no-op without license key
>
> Wire Phases 06 and 07 simulation actions to read `request()->attributes->get('anon_token')` when dispatching `SimulationStarted`.
>
> Tests: cooldown enforced, concurrent dispatches single-row, observer backfills, stats endpoint shape.
>
> PR labelled `phase:09`.
