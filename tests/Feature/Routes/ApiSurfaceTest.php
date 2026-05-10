<?php

declare(strict_types=1);

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('GET /api/v1/health is public and returns 200 with all-ok shape', function (): void {
    Http::fake([
        '*' => Http::response(['status' => 'ok'], 200),
    ]);

    getJson('/api/v1/health')
        ->assertStatus(200)
        ->assertJsonStructure(['status', 'dependencies' => ['mysql', 'redis', 'octave_bridge']])
        ->assertJsonPath('status', 'ok');
});

it('all v1 protected routes return 401 without X-API-Key', function (): void {
    getJson('/api/v1/logs')->assertStatus(401);
    getJson('/api/v1/logs/export.csv')->assertStatus(401);
    getJson('/api/v1/stats')->assertStatus(401);
    getJson('/api/v1/stats/pendulum')->assertStatus(401);
    postJson('/api/v1/octave/exec')->assertStatus(401);
    deleteJson('/api/v1/octave/session')->assertStatus(401);
    postJson('/api/v1/simulations/pendulum')->assertStatus(401);
    postJson('/api/v1/simulations/ball-beam')->assertStatus(401);
});

it('POST /api/v1/api-docs/pdf is public and returns 202', function (): void {
    // The api-docs PDF endpoints parallel /api/openapi.json — both are public.
    // The page itself is anonymous and ships no API key to the browser.
    postJson('/api/v1/api-docs/pdf')->assertStatus(202);
});

it('all v1 protected routes return non-5xx with valid X-API-Key', function (): void {
    Http::fake([
        '*' => Http::response(['status' => 'ok'], 200),
    ]);

    $plaintext = 'webte2_'.str_repeat('a', 48);
    ApiKey::create(['name' => 'surface-test', 'key_hash' => $plaintext]);

    $headers = ['X-API-Key' => $plaintext];

    getJson('/api/v1/logs', $headers)->assertSuccessful();
    getJson('/api/v1/logs/export.csv', $headers)->assertSuccessful();
    getJson('/api/v1/stats', $headers)->assertSuccessful();
    getJson('/api/v1/stats/pendulum', $headers)->assertSuccessful();
});
