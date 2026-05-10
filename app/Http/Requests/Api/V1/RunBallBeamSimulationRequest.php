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
            'parameters' => ['required', 'array', new ValidBallBeamParameters],
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
