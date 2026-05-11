<?php

declare(strict_types=1);

use App\Jobs\RegenerateApiDocsCacheJob;
use Dedoc\Scramble\Generator;
use Illuminate\Support\Facades\Cache;

it('forgets the old spec and seeds the cache with a fresh one', function (): void {
    // Pre-seed a stale spec.
    Cache::put('openapi:spec:v1', ['stale' => true], now()->addHour());

    $job = new RegenerateApiDocsCacheJob;
    $job->handle(app(Generator::class));

    $cached = Cache::get('openapi:spec:v1');

    expect($cached)->not->toBeNull()
        ->and($cached)->not->toBe(['stale' => true]);
});

it('does not leave an empty cache entry on success', function (): void {
    Cache::forget('openapi:spec:v1');

    $job = new RegenerateApiDocsCacheJob;
    $job->handle(app(Generator::class));

    expect(Cache::has('openapi:spec:v1'))->toBeTrue();
});

it('failed() does not throw and logs gracefully', function (): void {
    $job = new RegenerateApiDocsCacheJob;

    // Should not throw.
    $job->failed(new RuntimeException('test failure'));

    expect(true)->toBeTrue();
});
