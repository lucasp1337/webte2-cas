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

        $middleware->group('api-protected', [
            ApiKeyMiddleware::class,
            LogRequestMiddleware::class,
            'throttle:cas-api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
