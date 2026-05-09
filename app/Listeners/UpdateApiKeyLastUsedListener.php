<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ApiKeyUsed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class UpdateApiKeyLastUsedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $timeout = 30;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function handle(ApiKeyUsed $event): void
    {
        $event->apiKey->forceFill(['last_used_at' => now()])->save();
    }

    public function failed(ApiKeyUsed $event, Throwable $exception): void
    {
        // Do not log the api_key plaintext — only the id and prefix are safe.
        Log::error('api-key: failed to update last_used_at', [
            'api_key_id' => $event->apiKey->id,
            'api_key_prefix' => $event->apiKey->key_prefix,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
