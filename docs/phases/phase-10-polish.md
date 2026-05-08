# Phase 10 — Polish, security, cross-browser, Horizon gate

**Duration**: 1.5 d
**Tier**: any (senior eye on the security checklist)
**Required reading**: `CLAUDE.md` § 8, `docs/ARCHITECTURE.md` § 5

## Goal

Take the working app from "demo-able" to "submittable". Lock down security, add empty/loading/error states everywhere, run the cross-browser pass, gate Horizon, optionally add a 3D renderer if time allows.

## Definition of Done

- [ ] `Content-Security-Policy` middleware applied with per-route exceptions
- [ ] Security headers: HSTS (prod only), X-Content-Type-Options, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy
- [ ] Rate limit on `/api/v1/octave/exec` per API key (30/min default, env-configurable)
- [ ] Horizon dashboard gated: `HORIZON_ADMIN_TOKEN` env var compared in `HorizonServiceProvider::gate()`
- [ ] Telescope disabled in production
- [ ] Empty / loading / error states exist for: console, pendulum, ball-beam, logs, stats, api-docs
- [ ] DemoSeeder produces a usable demo state (one API key, ~50 request logs, ~100 animation usages)
- [ ] Manual cross-browser pass: Chrome, Firefox, Safari (or WebKit via Playwright). Mobile viewport 375 px ✓
- [ ] Security checklist (below) walked end to end and signed off in the PR description
- [ ] *(Optional, if budget allows)* Pendulum3D + BallBeam3D renderers behind a UI toggle

## Prerequisites

Phases 03–09 complete and merged.

## Tasks

### 10.1 CSP middleware

`app/Http/Middleware/ContentSecurityPolicyMiddleware.php`:

```php
final class ContentSecurityPolicyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $route = $request->route()?->getName();

        $relaxStyle = in_array($route, ['api-docs', 'console'], true);

        $directives = [
            "default-src 'self'",
            "script-src 'self'" . ($relaxStyle ? " 'unsafe-inline'" : ''),
            "style-src 'self'" . ($relaxStyle ? " 'unsafe-inline'" : " 'unsafe-inline'"), // Tailwind injects inline; tighten later
            "img-src 'self' data:",
            "connect-src 'self'",
            "font-src 'self' data:",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $directives));
        return $response;
    }
}
```

The plan tightens `style-src` to nonces only as a follow-up after submission.

### 10.2 Security headers

`SecurityHeadersMiddleware`:

```php
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('X-Frame-Options', 'DENY');
$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
$response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
if (app()->environment('production')) {
    $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
}
```

Apply both globally in `bootstrap/app.php`.

### 10.3 Rate limiting

`app/Providers/AppServiceProvider.php` `boot()`:

```php
RateLimiter::for('octave-exec', function (Request $request) {
    $apiKey = $request->attributes->get('api_key');
    $limit = (int) config('cas.rate_limit_per_minute', 30);
    return $apiKey !== null
        ? Limit::perMinute($limit)->by("api-key:{$apiKey->id}")
        : Limit::perMinute(10)->by($request->ip());
});
```

Apply to the exec route:

```php
Route::post('/octave/exec', ExecuteOctaveCommandController::class)
    ->middleware('throttle:octave-exec')
    ->name('octave.exec');
```

### 10.4 Horizon gate

`app/Providers/HorizonServiceProvider.php`:

```php
protected function gate(): void
{
    Gate::define('viewHorizon', function ($user = null) {
        $expected = (string) env('HORIZON_ADMIN_TOKEN', '');
        if ($expected === '') return false;
        $supplied = (string) request()->query('token', '');
        return hash_equals($expected, $supplied);
    });
}
```

Document in README: `https://your-host/horizon?token=$HORIZON_ADMIN_TOKEN`.

### 10.5 Telescope hardening

`config/telescope.php` — `enabled` driven by env, default `false` in production. `TelescopeServiceProvider::register()` short-circuits if not enabled.

### 10.6 Empty / loading / error states

For each page, ensure:

- **Empty** — clear visual, helpful CTA. E.g., logs page: "No requests yet — try the console or run a simulation"
- **Loading** — `<Skeleton>` matching the layout. No layout shift on load
- **Error** — error card with the message + a "Retry" button. No raw stack traces

A small `<AsyncBoundary>` component standardises this:

```typescript
type AsyncBoundaryProps<T> = {
  state: { status: 'idle' | 'loading' | 'success' | 'error'; data?: T; error?: string };
  empty?: ReactNode;
  loading?: ReactNode;
  error?: (msg: string) => ReactNode;
  children: (data: T) => ReactNode;
};
```

