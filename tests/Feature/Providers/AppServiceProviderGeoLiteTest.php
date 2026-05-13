<?php

declare(strict_types=1);

use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\Log;

it('returns null without logging when geolite db is missing in local env', function (): void {
    // In the testing environment the missing-db branch must not emit any log.
    // shouldReceive with never() acts as a strict expectation: no call allowed.
    Log::shouldReceive('warning')->never();

    config(['cas.geolite_db_path' => '/tmp/does-not-exist-webte2-test.mmdb']);

    app()->forgetInstance(Reader::class);
    $reader = app(Reader::class);

    expect($reader)->toBeNull();
});

it('returns null and logs a warning when geolite db is missing in production env', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message): bool {
            return str_contains($message, 'GeoLite2-City.mmdb missing at');
        });

    config(['cas.geolite_db_path' => '/tmp/does-not-exist-webte2-test.mmdb']);

    // Temporarily switch the environment so the production branch fires.
    app()->detectEnvironment(fn (): string => 'production');

    app()->forgetInstance(Reader::class);
    $reader = app(Reader::class);

    expect($reader)->toBeNull();
})->after(function (): void {
    // Restore environment so subsequent tests are unaffected.
    app()->detectEnvironment(fn (): string => 'testing');
});
