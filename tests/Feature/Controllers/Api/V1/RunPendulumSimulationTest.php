<?php

declare(strict_types=1);

use App\Enums\AnimationName;
use App\Events\SimulationStarted;
use App\Http\Middleware\EnsureAnonTokenMiddleware;
use App\Models\ApiKey;
use App\Services\Octave\Exceptions\OctaveBridgeUnavailableException;
use App\Services\Octave\Exceptions\OctaveCommandRejectedException;
use App\Services\Octave\Exceptions\OctaveTimeoutException;
use App\Services\Octave\OctaveBridgeClient;
use App\Services\Octave\OctaveExecutionResult;
use App\Services\Octave\Testing\FakeOctaveBridgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

/**
 * Returns a valid parameter array matching the default PendulumParameters shape.
 *
 * @return array<string, float>
 */
function pendulumValidParams(): array
{
    return [
        'M' => 1.0,
        'm' => 0.2,
        'b' => 0.1,
        'I' => 0.006,
        'g' => 9.81,
        'l' => 0.5,
        'r' => 0.2,
        'init_position' => 0.0,
        'init_angle' => 0.15,
        't_end' => 0.08,
        'dt' => 0.02,
    ];
}

/**
 * Creates an API key and returns [ApiKey, plaintext].
 *
 * @return array{0: ApiKey, 1: string}
 */
function makePendulumApiKey(string $suffix): array
{
    $plaintext = 'webte2_'.str_pad($suffix, 48, 'p');
    $apiKey = ApiKey::create(['name' => "pendulum-{$suffix}", 'key_hash' => $plaintext]);

    return [$apiKey, $plaintext];
}

/** Returns the golden fixture stdout. */
function pendulumFixtureStdout(): string
{
    return (string) file_get_contents(
        base_path('tests/Fixtures/octave/pendulum-default.txt'),
    );
}

function bindFakePendulumBridge(): FakeOctaveBridgeClient
{
    $fake = new FakeOctaveBridgeClient;
    app()->instance(OctaveBridgeClient::class, $fake);

    return $fake;
}

