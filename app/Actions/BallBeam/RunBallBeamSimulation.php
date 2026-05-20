<?php

declare(strict_types=1);

namespace App\Actions\BallBeam;

use App\Data\BallBeamParameters;
use App\Data\SimulationTrajectory;
use App\Enums\AnimationName;
use App\Events\SimulationStarted;
use App\Services\Octave\OctaveBridgeClient;
use App\Support\Octave\TrajectoryParser;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Runs the ball-on-beam simulation and returns a parsed trajectory.
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
final readonly class RunBallBeamSimulation
{
    public function __construct(
        private OctaveBridgeClient $bridge,
        private TrajectoryParser $parser,
    ) {}

    /**
     * @param  list<float>|null  $continueFrom  Optional 4-element final state
     *                                          [position, velocity, angle, angular_velocity]
     *                                          from a previous run.
     */
    public function handle(
        BallBeamParameters $parameters,
        ?array $continueFrom,
        string $anonToken,
        string $ip,
    ): SimulationTrajectory {
        $parameterHash = md5(
            json_encode($parameters->toArray(), JSON_THROW_ON_ERROR).
            json_encode($continueFrom ?? [], JSON_THROW_ON_ERROR),
        );

        SimulationStarted::dispatch(
            AnimationName::BallBeam,
            $anonToken,
            $ip,
            $parameterHash,
        );

        // Ephemeral session: 'bb_' prefix + ULID fits the bridge's session-id
        // regex (8–64 alphanumeric+hyphen characters).
        $sessionId = 'bb_'.Str::ulid()->toBase32();

        $script = View::make('octave.ball-beam', [
            'p' => $parameters,
            'continueFrom' => $continueFrom ?? [],
        ])->render();

        /** @var int $timeout */
        $timeout = config('cas.simulation_octave_timeout_seconds');

        // The finally block ensures the ephemeral session is always cleaned up,
        // even when the bridge throws. The exception re-propagates after cleanup.
        try {
            $result = $this->bridge->execute($sessionId, $script, $timeout);
        } finally {
            $this->bridge->clearSession($sessionId);
        }

        return $this->parser->parseBallBeam($result->stdout, $parameters, $result->requestId);
    }
}
