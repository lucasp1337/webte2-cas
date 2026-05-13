<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AnimationUsage;
use App\Models\ApiKey;
use App\Models\RequestLog;
use App\Observers\AnimationUsageObserver;
use App\Observers\ApiKeyObserver;
use App\Observers\RequestLogObserver;
use App\Services\Octave\HttpOctaveBridgeClient;
use App\Services\Octave\OctaveBridgeClient;
use App\Services\Pdf\BrowsershotPdfRenderer;
use App\Services\Pdf\PdfRenderer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use GeoIp2\Database\Reader;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->app->bind(PdfRenderer::class, function (): BrowsershotPdfRenderer {
            /** @var string $chromePath */
            $chromePath = config('cas.browsershot_chrome_path', '/usr/bin/chromium');

            return new BrowsershotPdfRenderer($chromePath);
        });

        $this->app->bind(Reader::class, function (): ?Reader {
            $path = config('cas.geolite_db_path', '');

            if (! is_string($path) || $path === '') {
                return null;
            }

            if (! is_file($path)) {
                if (app()->environment('production')) {
                    Log::warning(
                        'GeoLite2-City.mmdb missing at {path}; geolocation will return unknown for all lookups',
                        ['path' => $path],
                    );
                }

                return null;
            }

            return new Reader($path);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        AnimationUsage::observe(AnimationUsageObserver::class);
        ApiKey::observe(ApiKeyObserver::class);
        RequestLog::observe(RequestLogObserver::class);

        Scramble::extendOpenApi(function (OpenApi $openApi, OpenApiContext $context): void {
            /** @var SecurityScheme $apiKeyScheme */
            $apiKeyScheme = SecurityScheme::apiKey('header', 'X-API-Key');
            /** @var SecurityScheme $namedScheme */
            $namedScheme = $apiKeyScheme->as('ApiKeyAuth');
            $openApi->secure($namedScheme);
        });

        RateLimiter::for('cas-api', function (Request $request): Limit {
            /** @var ApiKey|null $apiKey */
            $apiKey = $request->attributes->get('api_key');
            /** @var int $perMinute */
            $perMinute = config('cas.cas_rate_limit_per_minute', 30);

            $key = $apiKey !== null ? $apiKey->id : $request->ip();

            return Limit::perMinute($perMinute)->by($key);
        });

        RateLimiter::for('octave-exec', function (Request $request): Limit {
            /** @var ApiKey|null $apiKey */
            $apiKey = $request->attributes->get('api_key');
            /** @var int $perMinute */
            $perMinute = config('cas.cas_rate_limit_per_minute', 30);

            return $apiKey !== null
                ? Limit::perMinute($perMinute)->by("api-key:{$apiKey->id}")
                : Limit::perMinute(10)->by($request->ip());
        });
    }
}
