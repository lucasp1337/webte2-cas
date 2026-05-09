<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\ExecuteOctaveCommandRequest;
use App\Models\ApiKey;
use App\Services\Octave\OctaveBridgeClient;
use App\Services\Octave\OctaveExecutionResult;
use App\Services\Octave\Testing\FakeOctaveBridgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;
use function Pest\Laravel\withSession;

uses(RefreshDatabase::class);

/** @return array{0: ApiKey, 1: string} */
function makePersistApiKey(string $suffix): array
{
    $plaintext = 'webte2_'.str_pad($suffix, 48, 'x');
    $apiKey = ApiKey::create(['name' => "persist-{$suffix}", 'key_hash' => $plaintext]);

    return [$apiKey, $plaintext];
}

function bindPersistFakeBridge(): FakeOctaveBridgeClient
{
    $fake = new FakeOctaveBridgeClient;
    app()->instance(OctaveBridgeClient::class, $fake);

    return $fake;
}

it('reuses the seeded session id across two sequential commands', function (): void {
    [, $plaintext] = makePersistApiKey('persist000');
    $fake = bindPersistFakeBridge();

    $headers = ['X-API-Key' => $plaintext];
    $sessionState = [ExecuteOctaveCommandRequest::SESSION_KEY => 'persisted-uuid-1234'];

    $fake->setNextResult(new OctaveExecutionResult('r1', "ok\n", '', 0, 1));
    withSession($sessionState)
        ->postJson('/api/v1/octave/exec', ['command' => 'a = 1+1;'], $headers)
        ->assertStatus(200);

    $fake->setNextResult(new OctaveExecutionResult('r2', "4\n", '', 0, 1));
    withSession($sessionState)
        ->postJson('/api/v1/octave/exec', ['command' => 'a + 2'], $headers)
        ->assertStatus(200);

    $execCalls = array_values(array_filter(
        $fake->calls,
        fn (array $call): bool => $call['method'] === 'execute',
    ));

    expect($execCalls)->toHaveCount(2);
    $sid0 = $execCalls[0]['args'][0];
    $sid1 = $execCalls[1]['args'][0];
    expect($sid0)->toBeString()->toBe('persisted-uuid-1234')
        ->and($sid1)->toBeString()->toBe('persisted-uuid-1234');
});

it('issues a fresh session id when the previous session was cleared', function (): void {
    [, $plaintext] = makePersistApiKey('persistclr');
    $fake = bindPersistFakeBridge();

    $headers = ['X-API-Key' => $plaintext];

    $fake->setNextResult(new OctaveExecutionResult('r1', "ok\n", '', 0, 1));
    withSession([ExecuteOctaveCommandRequest::SESSION_KEY => 'session-one'])
        ->postJson('/api/v1/octave/exec', ['command' => 'b = 7;'], $headers)
        ->assertStatus(200);

    // Clearing wipes the cookie; the next exec gets whatever the form request
    // generates fresh (empty seed -> new UUID).
    withSession([ExecuteOctaveCommandRequest::SESSION_KEY => 'session-one'])
        ->deleteJson('/api/v1/octave/session', [], $headers)
        ->assertStatus(204);

    $fake->setNextResult(new OctaveExecutionResult('r2', "ok\n", '', 0, 1));
    postJson('/api/v1/octave/exec', ['command' => 'c = 8;'], $headers)
        ->assertStatus(200);

    $execCalls = array_values(array_filter(
        $fake->calls,
        fn (array $call): bool => $call['method'] === 'execute',
    ));

    expect($execCalls)->toHaveCount(2);

    // The clear pulled session-one and asked the bridge to drop it.
    $clearCalls = array_values(array_filter(
        $fake->calls,
        fn (array $call): bool => $call['method'] === 'clearSession',
    ));
    expect($clearCalls)->toHaveCount(1);
    $clearedId = $clearCalls[0]['args'][0];
    expect($clearedId)->toBeString()->toBe('session-one');

    // After clearing, the second exec uses whatever the form request minted —
    // any non-empty UUID different from session-one is acceptable.
    $freshId = $execCalls[1]['args'][0];
    expect($freshId)->toBeString();
    expect($freshId)->not->toBe('session-one');
    expect($freshId)->not->toBe('');
});
