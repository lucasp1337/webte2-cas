<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * Input parameters for the inverted-pendulum simulation.
 *
 * Field names mirror the Octave script variable names used in
 * resources/views/octave/pendulum.blade.php so that the template
 * can reference $p->M, $p->m, etc. directly.
 *
 * JSON keys expected from the API caller (snake_case):
 *   M, m, b, I, g, l, r, init_position, init_angle, t_end, dt
 */
final class PendulumParameters extends Data
{
    public function __construct(
        /** Cart mass (kg). Must be > 0. */
        public readonly float $M,
        /** Pendulum mass (kg). Must be > 0. */
        public readonly float $m,
        /** Friction coefficient. Must be ≥ 0. */
        public readonly float $b,
        /** Moment of inertia (kg·m²). Must be > 0. */
        public readonly float $I,
        /** Gravitational acceleration (m/s²). Must be > 0. */
        public readonly float $g,
        /** Pendulum length (m). Must be > 0. */
        public readonly float $l,
        /** Reference cart position (m). Any finite value. */
        public readonly float $r,
        /** Initial cart position (m). Any finite value. */
        public readonly float $init_position,
        /** Initial pendulum angle (rad). Any finite value. */
        public readonly float $init_angle,
        /** Simulation duration (s). Must be in (0, 30]. */
        public readonly float $t_end,
        /** Time step (s). Must be in [0.001, 0.5]. */
        public readonly float $dt,
    ) {}
}
