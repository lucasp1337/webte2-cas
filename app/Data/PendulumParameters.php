<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * Input parameters for the inverted-pendulum simulation.
 *
 * Field names mirror `openapi.yaml PendulumRequest` (long snake_case) so the
 * frontend Zod schema, the OpenAPI contract, and the backend share one
 * vocabulary. The Octave script (`resources/views/octave/pendulum.blade.php`)
 * maps these to its own short variable names (`M`, `m`, `b`, …) for
 * readability inside the simulation.
 *
 * Octave correspondence:
 *   cart_mass            → M
 *   pendulum_mass        → m
 *   friction             → b
 *   inertia              → I
 *   gravity              → g
 *   length               → l
 *   reference_position   → r
 *   initial_position     → x(0)
 *   initial_angle        → theta(0)
 *   duration_seconds     → t_end
 *   step_size            → dt
 */
final class PendulumParameters extends Data
{
    public function __construct(
        /** Cart mass (kg). Must be > 0. */
        public readonly float $cart_mass,
        /** Pendulum mass (kg). Must be > 0. */
        public readonly float $pendulum_mass,
        /** Friction coefficient. Must be ≥ 0. */
        public readonly float $friction,
        /** Moment of inertia (kg·m²). Must be > 0. */
        public readonly float $inertia,
        /** Gravitational acceleration (m/s²). Must be > 0. */
        public readonly float $gravity,
        /** Pendulum length (m). Must be > 0. */
        public readonly float $length,
        /** Reference cart position (m). Any finite value. */
        public readonly float $reference_position,
        /** Initial cart position (m). Any finite value. */
        public readonly float $initial_position,
        /** Initial pendulum angle (rad). Any finite value. */
        public readonly float $initial_angle,
        /** Simulation duration (s). Must be in (0, 30]. */
        public readonly float $duration_seconds,
        /** Time step (s). Must be in [0.001, 0.5]. */
        public readonly float $step_size,
    ) {}
}
