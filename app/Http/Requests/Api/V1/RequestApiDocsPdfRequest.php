<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class RequestApiDocsPdfRequest extends FormRequest
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
            'locale' => ['nullable', 'string', 'in:sk,en'],
        ];
    }

    public function locale(): string
    {
        /** @var string|null $locale */
        $locale = $this->query('locale');

        return $locale ?? app()->getLocale();
    }
}
