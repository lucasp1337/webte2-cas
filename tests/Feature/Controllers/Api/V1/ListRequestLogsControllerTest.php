<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\RequestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

/**
 * @return array{0: ApiKey, 1: string}
 */
function makeListApiKey(string $suffix): array
{
    $plaintext = 'webte2_'.str_pad($suffix, 48, 'x');
    $apiKey = ApiKey::create(['name' => "test-{$suffix}", 'key_hash' => $plaintext]);

    return [$apiKey, $plaintext];
}

it('returns paginated logs for a valid api key', function (): void {
    [$apiKey, $plaintext] = makeListApiKey('paginate0');

    RequestLog::factory()->count(60)->create(['api_key_id' => $apiKey->id]);

    $response = getJson('/api/v1/logs', ['X-API-Key' => $plaintext]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [['request_id', 'method', 'route', 'status', 'duration_ms', 'created_at']],
            'meta',
            'links',
        ]);

    /** @var array<mixed> $data */
    $data = $response->json('data');
    expect(count($data))->toBe(50)
        ->and($response->json('meta.total'))->toBe(60);
});

it('filters by api_key_id', function (): void {
    [$apiKey, $plaintext] = makeListApiKey('aaafilterk');
    [$otherKey] = makeListApiKey('bbbfilterk');

    RequestLog::factory()->count(3)->create(['api_key_id' => $apiKey->id]);
    RequestLog::factory()->count(5)->create(['api_key_id' => $otherKey->id]);

    $response = getJson('/api/v1/logs?api_key_id='.$apiKey->id, ['X-API-Key' => $plaintext]);

    $response->assertStatus(200);
    expect($response->json('meta.total'))->toBe(3);
});

it('filters by route', function (): void {
    [$apiKey, $plaintext] = makeListApiKey('cccfilterr');

    RequestLog::factory()->count(2)->create([
        'api_key_id' => $apiKey->id,
        'route' => 'v1.octave.exec',
        'path' => '/api/v1/octave/exec',
    ]);
    RequestLog::factory()->count(4)->create([
        'api_key_id' => $apiKey->id,
        'route' => 'v1.logs.index',
        'path' => '/api/v1/logs',
    ]);

    $response = getJson('/api/v1/logs?route=v1.octave.exec', ['X-API-Key' => $plaintext]);

    $response->assertStatus(200);
    expect($response->json('meta.total'))->toBe(2);
});

it('filters by status', function (): void {
    [$apiKey, $plaintext] = makeListApiKey('dddfilters');

    RequestLog::factory()->count(3)->create(['api_key_id' => $apiKey->id, 'status' => 200, 'success' => true]);
    RequestLog::factory()->count(4)->create(['api_key_id' => $apiKey->id, 'status' => 422, 'success' => false]);

    $response = getJson('/api/v1/logs?status=200', ['X-API-Key' => $plaintext]);

    $response->assertStatus(200);
    expect($response->json('meta.total'))->toBe(3);
});

it('filters by date range', function (): void {
    [$apiKey, $plaintext] = makeListApiKey('eeefilterdt');

    RequestLog::factory()->count(2)->create([
        'api_key_id' => $apiKey->id,
        'created_at' => now()->subDays(10),
    ]);
    RequestLog::factory()->count(3)->create([
        'api_key_id' => $apiKey->id,
        'created_at' => now()->subDay(),
    ]);

    $from = now()->subDays(3)->toDateString();
    $to = now()->toDateString();

    $response = getJson("/api/v1/logs?from={$from}&to={$to}", ['X-API-Key' => $plaintext]);

    $response->assertStatus(200);
    expect($response->json('meta.total'))->toBe(3);
});

it('returns 422 for invalid status code', function (): void {
    [, $plaintext] = makeListApiKey('fff422sta');

    getJson('/api/v1/logs?status=99', ['X-API-Key' => $plaintext])
        ->assertStatus(422);
});

it('returns 422 for non-ulid api_key_id', function (): void {
    [, $plaintext] = makeListApiKey('gggulid0');

    getJson('/api/v1/logs?api_key_id=not-a-ulid', ['X-API-Key' => $plaintext])
        ->assertStatus(422);
});
