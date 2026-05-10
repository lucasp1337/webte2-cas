<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * Input parameters for the ball-on-beam simulation.
 *
 * Field names mirror `openapi.yaml BallBeamRequest` (long snake_case) so the
 * frontend Zod schema, the OpenAPI contract, and the backend share one
 * vocabulary. The Octave script (`resources/views/octave/ball-beam.blade.php`)
 * maps these to its own short variable names (`m`, `R`, `J`, …) for
 * readability inside the simulation.
 *
 * Octave correspondence (per gulicka.m):
 *   ball_mass            → m      (ball mass in kg)
 *   ball_radius          → R      (ball radius in m)
 *   inertia              → J      (moment of inertia of ball, kg·m²)
 *   beam_length          → used for visual range in the renderer; not directly
 *                          in the Octave equations but kept so the frontend
 *                          has physical context for scaling
 *   gravity              → g      (gravitational acceleration, always positive;
 *                          the Octave script uses −g in the H formula)
 *   reference_position   → r      (target ball position the controller drives toward)
 *   initial_position     → initRychlost (initial ball position on the beam, m)
 *   initial_velocity     → initRychlost (initial ball velocity, m/s) — gulicka.m
 *                          variable name is misleading; it is the initial
 *                          velocity in the state vector [position; velocity; …]
 *   initial_angle        → initZrychlenie (initial beam angle, rad) — similarly
 *                          misnamed in gulicka.m; it is the beam angle state
 *   duration_seconds     → t_end
 *   step_size            → dt
 *
 * State vector ordering (gulicka.m line 21):
 *   [position, velocity, angle, angular_velocity]
 * This ordering must be preserved in `final_state` / `continue_from` payloads.
 */
final class BallBeamParameters extends Data
{
    public function __construct(
        /** Ball mass (kg). Must be in (0, 100). */
        public readonly float $ball_mass,
        /** Ball radius (m). Must be in (0, 1). */
        public readonly float $ball_radius,
        /** Moment of inertia of the ball (kg·m²). Must be in (0, 1). */
        public readonly float $inertia,
        /** Beam length (m). Must be in (0, 10). Used for renderer scaling. */
        public readonly float $beam_length,
        /** Gravitational acceleration (m/s²). Must be in (0, 30). */
        public readonly float $gravity,
        /** Target ball position (m). Any finite value. */
        public readonly float $reference_position,
        /** Initial ball position on beam (m). Any finite value. */
        public readonly float $initial_position,
        /** Initial ball velocity (m/s). Any finite value. */
        public readonly float $initial_velocity,
        /** Initial beam angle (rad). Any finite value. */
        public readonly float $initial_angle,
        /** Simulation duration (s). Must be in (0, 30]. */
        public readonly float $duration_seconds,
        /** Time step (s). Must be in [0.001, 0.5]. */
        public readonly float $step_size,
    ) {}
}
