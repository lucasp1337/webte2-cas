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
function makeAnimApiKey(string $suffix): array
{
    $plaintext = 'webte2_'.str_pad($suffix, 48, 'a');
    $apiKey = ApiKey::create(['name' => "anim-{$suffix}", 'key_hash' => $plaintext]);

    return [$apiKey, $plaintext];
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-11 12:00:00'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow(null);
});

describe('StatsForAnimationController', function (): void {
    // Guarantee a clean animation_usages table inside each test's transaction,
    // regardless of what earlier test files may have committed or left behind.
    beforeEach(function (): void {
        AnimationUsage::query()->delete();
    });

    it('returns 401 without an API key', function (): void {
        getJson('/api/v1/stats/pendulum')->assertStatus(401);
    });

    it('returns 404 for an unknown animation slug', function (): void {
        [, $key] = makeAnimApiKey('notfound0');

        getJson('/api/v1/stats/banana', ['X-API-Key' => $key])
            ->assertStatus(404);
    });

    it('returns the expected JSON structure for pendulum', function (): void {
        [, $key] = makeAnimApiKey('struct000');

        AnimationUsage::factory()->pendulum()->create([
            'started_at' => now()->subDays(2),
        ]);

        getJson('/api/v1/stats/pendulum', ['X-API-Key' => $key])
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'animation',
                    'totals',
                    'per_day',
                    'top_countries',
                    'top_cities',
                ],
            ]);
    });

    it('returns only pendulum counts and excludes ball-beam rows', function (): void {
        [, $key] = makeAnimApiKey('filter000');

        AnimationUsage::factory()->pendulum()->count(3)->create([
            'started_at' => now()->subDays(2),
        ]);
        AnimationUsage::factory()->ballBeam()->count(5)->create([
            'started_at' => now()->subDays(2),
        ]);

        $response = getJson('/api/v1/stats/pendulum', ['X-API-Key' => $key])
            ->assertStatus(200);

        expect($response->json('data.totals.pendulum'))->toBe(3)
            ->and($response->json('data.totals'))->not->toHaveKey('ball-beam');
    });

    it('excludes rows older than 30 days', function (): void {
        [, $key] = makeAnimApiKey('window000');

        AnimationUsage::factory()->pendulum()->create([
            'started_at' => now()->subDays(10),
        ]);
        AnimationUsage::factory()->pendulum()->create([
            'started_at' => now()->subDays(40),
        ]);

        $response = getJson('/api/v1/stats/pendulum', ['X-API-Key' => $key])
            ->assertStatus(200);

        expect($response->json('data.totals.pendulum'))->toBe(1);
    });

    it('top_cities excludes rows with null city', function (): void {
        [, $key] = makeAnimApiKey('cities000');

        AnimationUsage::factory()->pendulum()->count(2)->create([
            'started_at' => now()->subDays(1),
            'city' => 'Berlin',
            'country_iso' => 'DE',
            'country' => 'Germany',
        ]);
        AnimationUsage::factory()->pendulum()->count(3)->withoutGeo()->create([
            'started_at' => now()->subDays(1),
        ]);

        getJson('/api/v1/stats/pendulum', ['X-API-Key' => $key])
            ->assertStatus(200)
            ->assertJsonPath('data.top_cities.0.city', 'Berlin')
            ->assertJsonPath('data.top_cities.0.count', 2)
            ->assertJsonCount(1, 'data.top_cities');
    });

    it('returns empty arrays for a new animation with no data', function (): void {
        [, $key] = makeAnimApiKey('empty0000');

        getJson('/api/v1/stats/ball-beam', ['X-API-Key' => $key])
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.per_day')
            ->assertJsonCount(0, 'data.top_countries')
            ->assertJsonCount(0, 'data.top_cities');
    });

    it('per_day entries contain date, animation, and count fields', function (): void {
        [, $key] = makeAnimApiKey('perday000');

        AnimationUsage::factory()->ballBeam()->create([
            'started_at' => now()->subDays(1),
        ]);

        getJson('/api/v1/stats/ball-beam', ['X-API-Key' => $key])
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'per_day' => [
                        ['date', 'animation', 'count'],
                    ],
                ],
            ]);
    });
});
