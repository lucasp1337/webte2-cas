<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ApiKey;

final class ApiKeyObserver
{
    public function creating(ApiKey $apiKey): void
    {
        // key_hash is expected to hold the plaintext at this point;
        // the observer replaces it with the HMAC digest and fills the prefix.
        $plaintext = $apiKey->key_hash;

        /** @var string $appKey */
        $appKey = config('app.key');

        $apiKey->key_hash = hash_hmac('sha256', $plaintext, $appKey);

        // Access raw attributes to avoid PHPStan's "always false" on null check.
        $attrs = $apiKey->getAttributes();
        if (! isset($attrs['key_prefix']) || $attrs['key_prefix'] === '') {
            $apiKey->key_prefix = substr($plaintext, 0, 8);
        }
    }

    public function updating(ApiKey $apiKey): void
    {
        if ($apiKey->isDirty('key_hash')) {
            throw new \LogicException('api_key.key_hash is immutable after creation');
        }

        if ($apiKey->isDirty('key_prefix')) {
            throw new \LogicException('api_key.key_prefix is immutable after creation');
        }
    }
}
