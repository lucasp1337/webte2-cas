<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\AnimationName;
use Spatie\LaravelData\Data;

/**
 * Parsed trajectory returned from a single simulation run.
 *
 * `finalState` column ordering for pendulum: [x, x_dot, theta, theta_dot]
 *   0 → cart position (x)
 *   1 → cart velocity (x_dot)
 *   2 → pendulum angle (theta)
 *   3 → pendulum angular velocity (theta_dot)
 *
 * `finalState` column ordering for ball-beam: [position, velocity, angle, angular_velocity]
 *   0 → ball position
 *   1 → ball velocity
 *   2 → beam angle
 *   3 → beam angular velocity
 *
 * The ordering must be preserved when passing `finalState` as `continue_from`
 * in the next run.
 *
 * `samples` shape varies by animation:
 *   pendulum:  { t: float, x: float, theta: float }
 *   ball-beam: { t: float, position: float, beam_angle: float }
 */
final class SimulationTrajectory extends Data
{
    /**
     * @param  array<int, array<string, float>>  $samples
     * @param  array{0: float, 1: float, 2: float, 3: float}  $finalState
     */
    public function __construct(
        public readonly string $requestId,
        public readonly AnimationName $animation,
        public readonly float $durationSeconds,
        public readonly float $stepSize,
        public readonly array $samples,
        public readonly array $finalState,
    ) {}
}
