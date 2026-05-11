<?php

declare(strict_types=1);

use App\Models\AnimationUsage;
use App\Services\GeolocationService;
use GeoIp2\Database\Reader;
use GeoIp2\Model\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery\Expectation;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Bind a fake GeolocationService that always returns a predictable result.
    app()->bind(GeolocationService::class, function (): GeolocationService {
        /** @var Reader&MockInterface $reader */
        $reader = Mockery::mock(Reader::class);
        $cityModel = new City([
            'country' => ['iso_code' => 'DE', 'names' => ['en' => 'Germany']],
            'city' => ['names' => ['en' => 'Berlin']],
            'traits' => ['ip_address' => '8.8.8.8'],
        ], ['en']);
        /** @var Expectation $exp */
        $exp = $reader->shouldReceive('city');
        $exp->andReturn($cityModel);

        return new GeolocationService($reader);
    });
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
