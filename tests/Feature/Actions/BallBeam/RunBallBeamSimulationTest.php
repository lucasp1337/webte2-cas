<?php

declare(strict_types=1);

use App\Actions\BallBeam\RunBallBeamSimulation;
use App\Data\BallBeamParameters;
use App\Enums\AnimationName;
use App\Events\SimulationStarted;
use App\Services\Octave\Exceptions\OctaveTimeoutException;
use App\Services\Octave\OctaveBridgeClient;
use App\Services\Octave\OctaveExecutionResult;
use App\Services\Octave\Testing\FakeOctaveBridgeClient;
use Illuminate\Support\Facades\Event;

/** Build a valid BallBeamParameters DTO for action tests. */
function bbActionParams(float $ballMass = 0.11): BallBeamParameters
{
    return new BallBeamParameters(
        ball_mass: $ballMass,
        ball_radius: 0.015,
        inertia: 0.00000999,
        beam_length: 1.0,
        gravity: 9.81,
        reference_position: 0.25,
        initial_position: 0.0,
        initial_velocity: 0.0,
        initial_angle: 0.0,
        duration_seconds: 0.08,
        step_size: 0.02,
    );
}

/** Returns the ball-beam golden fixture stdout. */
function bbActionFixtureStdout(): string
{
    return (string) file_get_contents(
        base_path('tests/Fixtures/octave/ball-beam-default.txt'),
    );
}

/** Bind a fresh FakeOctaveBridgeClient and return it. */
function bindBbActionFakeBridge(): FakeOctaveBridgeClient
{
    $fake = new FakeOctaveBridgeClient;
    app()->instance(OctaveBridgeClient::class, $fake);

    return $fake;
}

/** Extract the command string from the first execute call on a FakeOctaveBridgeClient. */
function bbFirstExecuteCommand(FakeOctaveBridgeClient $fake): string
{
    foreach ($fake->calls as $call) {
        if ($call['method'] === 'execute') {
            $command = $call['args'][1] ?? '';

            return is_string($command) ? $command : '';
        }
    }

    return '';
}

describe('RunBallBeamSimulation action', function (): void {
    it('renders parameter values into the Octave script', function (): void {
        $fake = bindBbActionFakeBridge();
        $fake->setNextResult(
            new OctaveExecutionResult('req-render', bbActionFixtureStdout(), '', 0, 100),
        );

        /** @var RunBallBeamSimulation $action */
        $action = app(RunBallBeamSimulation::class);
        $action->handle(bbActionParams(), null, 'tok-test', '127.0.0.1');

        $command = bbFirstExecuteCommand($fake);

        // Each parameter must appear in the rendered script as a %.15e float.
        // Assert on the mantissa prefix only — platform-portable.
        expect($command)
            ->toContain('1.100000000000000e')  // ball_mass = 0.11
            ->toContain('1.500000000000000e')  // ball_radius = 0.015
            ->toContain('9.810000000000000e')  // gravity = 9.81
            ->toContain('2.500000000000000e'); // reference_position = 0.25
    });

    it('dispatches SimulationStarted with AnimationName::BallBeam', function (): void {
        Event::fake([SimulationStarted::class]);

        $fake = bindBbActionFakeBridge();
        $fake->setNextResult(
            new OctaveExecutionResult('req-evt', bbActionFixtureStdout(), '', 0, 100),
        );

        /** @var RunBallBeamSimulation $action */
        $action = app(RunBallBeamSimulation::class);
        $action->handle(bbActionParams(), null, 'anon-bb', '10.0.0.1');

        Event::assertDispatched(SimulationStarted::class, function (SimulationStarted $e): bool {
            return $e->animation === AnimationName::BallBeam
                && $e->anonToken === 'anon-bb'
                && $e->ip === '10.0.0.1'
                && $e->parameterHash !== '';
        });
    });

    it('produces the same parameterHash for identical parameters', function (): void {
        Event::fake([SimulationStarted::class]);

        $fake = bindBbActionFakeBridge();
        $fake->setNextResult(
            new OctaveExecutionResult('req-h1', bbActionFixtureStdout(), '', 0, 100),
        );

        /** @var RunBallBeamSimulation $action */
        $action = app(RunBallBeamSimulation::class);
        $params = bbActionParams();
        $action->handle($params, null, 'tok', '127.0.0.1');

        $fake->setNextResult(
            new OctaveExecutionResult('req-h2', bbActionFixtureStdout(), '', 0, 100),
        );
        $action->handle($params, null, 'tok', '127.0.0.1');

        $hashes = [];
        Event::assertDispatched(SimulationStarted::class, function (SimulationStarted $e) use (&$hashes): bool {
            $hashes[] = $e->parameterHash;

            return true;
        });

        /** @var list<string> $hashes */
        expect($hashes)->toHaveCount(2)
            ->and($hashes[0])->toBe($hashes[1]);
    });

    it('uses simulation_octave_timeout_seconds for the bridge timeout', function (): void {
        $fake = bindBbActionFakeBridge();
        $fake->setNextResult(
            new OctaveExecutionResult('req-timeout', bbActionFixtureStdout(), '', 0, 100),
        );

        /** @var int $configuredTimeout */
        $configuredTimeout = config('cas.simulation_octave_timeout_seconds');

        /** @var RunBallBeamSimulation $action */
        $action = app(RunBallBeamSimulation::class);
        $action->handle(bbActionParams(), null, 'tok', '127.0.0.1');

        $usedTimeout = null;
        foreach ($fake->calls as $call) {
            if ($call['method'] === 'execute') {
                $usedTimeout = $call['args'][2] ?? null;
                break;
            }
        }

        expect($usedTimeout)->toBe($configuredTimeout);
    });

    it('renders continue_from values into the script when provided', function (): void {
        $fake = bindBbActionFakeBridge();
        $fake->setNextResult(
            new OctaveExecutionResult('req-cont', bbActionFixtureStdout(), '', 0, 100),
        );

        /** @var RunBallBeamSimulation $action */
        $action = app(RunBallBeamSimulation::class);
        $action->handle(bbActionParams(), [0.1, 0.0, 0.05, 0.0], 'tok', '127.0.0.1');

        $command = bbFirstExecuteCommand($fake);

        // 0.1 → 1.000000000000000e-1; 0.05 → 5.000000000000000e-2
        expect($command)
            ->toContain('1.000000000000000e-')
            ->toContain('5.000000000000000e-');
    });

    it('clears the session even when the bridge call throws', function (): void {
        $fake = bindBbActionFakeBridge();
        $fake->queueException(new OctaveTimeoutException('bridge timed out'));

        /** @var RunBallBeamSimulation $action */
        $action = app(RunBallBeamSimulation::class);

        $threwExpected = false;
        try {
            $action->handle(bbActionParams(), null, 'tok', '127.0.0.1');
        } catch (OctaveTimeoutException) {
            $threwExpected = true;
        }

        expect($threwExpected)->toBeTrue();

        $clearCallCount = 0;
        foreach ($fake->calls as $call) {
            if ($call['method'] === 'clearSession') {
                $clearCallCount++;
            }
        }

        expect($clearCallCount)->toBe(1);
    });
});
