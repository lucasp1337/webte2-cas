<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\Auth\AuthenticateApiKey;
use App\Observers\ApiKeyObserver;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Single source of truth for API-key hashing primitives.
 *
 * Used by {@see ApiKeyObserver} when persisting a new key,
 * and by {@see AuthenticateApiKey} when verifying an
 * incoming `X-API-Key` header. Models do not call into this class directly —
 * orchestrate through the action / observer.
 */
final readonly class ApiKeyHasher
{
    public const int PREFIX_LENGTH = 8;

    public function __construct(private ConfigRepository $config) {}

    public function hash(string $plaintext): string
    {
        return hash_hmac('sha256', $plaintext, $this->salt());
    }

    public function prefixOf(string $plaintext): string
    {
        return substr($plaintext, 0, self::PREFIX_LENGTH);
    }

    public function verify(string $plaintext, string $expectedHash): bool
    {
        return hash_equals($expectedHash, $this->hash($plaintext));
    }

    private function salt(): string
    {
        $key = $this->config->get('app.key');

        return is_string($key) ? $key : '';
    }
}
