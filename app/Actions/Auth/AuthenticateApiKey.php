<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\ApiKey;
use App\Support\ApiKeyHasher;

/**
 * Resolves an `X-API-Key` plaintext to its `ApiKey` model, or null if none
 * matches. Callers (the middleware) do not see the hashing primitives —
 * the {@see ApiKeyHasher} service owns those.
 */
final readonly class AuthenticateApiKey
{
    public function __construct(private ApiKeyHasher $hasher) {}

    public function handle(string $plaintext): ?ApiKey
    {
        $candidate = ApiKey::query()
            ->active()
            ->where('key_prefix', $this->hasher->prefixOf($plaintext))
            ->first();

        if ($candidate === null) {
            return null;
        }

        return $this->hasher->verify($plaintext, $candidate->key_hash)
            ? $candidate
            : null;
    }
}
