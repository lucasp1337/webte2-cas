<?php

declare(strict_types=1);

use App\Events\ApiKeyUsed;
use App\Listeners\UpdateApiKeyLastUsedListener;
use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('updates last_used_at when handle is invoked directly', function (): void {
    $plaintext = 'webte2_'.str_repeat('c', 48);
    $apiKey = ApiKey::create(['name' => 'test', 'key_hash' => $plaintext]);

    expect($apiKey->last_used_at)->toBeNull();

    $event = new ApiKeyUsed($apiKey);
    $listener = new UpdateApiKeyLastUsedListener;
    $listener->handle($event);

    /** @var ApiKey $fresh */
    $fresh = $apiKey->fresh();

    $lastUsedAt = $fresh->last_used_at;

    expect($lastUsedAt)->not->toBeNull();
    assert($lastUsedAt instanceof Carbon);
    expect($lastUsedAt->diffInSeconds(now()))->toBeLessThanOrEqual(5);
});
