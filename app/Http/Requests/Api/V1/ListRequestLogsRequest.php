<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListRequestLogsRequest extends FormRequest
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
            'api_key_id' => ['nullable', 'ulid', Rule::exists('api_keys', 'id')],
            'route' => ['nullable', 'string', 'max:128'],
            'status' => ['nullable', 'integer', 'between:100,599'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ];
    }
}
