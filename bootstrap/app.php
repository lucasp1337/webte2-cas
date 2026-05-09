<?php

declare(strict_types=1);

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogRequestMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'api-key' => ApiKeyMiddleware::class,
        ]);

        // Order matters: LogRequestMiddleware first so failed-auth requests
        // (rejected by ApiKeyMiddleware with 401) still produce a RequestLog
        // row — phase 03 DoD requires "every call is logged". The log
        // middleware tolerates `api_key` being unset on the request and
        // emits an X-Request-Id header for both branches.
        $middleware->group('api-protected', [
            LogRequestMiddleware::class,
            ApiKeyMiddleware::class,
            'throttle:cas-api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
