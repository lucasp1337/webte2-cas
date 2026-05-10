<?php

declare(strict_types=1);

use App\Enums\AnimationName;
use App\Events\SimulationStarted;
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

/**
 * Returns a valid parameter array matching the default BallBeamParameters shape.
 *
 * @return array<string, float>
 */
function bbValidParams(): array
{
    return [
        'ball_mass' => 0.11,
        'ball_radius' => 0.015,
        'inertia' => 0.00000999,
        'beam_length' => 1.0,
        'gravity' => 9.81,
        'reference_position' => 0.25,
        'initial_position' => 0.0,
        'initial_velocity' => 0.0,
        'initial_angle' => 0.0,
        'duration_seconds' => 0.08,
        'step_size' => 0.02,
    ];
}

/**
 * Creates an API key and returns [ApiKey, plaintext].
 *
 * @return array{0: ApiKey, 1: string}
 */
function makeBbApiKey(string $suffix): array
{
    $plaintext = 'webte2_'.str_pad($suffix, 48, 'b');
    $apiKey = ApiKey::create(['name' => "bb-{$suffix}", 'key_hash' => $plaintext]);

    return [$apiKey, $plaintext];
}

/** Returns the ball-beam golden fixture stdout. */
function bbFixtureStdout(): string
{
    return (string) file_get_contents(
        base_path('tests/Fixtures/octave/ball-beam-default.txt'),
    );
}

/** Bind a fresh FakeOctaveBridgeClient for ball-beam tests and return it. */
function bindFakeBbBridge(): FakeOctaveBridgeClient
{
    $fake = new FakeOctaveBridgeClient;
    app()->instance(OctaveBridgeClient::class, $fake);

    return $fake;
}

