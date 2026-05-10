<?php

declare(strict_types=1);

namespace App\Rules;

use App\Data\PendulumParameters;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates the full set of inverted-pendulum simulation parameters.
 *
 * Applied at the top-level 'parameters' array so that the error message
 * identifies each offending field individually rather than producing a
 * generic "invalid parameters" rejection.
 *
 * Physical bounds (locked via phase-06-plan.md):
 *   M   > 0     (cart mass)
 *   m   > 0     (pendulum mass)
 *   b   ≥ 0     (friction — can be frictionless)
 *   I   > 0     (moment of inertia)
 *   g   > 0     (gravity — must be non-zero for the linearisation to hold)
 *   l   > 0     (rod length)
 *   r   finite  (reference position — negative values valid)
 *   init_position  finite
 *   init_angle     finite
 *   t_end ∈ (0, 30]
 *   dt    ∈ [0.001, 0.5]
 */
final class ValidPendulumParameters implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('Parameters must be an object.');

            return;
        }

        try {
            $p = PendulumParameters::from($value);
        } catch (\Throwable) {
            $fail('Parameters could not be parsed. Ensure all required fields are present and numeric.');

            return;
        }

        $this->checkFinite('M', $p->M, $fail);
        $this->checkFinite('m', $p->m, $fail);
        $this->checkFinite('b', $p->b, $fail);
        $this->checkFinite('I', $p->I, $fail);
        $this->checkFinite('g', $p->g, $fail);
        $this->checkFinite('l', $p->l, $fail);
        $this->checkFinite('r', $p->r, $fail);
        $this->checkFinite('init_position', $p->init_position, $fail);
        $this->checkFinite('init_angle', $p->init_angle, $fail);
        $this->checkFinite('t_end', $p->t_end, $fail);
        $this->checkFinite('dt', $p->dt, $fail);

        if ($p->M <= 0) {
            $fail('Cart mass M must be greater than 0.');
        }

        if ($p->m <= 0) {
            $fail('Pendulum mass m must be greater than 0.');
        }

        if ($p->b < 0) {
            $fail('Friction coefficient b must be 0 or greater.');
        }

        if ($p->I <= 0) {
            $fail('Moment of inertia I must be greater than 0.');
        }

        if ($p->g <= 0) {
            $fail('Gravitational acceleration g must be greater than 0.');
        }

        if ($p->l <= 0) {
            $fail('Pendulum length l must be greater than 0.');
        }

        if ($p->t_end <= 0 || $p->t_end > 30) {
            $fail('Simulation duration t_end must be in the range (0, 30].');
        }

        if ($p->dt < 0.001 || $p->dt > 0.5) {
            $fail('Time step dt must be in the range [0.001, 0.5].');
        }
    }

    private function checkFinite(string $name, float $value, Closure $fail): void
    {
        if (! is_finite($value)) {
            $fail("Parameter {$name} must be a finite number (NaN and ±Inf are not allowed).");
        }
    }
}
