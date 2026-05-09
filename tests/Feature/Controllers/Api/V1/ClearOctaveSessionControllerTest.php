<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\ExecuteOctaveCommandRequest;
use App\Models\ApiKey;
use App\Services\Octave\OctaveBridgeClient;
use App\Services\Octave\Testing\FakeOctaveBridgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\withSession;

uses(RefreshDatabase::class);

/** @return array{0: ApiKey, 1: string} */
function makeClearApiKey(string $suffix): array
{
    $plaintext = 'webte2_'.str_pad($suffix, 48, 'x');
    $apiKey = ApiKey::create(['name' => "clear-{$suffix}", 'key_hash' => $plaintext]);

    return [$apiKey, $plaintext];
}

function bindClearFakeBridge(): FakeOctaveBridgeClient
{
    $fake = new FakeOctaveBridgeClient;
    app()->instance(OctaveBridgeClient::class, $fake);

    return $fake;
}

it('returns 204 even when no session has been started', function (): void {
    [, $plaintext] = makeClearApiKey('clearnone0');
    $fake = bindClearFakeBridge();

    deleteJson('/api/v1/octave/session', [], ['X-API-Key' => $plaintext])
        ->assertStatus(204);

    $clears = array_filter($fake->calls, fn (array $call): bool => $call['method'] === 'clearSession');
    expect($clears)->toBeEmpty();
});

it('clears the active session and forwards the id to the bridge', function (): void {
    [, $plaintext] = makeClearApiKey('clearuse00');
    $fake = bindClearFakeBridge();

    // Pre-seed the Laravel session with a console session id, then call the
    // clear endpoint and assert the controller pulls and forwards it.
    withSession([ExecuteOctaveCommandRequest::SESSION_KEY => 'console-session-xyz'])
        ->deleteJson('/api/v1/octave/session', [], ['X-API-Key' => $plaintext])
        ->assertStatus(204);

    $clears = array_values(array_filter($fake->calls, fn (array $call): bool => $call['method'] === 'clearSession'));
    expect($clears)->toHaveCount(1);
    $sessionId = $clears[0]['args'][0];
    expect($sessionId)->toBeString()->toBe('console-session-xyz');
});

it('returns 401 without an X-API-Key', function (): void {
    bindClearFakeBridge();
    deleteJson('/api/v1/octave/session')->assertStatus(401);
});