describe('RunBallBeamSimulationController', function (): void {
    it('returns a SimulationTrajectory on happy path', function (): void {
        [, $key] = makeBbApiKey('happypath0');
        $fake = bindFakeBbBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-ok', bbFixtureStdout(), '', 0, 150));

        postJson('/api/v1/simulations/ball-beam', ['parameters' => bbValidParams()], ['X-API-Key' => $key])
            ->assertStatus(200)
            ->assertJsonStructure([
                'request_id',
                'animation',
                'duration_seconds',
                'step_size',
                'samples',
                'final_state',
            ])
            ->assertJsonPath('animation', 'ball-beam')
            ->assertJsonCount(5, 'samples')
            ->assertJsonCount(4, 'final_state');
    });

    it('returns 401 without an api key', function (): void {
        bindFakeBbBridge();
        postJson('/api/v1/simulations/ball-beam', ['parameters' => bbValidParams()])
            ->assertStatus(401);
    });

    it('returns 422 when ball_mass is zero', function (): void {
        [, $key] = makeBbApiKey('mzero00000');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['ball_mass' => 0]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when ball_mass is 200', function (): void {
        [, $key] = makeBbApiKey('mover00000');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['ball_mass' => 200]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when ball_radius is zero', function (): void {
        [, $key] = makeBbApiKey('rzero00000');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['ball_radius' => 0]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when ball_radius is 2', function (): void {
        [, $key] = makeBbApiKey('rover000000');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['ball_radius' => 2]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when inertia is zero', function (): void {
        [, $key] = makeBbApiKey('jzero00000');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['inertia' => 0]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when inertia is 2', function (): void {
        [, $key] = makeBbApiKey('jover00000');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['inertia' => 2]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when beam_length is zero', function (): void {
        [, $key] = makeBbApiKey('blzero0000');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['beam_length' => 0]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when beam_length is 11', function (): void {
        [, $key] = makeBbApiKey('blover0000');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['beam_length' => 11]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when gravity is zero', function (): void {
        [, $key] = makeBbApiKey('gzero00000');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['gravity' => 0]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when gravity is 31', function (): void {
        [, $key] = makeBbApiKey('gover00000');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['gravity' => 31]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when duration_seconds is zero', function (): void {
        [, $key] = makeBbApiKey('tendzero00');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['duration_seconds' => 0]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when duration_seconds is 31', function (): void {
        [, $key] = makeBbApiKey('tendover00');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['duration_seconds' => 31]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when step_size is below minimum', function (): void {
        [, $key] = makeBbApiKey('dtlow00000');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['step_size' => 0.0005]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when step_size exceeds maximum', function (): void {
        [, $key] = makeBbApiKey('dthigh0000');
        bindFakeBbBridge();
        $params = array_merge(bbValidParams(), ['step_size' => 0.6]);

        postJson('/api/v1/simulations/ball-beam', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('fires SimulationStarted with the correct payload', function (): void {
        Event::fake([SimulationStarted::class]);

        [, $key] = makeBbApiKey('evtfired00');
        $fake = bindFakeBbBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-evt', bbFixtureStdout(), '', 0, 150));

        postJson('/api/v1/simulations/ball-beam', ['parameters' => bbValidParams()], ['X-API-Key' => $key])
            ->assertStatus(200);

        Event::assertDispatched(SimulationStarted::class, function (SimulationStarted $e): bool {
            return $e->animation === AnimationName::BallBeam
                && $e->anonToken !== ''
                && $e->ip !== ''
                && $e->parameterHash !== '';
        });
    });

    it('produces the same parameterHash for identical parameters across two requests', function (): void {
        Event::fake([SimulationStarted::class]);

        [, $key] = makeBbApiKey('hashstab00');
        $fake = bindFakeBbBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-h1', bbFixtureStdout(), '', 0, 150));

        postJson('/api/v1/simulations/ball-beam', ['parameters' => bbValidParams()], ['X-API-Key' => $key])
            ->assertStatus(200);

        $fake->setNextResult(new OctaveExecutionResult('req-h2', bbFixtureStdout(), '', 0, 150));

        postJson('/api/v1/simulations/ball-beam', ['parameters' => bbValidParams()], ['X-API-Key' => $key])
            ->assertStatus(200);

        $hashes = [];
        Event::assertDispatched(SimulationStarted::class, function (SimulationStarted $e) use (&$hashes): bool {
            $hashes[] = $e->parameterHash;

            return true;
        });

        /** @var list<string> $hashes */
        expect($hashes)->toHaveCount(2)
            ->and($hashes[0])->toBe($hashes[1]);
    });

    it('returns 503 when the bridge is unavailable', function (): void {
        [, $key] = makeBbApiKey('bridge503x');
        $fake = bindFakeBbBridge();
        $fake->queueException(new OctaveBridgeUnavailableException('boom'));

        postJson('/api/v1/simulations/ball-beam', ['parameters' => bbValidParams()], ['X-API-Key' => $key])
            ->assertStatus(503)
            ->assertJsonPath('error', 'bridge_unavailable');
    });

    it('returns 504 when the bridge times out', function (): void {
        [, $key] = makeBbApiKey('bridge504x');
        $fake = bindFakeBbBridge();
        $fake->queueException(new OctaveTimeoutException('boom'));

        postJson('/api/v1/simulations/ball-beam', ['parameters' => bbValidParams()], ['X-API-Key' => $key])
            ->assertStatus(504);
    });

    it('returns 422 when the bridge rejects the command', function (): void {
        [, $key] = makeBbApiKey('bridgerejx');
        $fake = bindFakeBbBridge();
        $fake->queueException(new OctaveCommandRejectedException(
            message: 'forbidden token: system',
            reason: 'forbidden token: system',
        ));

        postJson('/api/v1/simulations/ball-beam', ['parameters' => bbValidParams()], ['X-API-Key' => $key])
            ->assertStatus(422)
            ->assertJsonPath('error', 'command_rejected');
    });

    it('records a clearSession call after a successful run', function (): void {
        [, $key] = makeBbApiKey('clearsess0');
        $fake = bindFakeBbBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-clear', bbFixtureStdout(), '', 0, 150));

        postJson('/api/v1/simulations/ball-beam', ['parameters' => bbValidParams()], ['X-API-Key' => $key])
            ->assertStatus(200);

        $clearCalls = collect($fake->calls)->where('method', 'clearSession');
        expect($clearCalls)->toHaveCount(1);
    });

    it('passes continue_from values into the rendered script', function (): void {
        [, $key] = makeBbApiKey('continuef0');
        $fake = bindFakeBbBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-cont', bbFixtureStdout(), '', 0, 150));

        postJson(
            '/api/v1/simulations/ball-beam',
            [
                'parameters' => bbValidParams(),
                'continue_from' => [0.1, 0.0, 0.05, 0.0],
            ],
            ['X-API-Key' => $key],
        )->assertStatus(200);

        $command = '';
        foreach ($fake->calls as $call) {
            if ($call['method'] === 'execute') {
                $command = is_string($call['args'][1] ?? null) ? (string) $call['args'][1] : '';
                break;
            }
        }

        expect($command)->not->toBe('');

        // PHP's sprintf('%.15e') emits 1-digit exponents on this platform.
        // Assert on the mantissa prefix only so the test is platform-portable.
        expect($command)
            ->toContain('1.000000000000000e-')  // 0.1 → 1.000000000000000e-1
            ->toContain('5.000000000000000e-'); // 0.05 → 5.000000000000000e-2
    });

    it('issues an anon_token cookie when one is not present', function (): void {
        [, $key] = makeBbApiKey('cookienew0');
        $fake = bindFakeBbBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-cookie', bbFixtureStdout(), '', 0, 150));

        $response = postJson(
            '/api/v1/simulations/ball-beam',
            ['parameters' => bbValidParams()],
            ['X-API-Key' => $key],
        )->assertStatus(200);

        expect($response->headers->has('Set-Cookie'))->toBeTrue();
        expect((string) $response->headers->get('Set-Cookie'))->toContain('anon_token=');
    });

    it('returns 422 for non-finite continue_from values', function (): void {
        [, $key] = makeBbApiKey('cfnantest0');
        bindFakeBbBridge();

        postJson(
            '/api/v1/simulations/ball-beam',
            [
                'parameters' => bbValidParams(),
                'continue_from' => [1, 2, 3, 'Infinity'],
            ],
            ['X-API-Key' => $key],
        )->assertStatus(422);
    });
});
