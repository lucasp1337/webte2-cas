<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\ValidPendulumParameters;
use Illuminate\Foundation\Http\FormRequest;

final class RunPendulumSimulationRequest extends FormRequest
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
            'parameters' => ['required', 'array', new ValidPendulumParameters],
            'continue_from' => ['nullable', 'array', 'size:4'],
            'continue_from.*' => ['numeric'],
        ];
    }
}
