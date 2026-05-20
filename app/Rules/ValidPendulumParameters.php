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
 * Physical bounds:
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

        $this->checkFinite('cart_mass', $p->cart_mass, $fail);
        $this->checkFinite('pendulum_mass', $p->pendulum_mass, $fail);
        $this->checkFinite('friction', $p->friction, $fail);
        $this->checkFinite('inertia', $p->inertia, $fail);
        $this->checkFinite('gravity', $p->gravity, $fail);
        $this->checkFinite('length', $p->length, $fail);
        $this->checkFinite('reference_position', $p->reference_position, $fail);
        $this->checkFinite('initial_position', $p->initial_position, $fail);
        $this->checkFinite('initial_angle', $p->initial_angle, $fail);
        $this->checkFinite('duration_seconds', $p->duration_seconds, $fail);
        $this->checkFinite('step_size', $p->step_size, $fail);

        if ($p->cart_mass <= 0) {
            $fail('cart_mass must be greater than 0.');
        }

        if ($p->pendulum_mass <= 0) {
            $fail('pendulum_mass must be greater than 0.');
        }

        if ($p->friction < 0) {
            $fail('friction must be 0 or greater.');
        }

        if ($p->inertia <= 0) {
            $fail('inertia must be greater than 0.');
        }

        if ($p->gravity <= 0) {
            $fail('gravity must be greater than 0.');
        }

        if ($p->length <= 0) {
            $fail('length must be greater than 0.');
        }

        if ($p->duration_seconds <= 0 || $p->duration_seconds > 30) {
            $fail('duration_seconds must be in the range (0, 30].');
        }

        if ($p->step_size < 0.001 || $p->step_size > 0.5) {
            $fail('step_size must be in the range [0.001, 0.5].');
        }
    }

    private function checkFinite(string $name, float $value, Closure $fail): void
    {
        if (! is_finite($value)) {
            $fail("Parameter {$name} must be a finite number (NaN and ±Inf are not allowed).");
        }
    }
}
