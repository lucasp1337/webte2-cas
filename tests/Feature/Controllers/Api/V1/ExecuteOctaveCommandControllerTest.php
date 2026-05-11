<?php

declare(strict_types=1);

use App\Events\OctaveCommandExecuted;
use App\Models\ApiKey;
use App\Services\Octave\Exceptions\OctaveBridgeUnavailableException;
use App\Services\Octave\Exceptions\OctaveCommandRejectedException;
use App\Services\Octave\Exceptions\OctaveTimeoutException;
use App\Services\Octave\OctaveBridgeClient;
use App\Services\Octave\OctaveExecutionResult;
use App\Services\Octave\Testing\FakeOctaveBridgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

/** @return array{0: ApiKey, 1: string} */
function makeOctaveApiKey(string $suffix): array
{
    $plaintext = 'webte2_'.str_pad($suffix, 48, 'x');
    $apiKey = ApiKey::create(['name' => "octave-{$suffix}", 'key_hash' => $plaintext]);

    return [$apiKey, $plaintext];
}

function bindFakeBridge(): FakeOctaveBridgeClient
{
    $fake = new FakeOctaveBridgeClient;
    app()->instance(OctaveBridgeClient::class, $fake);

    return $fake;
}

it('returns 200 with the execution payload on success', function (): void {
    [, $plaintext] = makeOctaveApiKey('execok0000');
    $fake = bindFakeBridge();
    $fake->setNextResult(new OctaveExecutionResult('req-success', "ans = 2\n", '', 0, 12));

    postJson('/api/v1/octave/exec', ['command' => '1+1'], ['X-API-Key' => $plaintext])
        ->assertStatus(200)
        ->assertJsonPath('stdout', "ans = 2\n")
        ->assertJsonPath('exit_code', 0)
        ->assertJsonPath('duration_ms', 12);
});

it('dispatches OctaveCommandExecuted on success', function (): void {
    Event::fake([OctaveCommandExecuted::class]);

    [, $plaintext] = makeOctaveApiKey('execev0000');
    $fake = bindFakeBridge();
    $fake->setNextResult(new OctaveExecutionResult('req-evt', "ok\n", '', 0, 5));

    postJson('/api/v1/octave/exec', ['command' => 'disp("ok")'], ['X-API-Key' => $plaintext])
        ->assertStatus(200);

    Event::assertDispatched(OctaveCommandExecuted::class, function (OctaveCommandExecuted $event): bool {
        return $event->result->isSuccessful() && $event->sessionId !== '';
    });
});

it('returns 422 with the rejection reason when the bridge rejects the command', function (): void {
    [, $plaintext] = makeOctaveApiKey('execrej000');
    $fake = bindFakeBridge();
    $fake->queueException(new OctaveCommandRejectedException(
        message: "Forbidden token: 'system'",
        reason: "Forbidden token: 'system'",
    ));

    postJson('/api/v1/octave/exec', ['command' => "system('ls')"], ['X-API-Key' => $plaintext])
        ->assertStatus(422)
        ->assertJsonPath('rejection_reason', "Forbidden token: 'system'");
});

it('returns 504 when Octave times out', function (): void {
    [, $plaintext] = makeOctaveApiKey('exectim000');
    $fake = bindFakeBridge();
    $fake->queueException(new OctaveTimeoutException('timeout'));

    postJson('/api/v1/octave/exec', ['command' => 'while true; end'], ['X-API-Key' => $plaintext])
        ->assertStatus(504);
});

it('returns 503 when the bridge is unavailable', function (): void {
    [, $plaintext] = makeOctaveApiKey('execdwn000');
    $fake = bindFakeBridge();
    $fake->queueException(new OctaveBridgeUnavailableException('connection refused'));

    postJson('/api/v1/octave/exec', ['command' => '1+1'], ['X-API-Key' => $plaintext])
        ->assertStatus(503)
        ->assertJsonPath('error', 'bridge_unavailable');
});

it('rejects requests without a command with 422', function (): void {
    [, $plaintext] = makeOctaveApiKey('execnocm00');
    bindFakeBridge();

    postJson('/api/v1/octave/exec', [], ['X-API-Key' => $plaintext])
        ->assertStatus(422);
});

it('rejects requests with a command longer than 4096 chars with 422', function (): void {
    [, $plaintext] = makeOctaveApiKey('execlong00');
    bindFakeBridge();

    postJson('/api/v1/octave/exec', ['command' => str_repeat('a', 4097)], ['X-API-Key' => $plaintext])
        ->assertStatus(422);
});

it('returns 401 without an X-API-Key', function (): void {
    bindFakeBridge();
    postJson('/api/v1/octave/exec', ['command' => '1+1'])->assertStatus(401);
});

describe('stderr passthrough', function (): void {
    it('returns empty stderr on a trivial assignment when the bridge filters shutdown noise', function (): void {
        // The bridge strips the known Octave shutdown noise before returning to the
        // controller; here we simulate that post-fix contract — the fake returns
        // clean empty stderr, and the controller must echo it verbatim.
        [, $plaintext] = makeOctaveApiKey('stderrclean');
        $fake = bindFakeBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-1', '', '', 0, 95));

        postJson('/api/v1/octave/exec', ['command' => 'a = 5;'], ['X-API-Key' => $plaintext])
            ->assertStatus(200)
            ->assertJsonPath('stderr', '')
            ->assertJsonPath('exit_code', 0)
            ->assertJsonPath('stdout', '');
    });

    it('does not strip arbitrary stderr text — the controller is not a filter', function (): void {
        // The stripping is the bridge's responsibility. If the bridge returns real
        // diagnostic text the controller must echo it unchanged so the UI can surface
        // it to the user. This test guards against a regression where someone adds a
        // second, wrong-layer filter inside the controller or action.
        [, $plaintext] = makeOctaveApiKey('stderrreal0');
        $fake = bindFakeBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-2', '', 'error: something real happened', 1, 20));

        postJson('/api/v1/octave/exec', ['command' => 'undefined_var'], ['X-API-Key' => $plaintext])
            ->assertStatus(200)
            ->assertJsonPath('stderr', 'error: something real happened')
            ->assertJsonPath('exit_code', 1);
    });
});
