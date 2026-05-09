<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class RunBallBeamSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth is handled by ApiKeyMiddleware.
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'parameters' => ['required', 'array'],
            'parameters.ball_mass' => ['required', 'numeric', 'gt:0'],
            'parameters.ball_radius' => ['required', 'numeric', 'gt:0'],
            'parameters.beam_length' => ['required', 'numeric', 'gt:0'],
            'parameters.gravity' => ['required', 'numeric', 'gt:0'],
            'parameters.initial_position' => ['required', 'numeric'],
            'parameters.initial_velocity' => ['required', 'numeric'],
            'parameters.duration_seconds' => ['required', 'numeric', 'gt:0', 'max:60'],
            'parameters.step_size' => ['required', 'numeric', 'gt:0', 'max:1'],
            'control' => ['nullable', 'array'],
            'control.kind' => ['nullable', 'string', 'in:lqr,pid,none'],
            'control.kp' => ['nullable', 'numeric'],
            'control.ki' => ['nullable', 'numeric'],
            'control.kd' => ['nullable', 'numeric'],
        ];
    }
}
