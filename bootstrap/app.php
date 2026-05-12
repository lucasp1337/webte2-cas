<?php

declare(strict_types=1);

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\EnsureAnonTokenMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogRequestMiddleware;
use App\Http\Middleware\SetLocale;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'api-key' => ApiKeyMiddleware::class,
            'set-locale' => SetLocale::class,
        ]);

        // Order matters: LogRequestMiddleware first so failed-auth requests
        // (rejected by ApiKeyMiddleware with 401) still produce a RequestLog
        // row — phase 03 DoD requires "every call is logged". Session-stack
        // middleware runs after auth so the per-browser console session
        // cookie persists for the Octave console feature (Phase 05).
        $middleware->group('api-protected', [
            LogRequestMiddleware::class,
            ApiKeyMiddleware::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            'throttle:cas-api',
        ]);

        // Extends api-protected with the anonymous-token cookie middleware
        // needed by the simulation routes for phase-09 stats cooldown.
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