### 10.7 Demo seeder

`database/seeders/DemoSeeder.php`:

```php
public function run(): void
{
    $apiKey = ApiKey::factory()->create([
        'name' => 'Demo',
        'key_hash' => env('CAS_API_KEY_PLAINTEXT', 'webte2_demo_' . Str::random(40)),
    ]);

    RequestLog::factory()->count(50)->forApiKey($apiKey)->create();

    AnimationUsage::factory()->count(60)->pendulum()->create();
    AnimationUsage::factory()->count(40)->ballBeam()->create();
}
```

Idempotent: run multiple times safely.

### 10.8 Cross-browser pass

Open every page in Chrome, Firefox, Safari (or WebKit via Playwright). Walk:

- Language switch SK ↔ EN on every page
- Theme toggle on every page
- Submit a command in the console
- Run both simulations end to end
- Download CSV
- Download PDF
- Stats page renders

Mobile viewport 375 px: nav collapses to hamburger; forms usable; charts shrink without overflowing.

### 10.9 Security review checklist

Walk before opening the PR. Sign each off in the PR description.

- [ ] Octave bridge: `cap_drop: ALL`, no network egress, ulimits set, sandbox blocklist active
- [ ] API keys hashed in DB; `hash_equals` for comparison; never logged
- [ ] CSP applied; tightest possible per route
- [ ] Security headers present on every response
- [ ] Rate limit on `/api/v1/octave/exec` per API key
- [ ] Horizon gated by `HORIZON_ADMIN_TOKEN`
- [ ] Telescope disabled in production
- [ ] Anon token cookie HttpOnly + SameSite=Lax + Secure (in prod)
- [ ] No `dd()` / `dump()` / `console.log()` in committed code
- [ ] Octave commands truncated to 1024 chars in logs
- [ ] Form Requests validate every endpoint
- [ ] Custom rules enforce physical bounds for simulations
- [ ] CSRF active on all stateful HTML routes; API routes use API key
- [ ] Sessions stored in Redis (no file-system cross-contamination between containers)
- [ ] No secrets committed (`.env` gitignored; `.env.example` clean)

### 10.10 Optional: 3D renderers

If time remains (hard 4-hour budget):

```typescript
export const Pendulum3D: AnimationRenderer<PendulumFrame> = ({ trajectory, frameIndex, width, height }) => {
  // Three.js scene: cart on a rail, pendulum rod and bob
  // Same prop signature as Pendulum2D — drop-in
};
```

UI toggle on the page swaps between `Pendulum2D` and `Pendulum3D`. Same for ball-beam. If 3D doesn't fit the budget, **don't merge a half-baked version** — leave it for follow-up.

## Quality gates

- [ ] `composer qa` + `npm run qa` green
- [ ] All security checklist items checked in PR description
- [ ] Cross-browser smoke pass documented in PR
- [ ] CSP report-only mode shows no unexpected blocks during a full walkthrough
- [ ] Horizon dashboard reachable only with valid token

## Risks

| Risk | Mitigation |
|---|---|
| CSP breaking Swagger UI / CodeMirror | Per-route relaxation only on `/api-docs` and `/console` |
| Tailwind 4 inline styles | Document the trade-off; tightening to nonces is post-submission |
| Rate limiter too tight in demo | Default 30/min; configurable via `CAS_RATE_LIMIT_PER_MINUTE` |

## Hand-off to next phase

Phase 11 needs: a stable, polished build of the app to record the video against. After this phase, no behavioural changes during the video recording phase.

## Agent brief (copy-paste)

> Read `CLAUDE.md` § 8 and this phase markdown.
>
> Build:
> - `ContentSecurityPolicyMiddleware` with per-route exceptions for `/api-docs` and `/console`
> - `SecurityHeadersMiddleware`
> - Rate limiter `octave-exec` per API key, applied to the exec route
> - `HorizonServiceProvider::gate()` using `HORIZON_ADMIN_TOKEN` and `hash_equals`
> - `AsyncBoundary` component + empty/loading/error states for every page
> - `DemoSeeder`
> - Telescope disabled in production
>
> Walk the security checklist (§ 10.9) and sign each item in the PR description. Run a cross-browser pass; attach screenshots of each browser/page to the PR.
>
> 3D renderers are optional. Don't merge a half-implementation. Hard budget: 4 hours.
>
> PR labelled `phase:10`.
