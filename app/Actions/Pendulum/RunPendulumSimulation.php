<?php

declare(strict_types=1);

namespace App\Actions\Pendulum;

use App\Data\PendulumParameters;
use App\Data\SimulationTrajectory;
use App\Enums\AnimationName;
use App\Events\SimulationStarted;
use App\Services\Octave\OctaveBridgeClient;
use App\Support\Octave\TrajectoryParser;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Runs the inverted-pendulum simulation and returns a parsed trajectory.
 *
 * Steps:
 *  1. Dispatch `SimulationStarted` BEFORE the bridge call so that the stats
 *     listener fires even if the bridge subsequently fails.
 *  2. Render the Octave script from the Blade template.
 *  3. Execute via the bridge with the configured simulation timeout.
 *  4. Parse the stdout markers into a `SimulationTrajectory`.
 *  5. Clear the ephemeral session in a `finally` block.
 *
 * Bridge exceptions (`OctaveCommandRejectedException`, `OctaveTimeoutException`,
 * `OctaveBridgeUnavailableException`) propagate to the controller for HTTP
 * status mapping.
 */
final readonly class RunPendulumSimulation
{
    public function __construct(
        private OctaveBridgeClient $bridge,
        private TrajectoryParser $parser,
    ) {}

    /**
     * @param  list<float>|null  $continueFrom  Optional 4-element final state
     *                                          [x, x_dot, theta, theta_dot]
     *                                          from a previous run.
     */
    public function handle(
        PendulumParameters $parameters,
        ?array $continueFrom,
        string $anonToken,
        string $ip,
    ): SimulationTrajectory {
        $parameterHash = md5(json_encode($parameters->toArray(), JSON_THROW_ON_ERROR));

        SimulationStarted::dispatch(
            AnimationName::Pendulum,
            $anonToken,
            $ip,
            $parameterHash,
        );

        // Ephemeral session: 'sim-' prefix + ULID fits the bridge's session-id
        // regex (8–64 alphanumeric+hyphen characters).
        $sessionId = 'sim-'.Str::ulid();

        $script = View::make('octave.pendulum', [
            'p' => $parameters,
            'continueFrom' => $continueFrom ?? [],
        ])->render();

        /** @var int $timeout */
        $timeout = config('cas.simulation_octave_timeout_seconds');

        // The finally block ensures the ephemeral session is always cleaned up,
        // even when the bridge throws. The exception re-propagates after cleanup.
        // PHPStan understands that after a try/finally with no catch, variables
        // assigned inside try are definitely initialised when execution continues
        // past the block (an exception would have re-thrown).
        try {
            $result = $this->bridge->execute($sessionId, $script, $timeout);
        } finally {
            $this->bridge->clearSession($sessionId);
        }

        return $this->parser->parsePendulum($result->stdout, $parameters, $result->requestId);
    }
}
