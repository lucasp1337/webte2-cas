<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class ExecuteOctaveCommandRequest extends FormRequest
{
    /** Laravel session key under which the per-browser console session id lives. */
    public const string SESSION_KEY = 'console_session_id';

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
            'command' => ['required', 'string', 'max:4096'],
        ];
    }

    /**
     * Returns the per-browser console session id from the Laravel session,
     * generating and persisting a fresh UUID on first call.
     */
    public function consoleSessionId(): string
    {
        $session = $this->session();

        $existing = $session->get(self::SESSION_KEY);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $fresh = (string) Str::uuid();
        $session->put(self::SESSION_KEY, $fresh);

        return $fresh;
    }
}
