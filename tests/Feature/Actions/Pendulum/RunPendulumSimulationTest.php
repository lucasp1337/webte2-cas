<?php

declare(strict_types=1);

use App\Actions\Pendulum\RunPendulumSimulation;
use App\Data\PendulumParameters;
use App\Enums\AnimationName;
use App\Events\SimulationStarted;
use App\Services\Octave\Exceptions\OctaveTimeoutException;
use App\Services\Octave\OctaveBridgeClient;
use App\Services\Octave\OctaveExecutionResult;
use App\Services\Octave\Testing\FakeOctaveBridgeClient;
use Illuminate\Support\Facades\Event;

/** Build a valid PendulumParameters DTO. */
function actionTestParams(float $M = 1.0): PendulumParameters
{
    return new PendulumParameters(
        M: $M,
        m: 0.2,
        b: 0.1,
        I: 0.006,
        g: 9.81,
        l: 0.5,
        r: 0.2,
        init_position: 0.0,
        init_angle: 0.15,
        t_end: 0.08,
        dt: 0.02,
    );
}

/** Returns the golden fixture stdout. */
function actionFixtureStdout(): string
{
    return (string) file_get_contents(
        base_path('tests/Fixtures/octave/pendulum-default.txt'),
    );
}

/** Bind a fresh FakeOctaveBridgeClient and return it. */
function bindActionFakeBridge(): FakeOctaveBridgeClient
{
    $fake = new FakeOctaveBridgeClient;
    app()->instance(OctaveBridgeClient::class, $fake);

    return $fake;
}

/** Extract the command string from the first execute call on a FakeOctaveBridgeClient. */
function firstExecuteCommand(FakeOctaveBridgeClient $fake): string
{
    foreach ($fake->calls as $call) {
        if ($call['method'] === 'execute') {
            $command = $call['args'][1] ?? '';

            return is_string($command) ? $command : '';
        }
    }

    return '';
}

describe('RunPendulumSimulation action', function (): void {
    it('renders parameter values into the Octave script', function (): void {
        $fake = bindActionFakeBridge();
        $fake->setNextResult(
            new OctaveExecutionResult('req-render', actionFixtureStdout(), '', 0, 100),
        );

        /** @var RunPendulumSimulation $action */
        $action = app(RunPendulumSimulation::class);
        $action->handle(actionTestParams(), null, 'tok-test', '127.0.0.1');

        $command = firstExecuteCommand($fake);

        // Each parameter must appear in the rendered script as a %.15e float.
        // PHP's sprintf('%.15e') on this platform emits 1-digit exponents.
        // We assert on the mantissa prefix only, which is platform-portable.
        expect($command)
            ->toContain('1.000000000000000e')  // M = 1.0, g = 9.81
            ->toContain('2.000000000000000e')  // m = 0.2, dt = 0.02
            ->toContain('5.000000000000000e')  // l = 0.5
            ->toContain('9.810000000000000e'); // g = 9.81
    });

    it('dispatches SimulationStarted before the bridge call', function (): void {
        Event::fake([SimulationStarted::class]);

        $fake = bindActionFakeBridge();
        $fake->setNextResult(
            new OctaveExecutionResult('req-evt', actionFixtureStdout(), '', 0, 100),
        );

        /** @var RunPendulumSimulation $action */
        $action = app(RunPendulumSimulation::class);
        $action->handle(actionTestParams(), null, 'anon-abc', '10.0.0.1');

        Event::assertDispatched(SimulationStarted::class, function (SimulationStarted $e): bool {
            return $e->animation === AnimationName::Pendulum
                && $e->anonToken === 'anon-abc'
                && $e->ip === '10.0.0.1'
                && $e->parameterHash !== '';
        });
    });

    it('produces the same parameterHash for identical parameters', function (): void {
        Event::fake([SimulationStarted::class]);

        $fake = bindActionFakeBridge();
        $fake->setNextResult(
            new OctaveExecutionResult('req-h1', actionFixtureStdout(), '', 0, 100),
        );

        /** @var RunPendulumSimulation $action */
        $action = app(RunPendulumSimulation::class);
        $params = actionTestParams();
        $action->handle($params, null, 'tok', '127.0.0.1');

        $fake->setNextResult(
            new OctaveExecutionResult('req-h2', actionFixtureStdout(), '', 0, 100),
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

    it('produces different parameterHash when cart_mass differs', function (): void {
        Event::fake([SimulationStarted::class]);

        $fake = bindActionFakeBridge();
        $fake->setNextResult(
            new OctaveExecutionResult('req-ha', actionFixtureStdout(), '', 0, 100),
        );

        /** @var RunPendulumSimulation $action */
        $action = app(RunPendulumSimulation::class);
        $action->handle(actionTestParams(M: 1.0), null, 'tok', '127.0.0.1');

        $fake->setNextResult(
            new OctaveExecutionResult('req-hb', actionFixtureStdout(), '', 0, 100),
        );
        $action->handle(actionTestParams(M: 2.0), null, 'tok', '127.0.0.1');

        $hashes = [];
        Event::assertDispatched(SimulationStarted::class, function (SimulationStarted $e) use (&$hashes): bool {
            $hashes[] = $e->parameterHash;

            return true;
        });

        /** @var list<string> $hashes */
        expect($hashes)->toHaveCount(2)
            ->and($hashes[0])->not->toBe($hashes[1]);
    });

    it('passes the configured simulation timeout to the bridge execute call', function (): void {
        $fake = bindActionFakeBridge();
        $fake->setNextResult(
            new OctaveExecutionResult('req-timeout', actionFixtureStdout(), '', 0, 100),
        );

        /** @var int $configuredTimeout */
        $configuredTimeout = config('cas.simulation_octave_timeout_seconds');

        /** @var RunPendulumSimulation $action */
        $action = app(RunPendulumSimulation::class);
        $action->handle(actionTestParams(), null, 'tok', '127.0.0.1');

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
        $fake = bindActionFakeBridge();
        $fake->setNextResult(
            new OctaveExecutionResult('req-cont', actionFixtureStdout(), '', 0, 100),
        );

        /** @var RunPendulumSimulation $action */
        $action = app(RunPendulumSimulation::class);
        $action->handle(actionTestParams(), [0.1, 0.0, 0.05, 0.0], 'tok', '127.0.0.1');

        $command = firstExecuteCommand($fake);

        // 0.1 in %.15e format → 1.000000000000000e-1 (PHP single-digit exponent)
        // 0.05 in %.15e format → 5.000000000000000e-2
        // Assert on the mantissa+sign prefix to remain portable.
        expect($command)
            ->toContain('1.000000000000000e-')
            ->toContain('5.000000000000000e-');
    });

    it('clears the session even when the bridge call throws', function (): void {
        $fake = bindActionFakeBridge();
        $fake->queueException(new OctaveTimeoutException('bridge timed out'));

        /** @var RunPendulumSimulation $action */
        $action = app(RunPendulumSimulation::class);

        $threwExpected = false;
        try {
            $action->handle(actionTestParams(), null, 'tok', '127.0.0.1');
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