describe('RunPendulumSimulationController', function (): void {
    it('returns a SimulationTrajectory on happy path', function (): void {
        [, $key] = makePendulumApiKey('happypath0');
        $fake = bindFakePendulumBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-ok', pendulumFixtureStdout(), '', 0, 150));

        postJson('/api/v1/simulations/pendulum', ['parameters' => pendulumValidParams()], ['X-API-Key' => $key])
            ->assertStatus(200)
            ->assertJsonStructure([
                'request_id',
                'animation',
                'duration_seconds',
                'step_size',
                'samples',
                'final_state',
            ])
            ->assertJsonPath('animation', 'pendulum')
            ->assertJsonCount(5, 'samples')
            ->assertJsonCount(4, 'final_state');
    });

    it('returns 401 without an api key', function (): void {
        bindFakePendulumBridge();
        postJson('/api/v1/simulations/pendulum', ['parameters' => pendulumValidParams()])
            ->assertStatus(401);
    });

    it('returns 422 when cart_mass is zero', function (): void {
        [, $key] = makePendulumApiKey('validMzero0');
        bindFakePendulumBridge();
        $params = array_merge(pendulumValidParams(), ['M' => 0]);

        postJson('/api/v1/simulations/pendulum', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when pendulum_mass is negative', function (): void {
        [, $key] = makePendulumApiKey('validmneg0');
        bindFakePendulumBridge();
        $params = array_merge(pendulumValidParams(), ['m' => -1]);

        postJson('/api/v1/simulations/pendulum', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when length is zero', function (): void {
        [, $key] = makePendulumApiKey('validlzero0');
        bindFakePendulumBridge();
        $params = array_merge(pendulumValidParams(), ['l' => 0]);

        postJson('/api/v1/simulations/pendulum', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when gravity is negative', function (): void {
        [, $key] = makePendulumApiKey('validgneg00');
        bindFakePendulumBridge();
        $params = array_merge(pendulumValidParams(), ['g' => -9.81]);

        postJson('/api/v1/simulations/pendulum', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when friction is negative', function (): void {
        [, $key] = makePendulumApiKey('validbneg00');
        bindFakePendulumBridge();
        $params = array_merge(pendulumValidParams(), ['b' => -0.1]);

        postJson('/api/v1/simulations/pendulum', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when inertia is zero', function (): void {
        [, $key] = makePendulumApiKey('validIzero0');
        bindFakePendulumBridge();
        $params = array_merge(pendulumValidParams(), ['I' => 0]);

        postJson('/api/v1/simulations/pendulum', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when duration_seconds exceeds 30', function (): void {
        [, $key] = makePendulumApiKey('validtend00');
        bindFakePendulumBridge();
        $params = array_merge(pendulumValidParams(), ['t_end' => 31]);

        postJson('/api/v1/simulations/pendulum', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when duration_seconds is zero', function (): void {
        [, $key] = makePendulumApiKey('validtend01');
        bindFakePendulumBridge();
        $params = array_merge(pendulumValidParams(), ['t_end' => 0]);

        postJson('/api/v1/simulations/pendulum', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when step_size is below minimum', function (): void {
        [, $key] = makePendulumApiKey('validdtlow0');
        bindFakePendulumBridge();
        $params = array_merge(pendulumValidParams(), ['dt' => 0.0005]);

        postJson('/api/v1/simulations/pendulum', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('returns 422 when step_size exceeds maximum', function (): void {
        [, $key] = makePendulumApiKey('validdthigh');
        bindFakePendulumBridge();
        $params = array_merge(pendulumValidParams(), ['dt' => 0.6]);

        postJson('/api/v1/simulations/pendulum', ['parameters' => $params], ['X-API-Key' => $key])
            ->assertStatus(422);
    });

    it('fires SimulationStarted with the correct payload', function (): void {
        Event::fake([SimulationStarted::class]);

        [, $key] = makePendulumApiKey('evtfired000');
        $fake = bindFakePendulumBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-evt', pendulumFixtureStdout(), '', 0, 150));

        postJson('/api/v1/simulations/pendulum', ['parameters' => pendulumValidParams()], ['X-API-Key' => $key])
            ->assertStatus(200);

        Event::assertDispatched(SimulationStarted::class, function (SimulationStarted $e): bool {
            return $e->animation === AnimationName::Pendulum
                && $e->anonToken !== ''
                && $e->ip !== ''
                && $e->parameterHash !== '';
        });
    });

    it('produces the same parameterHash for identical parameters across two requests', function (): void {
        Event::fake([SimulationStarted::class]);

        [, $key] = makePendulumApiKey('hashstable0');
        $fake = bindFakePendulumBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-h1', pendulumFixtureStdout(), '', 0, 150));

        postJson('/api/v1/simulations/pendulum', ['parameters' => pendulumValidParams()], ['X-API-Key' => $key])
            ->assertStatus(200);

        $fake->setNextResult(new OctaveExecutionResult('req-h2', pendulumFixtureStdout(), '', 0, 150));

        postJson('/api/v1/simulations/pendulum', ['parameters' => pendulumValidParams()], ['X-API-Key' => $key])
            ->assertStatus(200);

        // Collect all dispatched hashes and assert they are all identical.
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
        [, $key] = makePendulumApiKey('bridge503xx');
        $fake = bindFakePendulumBridge();
        $fake->queueException(new OctaveBridgeUnavailableException('boom'));

        postJson('/api/v1/simulations/pendulum', ['parameters' => pendulumValidParams()], ['X-API-Key' => $key])
            ->assertStatus(503)
            ->assertJsonPath('error', 'bridge_unavailable');
    });

    it('returns 504 when the bridge times out', function (): void {
        [, $key] = makePendulumApiKey('bridge504xx');
        $fake = bindFakePendulumBridge();
        $fake->queueException(new OctaveTimeoutException('boom'));

        postJson('/api/v1/simulations/pendulum', ['parameters' => pendulumValidParams()], ['X-API-Key' => $key])
            ->assertStatus(504);
    });

    it('returns 422 when the bridge rejects the command', function (): void {
        [, $key] = makePendulumApiKey('bridgerej00');
        $fake = bindFakePendulumBridge();
        $fake->queueException(new OctaveCommandRejectedException(
            message: 'forbidden token: system',
            reason: 'forbidden token: system',
        ));

        postJson('/api/v1/simulations/pendulum', ['parameters' => pendulumValidParams()], ['X-API-Key' => $key])
            ->assertStatus(422)
            ->assertJsonPath('error', 'command_rejected');
    });

    it('records a clearSession call after a successful run', function (): void {
        [, $key] = makePendulumApiKey('clearsess00');
        $fake = bindFakePendulumBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-clear', pendulumFixtureStdout(), '', 0, 150));

        postJson('/api/v1/simulations/pendulum', ['parameters' => pendulumValidParams()], ['X-API-Key' => $key])
            ->assertStatus(200);

        $clearCalls = collect($fake->calls)->where('method', 'clearSession');
        expect($clearCalls)->toHaveCount(1);
    });

    it('passes continue_from values into the rendered script', function (): void {
        [, $key] = makePendulumApiKey('continuefr0');
        $fake = bindFakePendulumBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-cont', pendulumFixtureStdout(), '', 0, 150));

        postJson(
            '/api/v1/simulations/pendulum',
            [
                'parameters' => pendulumValidParams(),
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
        [, $key] = makePendulumApiKey('cookienew0');
        $fake = bindFakePendulumBridge();
        $fake->setNextResult(new OctaveExecutionResult('req-cookie-new', pendulumFixtureStdout(), '', 0, 150));

        $response = postJson(
            '/api/v1/simulations/pendulum',
            ['parameters' => pendulumValidParams()],
            ['X-API-Key' => $key],
        )->assertStatus(200);

        expect($response->headers->has('Set-Cookie'))->toBeTrue();
        expect((string) $response->headers->get('Set-Cookie'))->toContain('anon_token=');
    });

    it('reuses the existing anon_token from the request attributes when EnsureAnonToken runs', function (): void {
        // EnsureAnonTokenMiddleware exposes the resolved token via request attributes.
        // This unit-level test exercises the middleware directly on a synthetic
        // request carrying a valid UUID cookie, asserting that the response does
        // NOT set a new anon_token cookie (the middleware only issues one when the
        // cookie is absent or invalid).
        $existingUuid = (string) Str::uuid();

        $request = Request::create('/test', 'GET', [], ['anon_token' => $existingUuid]);
        $middleware = new EnsureAnonTokenMiddleware;

        $response = $middleware->handle($request, function (Request $req) use ($existingUuid): Response {
            // Assert that the valid cookie was preserved, not regenerated.
            expect($req->attributes->get('anon_token'))->toBe($existingUuid);

            return new Response('ok');
        });

        // No new cookie should be emitted when the token was already valid.
        $setCookieHeader = $response->headers->get('Set-Cookie', '');
        expect((string) $setCookieHeader)->not->toContain('anon_token=');
    });
});
