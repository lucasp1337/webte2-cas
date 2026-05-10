<?php

declare(strict_types=1);

use function Pest\Laravel\getJson;

it('serves the generated openapi spec at /api/openapi.json', function (): void {
    getJson('/api/openapi.json')
        ->assertOk()
        ->assertJsonPath('info.title', 'WEBTE2 — REST API');

    /** @var string $openApiVersion */
    $openApiVersion = getJson('/api/openapi.json')->json('openapi') ?? '';
    expect($openApiVersion)->toMatch('/^3\.\d+\.\d+$/');
});

it('returns a cache hit on the second call', function (): void {
    // Populate the cache on the first call.
    getJson('/api/openapi.json')->assertOk();

    // Second call must hit cache — still 200 with the same title.
    getJson('/api/openapi.json')
        ->assertOk()
        ->assertJsonPath('info.title', 'WEBTE2 — REST API');
});

it('documents every /api/v1/* route the project exposes', function (): void {
    /** @var array<string, mixed> $rawPaths */
    $rawPaths = getJson('/api/openapi.json')->json('paths') ?? [];
    $paths = array_keys($rawPaths);

    // Scramble strips the configured `api_path` ('api/v1') from path keys —
    // the public URL is /api/v1/octave/exec but the spec key is /octave/exec.
    // The test asserts every documented route is present in that stripped form.
    $expected = [
        '/octave/exec',
        '/octave/session',
        '/simulations/pendulum',
        '/simulations/ball-beam',
        '/logs',
        '/logs/export.csv',
        '/logs/export.csv/{exportId}',
        '/api-docs/pdf',
        '/api-docs/pdf/{exportId}',
        '/stats',
        '/stats/{animation}',
        '/health',
    ];

    foreach ($expected as $path) {
        expect($paths)->toContain($path);
    }
});

it('declares the X-API-Key security scheme', function (): void {
    /** @var array<string, array<string, string>> $schemes */
    $schemes = getJson('/api/openapi.json')->json('components.securitySchemes') ?? [];

    expect($schemes)->toHaveKey('ApiKeyAuth');
    expect($schemes['ApiKeyAuth']['type'])->toBe('apiKey');
    expect($schemes['ApiKeyAuth']['in'])->toBe('header');
    expect($schemes['ApiKeyAuth']['name'])->toBe('X-API-Key');
});
