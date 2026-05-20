<?php

declare(strict_types=1);

use App\Models\AnimationUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();

    // Fake ip-api.com so the real GeolocationService maps a predictable result.
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'country' => 'Germany',
            'countryCode' => 'DE',
            'city' => 'Berlin',
        ]),
    ]);
});

describe('AnimationUsageObserver', function (): void {
    it('backfills geo when columns are null and a request IP is available', function (): void {
        // Bind a synthetic request with a real IP so request()->ip() returns '8.8.8.8'.
        $syntheticRequest = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        app()->instance('request', $syntheticRequest);

        $usage = AnimationUsage::create([
            'animation' => 'pendulum',
            'anon_token' => (string) Str::uuid(),
            'started_at' => now(),
            'country_iso' => null,
            'country' => null,
            'city' => null,
        ]);

        expect($usage->country_iso)->toBe('DE')
            ->and($usage->country)->toBe('Germany')
            ->and($usage->city)->toBe('Berlin');
    });

    it('does not overwrite already-set geo columns', function (): void {
        $usage = AnimationUsage::create([
            'animation' => 'pendulum',
            'anon_token' => (string) Str::uuid(),
            'started_at' => now(),
            'country_iso' => 'SK',
            'country' => 'Slovakia',
            'city' => 'Bratislava',
        ]);

        expect($usage->country_iso)->toBe('SK')
            ->and($usage->country)->toBe('Slovakia')
            ->and($usage->city)->toBe('Bratislava');
    });

    it('does not look up geo when ip_address context is absent', function (): void {
        // No HTTP request in flight — request()->ip() returns null in queue/console context.
        $usage = AnimationUsage::create([
            'animation' => 'pendulum',
            'anon_token' => (string) Str::uuid(),
            'started_at' => now(),
            'country_iso' => null,
            'country' => null,
            'city' => null,
        ]);

        // Outside an HTTP request the observer should not backfill.
        expect($usage->country_iso)->toBeNull()
            ->and($usage->country)->toBeNull()
            ->and($usage->city)->toBeNull();
    });

    it('does not touch geo when only country_iso is already set', function (): void {
        // If even one geo column is non-null the observer short-circuits.
        $usage = AnimationUsage::create([
            'animation' => 'ball-beam',
            'anon_token' => (string) Str::uuid(),
            'started_at' => now(),
            'country_iso' => 'US',
            'country' => null,
            'city' => null,
        ]);

        expect($usage->country_iso)->toBe('US')
            ->and($usage->country)->toBeNull()
            ->and($usage->city)->toBeNull();
    });
});
