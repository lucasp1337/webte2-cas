<?php

declare(strict_types=1);

use App\Actions\Auth\AuthenticateApiKey;
use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the matching api key for a valid plaintext', function (): void {
    $plaintext = 'webte2_'.str_repeat('a', 48);
    ApiKey::factory()->create(['key_hash' => $plaintext]);

    /** @var AuthenticateApiKey $action */
    $action = app(AuthenticateApiKey::class);

    expect($action->handle($plaintext))->toBeInstanceOf(ApiKey::class);
});

it('returns null when no key matches the prefix', function (): void {
    /** @var AuthenticateApiKey $action */
    $action = app(AuthenticateApiKey::class);

    expect($action->handle('webte2_unknown'))->toBeNull();
});

it('returns null when the prefix matches but the hash does not', function (): void {
    // Two plaintexts sharing the first 8 chars (`webte2_a`) but differing later.
    $first = 'webte2_a'.str_repeat('1', 48);
    $second = 'webte2_a'.str_repeat('2', 48);
    ApiKey::factory()->create(['key_hash' => $first]);

    /** @var AuthenticateApiKey $action */
    $action = app(AuthenticateApiKey::class);

    expect($action->handle($second))->toBeNull();
});

it('returns null when the matching key has been revoked', function (): void {
    $plaintext = 'webte2_'.str_repeat('c', 48);
    ApiKey::factory()->create([
        'key_hash' => $plaintext,
        'revoked_at' => now(),
    ]);

    /** @var AuthenticateApiKey $action */
    $action = app(AuthenticateApiKey::class);

    expect($action->handle($plaintext))->toBeNull();
});
