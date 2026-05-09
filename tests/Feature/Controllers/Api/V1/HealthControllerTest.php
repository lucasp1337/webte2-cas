<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

use function Pest\Laravel\getJson;

it('returns 200 with all-ok shape when all dependencies are reachable', function (): void {
    Http::fake([
        '*' => Http::response(['status' => 'ok'], 200),
    ]);

    getJson('/api/v1/health')
        ->assertStatus(200)
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('dependencies.mysql', 'ok')
        ->assertJsonPath('dependencies.redis', 'ok')
        ->assertJsonPath('dependencies.octave_bridge', 'ok');
});

it('returns 503 with degraded shape when bridge is unreachable', function (): void {
    Http::fake([
        '*' => Http::response([], 503),
    ]);

    getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('dependencies.octave_bridge', 'down');
});
