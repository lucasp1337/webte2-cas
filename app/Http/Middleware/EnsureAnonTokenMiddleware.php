<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures every simulation request carries a stable anonymous token cookie.
 *
 * The token is a UUID v4 stored in an HttpOnly, SameSite=Lax cookie valid for
 * one year. It is used by the `RecordAnimationUsageListener` to implement
 * the cooldown deduplication logic without any user account.
 *
 * The plain UUID is attached to `$request->attributes` so downstream code
 * (the action) can read it synchronously without re-parsing the cookie.
 *
 * Security notes:
 *  - The cookie is HttpOnly, so JavaScript cannot read or forge it.
 *  - Secure flag is set in all non-local environments (HTTPS only).
 *  - SameSite=Lax prevents cross-site delivery while allowing top-level nav.
 *  - The value is a UUID — not a session token; compromise leaks only
 *    simulation frequency stats, not authentication.
 */
final class EnsureAnonTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $existing = $request->cookie('anon_token');
        $isValid = is_string($existing)
            && $existing !== ''
            && Str::isUuid($existing);

        $token = $isValid ? (string) $existing : (string) Str::uuid();

        // Expose via request attributes for direct reading in controllers/actions.
        $request->attributes->set('anon_token', $token);

        /** @var Response $response */
        $response = $next($request);

        if (! $isValid) {
            $cookie = cookie(
                name: 'anon_token',
                value: $token,
                minutes: 60 * 24 * 365,
                path: '/',
                domain: null,
                secure: ! app()->environment('local'),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            );

            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
