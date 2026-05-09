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
        $apiKey->key_hash = hash_hmac('sha256', $plaintext, (string) config('app.key'));

        if ($apiKey->key_prefix === null || $apiKey->key_prefix === '') {
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
