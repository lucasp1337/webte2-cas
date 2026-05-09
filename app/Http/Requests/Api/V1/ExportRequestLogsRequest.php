<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ExportRequestLogsRequest extends FormRequest
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
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'route' => ['nullable', 'string', 'max:128'],
        ];
    }
}
