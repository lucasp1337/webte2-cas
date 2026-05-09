<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ClearOctaveSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth is handled by ApiKeyMiddleware.
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
        ];
    }
}
