<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\ValidBallBeamParameters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class RunBallBeamSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentication is handled upstream by ApiKeyMiddleware.
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // The composite rule does deep validation (finite checks, bounds).
            // The per-field dot-notation rules below give Scramble enough
            // type info to render `parameters` as a structured object in the
            // OpenAPI spec — without them it falls back to `array of string`.
            'parameters' => ['required', 'array', new ValidBallBeamParameters],
            'parameters.ball_mass' => ['required', 'numeric'],
            'parameters.ball_radius' => ['required', 'numeric'],
            'parameters.inertia' => ['required', 'numeric'],
            'parameters.gravity' => ['required', 'numeric'],
            'parameters.beam_length' => ['required', 'numeric'],
            'parameters.reference_position' => ['required', 'numeric'],
            'parameters.initial_position' => ['required', 'numeric'],
            'parameters.initial_velocity' => ['required', 'numeric'],
            'parameters.initial_angle' => ['required', 'numeric'],
            'parameters.duration_seconds' => ['required', 'numeric'],
            'parameters.step_size' => ['required', 'numeric'],
            'continue_from' => ['nullable', 'array', 'size:4'],
            // Laravel's `numeric` rule accepts the strings "Infinity", "INF",
            // "NAN" and PHP's INF/NAN floats; sprintf turns them into valid
            // Octave literals which then poison the simulation. Reject here.
            'continue_from.*' => ['numeric', $this->finiteRule()],
        ];
    }

    private function finiteRule(): ValidationRule
    {
        return new class implements ValidationRule
        {
            public function validate(string $attribute, mixed $value, \Closure $fail): void
            {
                if (! is_numeric($value) || ! is_finite((float) $value)) {
                    $fail("{$attribute} must be a finite number.");
                }
            }
        };
    }
}
