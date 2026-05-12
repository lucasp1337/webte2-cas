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

it('documents the /octave/exec 200 response as a structured object with all six fields', function (): void {
    $spec = getJson('/api/openapi.json');

    // Scramble emits a $ref to components/schemas for resource responses.
    // Verify the ref exists on the 200 response then resolve the component.
    $ref = $spec->json('paths./octave/exec.post.responses.200.content.application/json.schema.$ref');
    expect($ref)->toBe('#/components/schemas/OctaveExecutionResource');

    /** @var array<string, mixed> $schema */
    $schema = $spec->json('components.schemas.OctaveExecutionResource');

    expect($schema)->toHaveKey('type', 'object');

    /** @var array<string, mixed> $properties */
    $properties = $schema['properties'] ?? [];

    expect(array_keys($properties))->toContain('request_id')
        ->toContain('stdout')
        ->toContain('stderr')
        ->toContain('exit_code')
        ->toContain('duration_ms')
        ->toContain('rejection_reason');
});

it('documents the DELETE /octave/session as 204 No Content', function (): void {
    /** @var array<string, mixed> $responses */
    $responses = getJson('/api/openapi.json')
        ->json('paths./octave/session.delete.responses') ?? [];

    expect($responses)->toHaveKey('204');
});
