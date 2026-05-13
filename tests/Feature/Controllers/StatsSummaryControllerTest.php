<?php

declare(strict_types=1);

use App\Models\AnimationUsage;
use App\Models\ApiKey;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

/**
 * @return array{0: ApiKey, 1: string}
 */
function makeStatsApiKey(string $suffix): array
{
    $plaintext = 'webte2_'.str_pad($suffix, 48, 's');
    $apiKey = ApiKey::create(['name' => "stats-{$suffix}", 'key_hash' => $plaintext]);

    return [$apiKey, $plaintext];
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-11 12:00:00'));
    // See StatsForAnimationControllerTest for the rationale on this cleanup.
    AnimationUsage::query()->delete();
});

afterEach(function (): void {
    CarbonImmutable::setTestNow(null);
});

describe('StatsSummaryController', function (): void {
    it('returns 401 without an API key', function (): void {
        getJson('/api/v1/stats')->assertStatus(401);
    });

    it('returns the expected JSON structure on happy path', function (): void {
        [, $key] = makeStatsApiKey('structure0');

        AnimationUsage::factory()->pendulum()->count(3)->create([
            'started_at' => now()->subDays(5),
        ]);
        AnimationUsage::factory()->ballBeam()->count(2)->create([
            'started_at' => now()->subDays(3),
        ]);

        getJson('/api/v1/stats', ['X-API-Key' => $key])
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'totals',
                    'per_day',
                    'top_countries',
                ],
            ]);
    });

    it('returns correct total counts per animation', function (): void {
        [, $key] = makeStatsApiKey('totals000');

        AnimationUsage::factory()->pendulum()->count(4)->create([
            'started_at' => now()->subDays(2),
            'country_iso' => null,
            'country' => null,
            'city' => null,
        ]);
        AnimationUsage::factory()->ballBeam()->count(2)->create([
            'started_at' => now()->subDays(2),
            'country_iso' => null,
            'country' => null,
            'city' => null,
        ]);

        $response = getJson('/api/v1/stats', ['X-API-Key' => $key])
            ->assertStatus(200);

        expect($response->json('data.totals.pendulum'))->toBe(4)
            ->and($response->json('data.totals.ball-beam'))->toBe(2);
    });

    it('excludes rows older than 30 days from totals', function (): void {
        [, $key] = makeStatsApiKey('window000');

        // Row within the window.
        AnimationUsage::factory()->pendulum()->create([
            'started_at' => now()->subDays(15),
        ]);

        // Row outside the 30-day window.
        AnimationUsage::factory()->pendulum()->create([
            'started_at' => now()->subDays(31),
        ]);

        $response = getJson('/api/v1/stats', ['X-API-Key' => $key])
            ->assertStatus(200);

        expect($response->json('data.totals.pendulum'))->toBe(1);
    });

    it('returns empty structures when no rows exist', function (): void {
        [, $key] = makeStatsApiKey('empty0000');

        getJson('/api/v1/stats', ['X-API-Key' => $key])
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.totals')
            ->assertJsonCount(0, 'data.per_day')
            ->assertJsonCount(0, 'data.top_countries');
    });

    it('per_day entries contain date, animation, and count fields', function (): void {
        [, $key] = makeStatsApiKey('perday000');

        AnimationUsage::factory()->pendulum()->create([
            'started_at' => now()->subDays(1),
        ]);

        getJson('/api/v1/stats', ['X-API-Key' => $key])
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'per_day' => [
                        ['date', 'animation', 'count'],
                    ],
                ],
            ]);
    });

    it('top_countries contains rows with country_iso, country, and count', function (): void {
        [, $key] = makeStatsApiKey('topcou000');

        AnimationUsage::factory()->pendulum()->count(3)->create([
            'started_at' => now()->subDays(2),
            'country_iso' => 'DE',
            'country' => 'Germany',
        ]);

        getJson('/api/v1/stats', ['X-API-Key' => $key])
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'top_countries' => [
                        ['country_iso', 'country', 'count'],
                    ],
                ],
            ])
            ->assertJsonPath('data.top_countries.0.country_iso', 'DE')
            ->assertJsonPath('data.top_countries.0.count', 3);
    });

    it('top_countries excludes rows with null country_iso', function (): void {
        [, $key] = makeStatsApiKey('topcounull');

        AnimationUsage::factory()->pendulum()->count(2)->withoutGeo()->create([
            'started_at' => now()->subDays(1),
        ]);

        getJson('/api/v1/stats', ['X-API-Key' => $key])
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.top_countries');
    });
});
