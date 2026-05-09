<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Octave\HttpOctaveBridgeClient;
use App\Services\Octave\OctaveBridgeClient;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OctaveBridgeClient::class, function (): HttpOctaveBridgeClient {
            /** @var string $baseUrl */
            $baseUrl = config('cas.octave_bridge_url', 'http://octave-bridge:8001');
            /** @var int $timeoutSeconds */
            $timeoutSeconds = config('cas.octave_bridge_timeout_seconds', 10);

            return new HttpOctaveBridgeClient(
                baseUrl: $baseUrl,
                timeoutSeconds: $timeoutSeconds,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
