<?php

declare(strict_types=1);

namespace App\Rules;

use App\Data\BallBeamParameters;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates the full set of ball-on-beam simulation parameters.
 *
 * Applied at the top-level 'parameters' array so that the error message
 * identifies each offending field individually rather than producing a
 * generic "invalid parameters" rejection.
 *
 * Physical bounds:
 *   ball_mass   ∈ (0, 100)   kg
 *   ball_radius ∈ (0, 1)     m
 *   inertia     ∈ (0, 1)     kg·m²
 *   beam_length ∈ (0, 10)    m
 *   gravity     ∈ (0, 30)    m/s²
 *   reference_position  finite  (negative values valid)
 *   initial_position    finite
 *   initial_velocity    finite
 *   initial_angle       finite
 *   duration_seconds ∈ (0, 30]
 *   step_size        ∈ [0.001, 0.5]
 */
final class ValidBallBeamParameters implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('Parameters must be an object.');

            return;
        }

        try {
            $p = BallBeamParameters::from($value);
        } catch (\Throwable) {
            $fail('Parameters could not be parsed. Ensure all required fields are present and numeric.');

            return;
        }

        $this->checkFinite('ball_mass', $p->ball_mass, $fail);
        $this->checkFinite('ball_radius', $p->ball_radius, $fail);
        $this->checkFinite('inertia', $p->inertia, $fail);
        $this->checkFinite('beam_length', $p->beam_length, $fail);
        $this->checkFinite('gravity', $p->gravity, $fail);
        $this->checkFinite('reference_position', $p->reference_position, $fail);
        $this->checkFinite('initial_position', $p->initial_position, $fail);
        $this->checkFinite('initial_velocity', $p->initial_velocity, $fail);
        $this->checkFinite('initial_angle', $p->initial_angle, $fail);
        $this->checkFinite('duration_seconds', $p->duration_seconds, $fail);
        $this->checkFinite('step_size', $p->step_size, $fail);

        if ($p->ball_mass <= 0 || $p->ball_mass >= 100) {
            $fail('ball_mass must be in the range (0, 100).');
        }

        if ($p->ball_radius <= 0 || $p->ball_radius >= 1) {
            $fail('ball_radius must be in the range (0, 1).');
        }

        if ($p->inertia <= 0 || $p->inertia >= 1) {
            $fail('inertia must be in the range (0, 1).');
        }

        if ($p->beam_length <= 0 || $p->beam_length >= 10) {
            $fail('beam_length must be in the range (0, 10).');
        }

        if ($p->gravity <= 0 || $p->gravity >= 30) {
            $fail('gravity must be in the range (0, 30).');
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
