<?php

declare(strict_types=1);

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\ContentSecurityPolicyMiddleware;
use App\Http\Middleware\EnsureAnonTokenMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogRequestMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SetLocale;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Security headers run on every response — web and API alike.
        // They are appended to the global stack so they execute last,
        // after all other middleware, and cannot be overridden downstream.
        $middleware->append(SecurityHeadersMiddleware::class);
        $middleware->append(ContentSecurityPolicyMiddleware::class);

        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'api-key' => ApiKeyMiddleware::class,
            'set-locale' => SetLocale::class,
        ]);

        // The per-API-key rate-limiter buckets read $request->attributes->get('api_key')
        // which is set by ApiKeyMiddleware.  Laravel's default priority list places
        // ThrottleRequests before any custom auth middleware, so we bump ApiKeyMiddleware
        // ahead of ThrottleRequests to guarantee it populates the attribute first.
        $middleware->prependToPriorityList(
            ThrottleRequests::class,
            ApiKeyMiddleware::class,
        );

        // Order matters: LogRequestMiddleware first so failed-auth requests
        // (rejected by ApiKeyMiddleware with 401) still produce a RequestLog
        // row — every call must be logged. Session-stack middleware runs
        // after auth so the per-browser console session cookie persists for
        // the Octave console feature.
        $middleware->group('api-protected', [
            LogRequestMiddleware::class,
            ApiKeyMiddleware::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            'throttle:cas-api',
        ]);

        // Extends api-protected with the anonymous-token cookie middleware
        // needed by the simulation routes for stats cooldown.
        $middleware->group('api-simulation', [
            LogRequestMiddleware::class,
            ApiKeyMiddleware::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            EnsureAnonTokenMiddleware::class,
            'throttle:cas-api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render Inertia's NotFound page for all 404s that arrive via a
        // web (HTML) request. API 404s keep the default JSON response.
        $exceptions->render(function (NotFoundHttpException $_e, Request $request) {
            if ($request->expectsJson()) {
                return null; // Let Laravel produce the default JSON 404
            }

            return Inertia::render('NotFound')
                ->toResponse($request)
                ->setStatusCode(404);
        });
    })->create();
