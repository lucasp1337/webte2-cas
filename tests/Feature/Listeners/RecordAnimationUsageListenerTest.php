<?php

declare(strict_types=1);

use App\Enums\AnimationName;
use App\Events\SimulationStarted;
use App\Listeners\RecordAnimationUsageListener;
use App\Models\AnimationUsage;
use App\Services\GeolocationService;
use Carbon\CarbonImmutable;
use GeoIp2\Database\Reader;
use GeoIp2\Model\City;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery\Expectation;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/**
 * Builds a SimulationStarted event with a predictable anon token and IP.
 */
function makeSimEvent(
    string $token,
    AnimationName $animation = AnimationName::Pendulum,
    string $ip = '8.8.8.8',
): SimulationStarted {
    return new SimulationStarted($animation, $token, $ip, md5('params'));
}

/**
 * Returns a GeolocationService whose lookup always returns DE/Germany/Berlin.
 */
function fakeGeoService(): GeolocationService
{
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
}

beforeEach(function (): void {
    Cache::flush();
    CarbonImmutable::setTestNow(CarbonImmutable::now());
});

afterEach(function (): void {
    CarbonImmutable::setTestNow(null);
});

describe('RecordAnimationUsageListener', function (): void {
    it('implements ShouldQueue so it is processed off the request thread', function (): void {
        expect(RecordAnimationUsageListener::class)
            ->toImplement(ShouldQueue::class);
    });

    it('creates a row on the first event for an anon token', function (): void {
        $listener = new RecordAnimationUsageListener(fakeGeoService());
        $token = (string) Str::uuid();

        $listener->handle(makeSimEvent($token));

        expect(AnimationUsage::query()->where('anon_token', $token)->count())->toBe(1);
    });

    it('populates geo columns from the GeolocationService', function (): void {
        $listener = new RecordAnimationUsageListener(fakeGeoService());
        $token = (string) Str::uuid();

        $listener->handle(makeSimEvent($token));

        /** @var AnimationUsage|null $row */
        $row = AnimationUsage::query()->where('anon_token', $token)->first();

        expect($row)->not->toBeNull();

        /** @var AnimationUsage $row */
        expect($row->country_iso)->toBe('DE')
            ->and($row->country)->toBe('Germany')
            ->and($row->city)->toBe('Berlin');
    });

    it('does not create a row within the cooldown window', function (): void {
        $token = (string) Str::uuid();

        // Seed a row that is within the default 10-minute cooldown window.
        AnimationUsage::factory()->pendulum()->create([
            'anon_token' => $token,
            'started_at' => now()->subMinutes(5),
        ]);

        $listener = new RecordAnimationUsageListener(fakeGeoService());
        $listener->handle(makeSimEvent($token));

        expect(AnimationUsage::query()->where('anon_token', $token)->count())->toBe(1);
    });

    it('creates a row when the previous record is outside the cooldown window', function (): void {
        $token = (string) Str::uuid();

        // Seed a row that is outside the default 10-minute cooldown (15 minutes ago).
        AnimationUsage::factory()->pendulum()->create([
            'anon_token' => $token,
            'started_at' => now()->subMinutes(15),
        ]);

        $listener = new RecordAnimationUsageListener(fakeGeoService());
        $listener->handle(makeSimEvent($token));

        expect(AnimationUsage::query()->where('anon_token', $token)->count())->toBe(2);
    });

    it('respects the cas.stats_cooldown_minutes config value', function (): void {
        config(['cas.stats_cooldown_minutes' => 5]);
        $token = (string) Str::uuid();

        // 7 minutes ago is outside a 5-minute window — a new row should be created.
        AnimationUsage::factory()->pendulum()->create([
            'anon_token' => $token,
            'started_at' => now()->subMinutes(7),
        ]);

        $listener = new RecordAnimationUsageListener(fakeGeoService());
        $listener->handle(makeSimEvent($token));

        expect(AnimationUsage::query()->where('anon_token', $token)->count())->toBe(2);
    });

    it('treats different animations as independent cooldown buckets', function (): void {
        $token = (string) Str::uuid();

        // Seed a pendulum row within cooldown.
        AnimationUsage::factory()->pendulum()->create([
            'anon_token' => $token,
            'started_at' => now()->subMinutes(3),
        ]);

        // A ball-beam event for the same token should still create a row.
        $listener = new RecordAnimationUsageListener(fakeGeoService());
        $listener->handle(makeSimEvent($token, AnimationName::BallBeam));

        expect(
            AnimationUsage::query()
                ->where('anon_token', $token)
                ->where('animation', AnimationName::BallBeam->value)
                ->count()
        )->toBe(1);
    });

    it('only ever creates one row per cooldown window (idempotency)', function (): void {
        // Release any existing lock so both calls start fresh.
        Cache::flush();

        $token = (string) Str::uuid();
        $listener = new RecordAnimationUsageListener(fakeGeoService());
        $event = makeSimEvent($token);

        // Call handle twice synchronously — the second call should be
        // blocked by the Redis lock acquired by the first.
        $listener->handle($event);
        $listener->handle($event);

        expect(AnimationUsage::query()->where('anon_token', $token)->count())->toBe(1);
    });

    it('logs an error via the failed() callback', function (): void {
        Log::shouldReceive('error')
            ->once()
            ->with('animation-usage: failed to record usage', Mockery::type('array'));

        $listener = new RecordAnimationUsageListener(fakeGeoService());
        $event = makeSimEvent((string) Str::uuid());

        $listener->failed($event, new RuntimeException('boom'));
    });
});
