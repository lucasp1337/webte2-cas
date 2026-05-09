<?php

declare(strict_types=1);

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('hashes the plaintext key on creating', function (): void {
    $plaintext = 'webte2_'.str_repeat('d', 48);

    $apiKey = ApiKey::create(['name' => 'test', 'key_hash' => $plaintext]);

    /** @var string $appKey */
    $appKey = config('app.key');
    $expected = hash_hmac('sha256', $plaintext, $appKey);

    expect($apiKey->key_hash)->not->toBe($plaintext)
        ->and($apiKey->key_hash)->toBe($expected);
});

it('sets the key_prefix to the first 8 characters of the plaintext', function (): void {
    $plaintext = 'webte2_e'.str_repeat('f', 47);

    $apiKey = ApiKey::create(['name' => 'test', 'key_hash' => $plaintext]);

    expect($apiKey->key_prefix)->toBe('webte2_e');
});

it('rejects updates that mutate key_hash', function (): void {
    $plaintext = 'webte2_'.str_repeat('g', 48);
    $apiKey = ApiKey::create(['name' => 'test', 'key_hash' => $plaintext]);

    expect(fn () => $apiKey->update(['key_hash' => 'tampered']))->toThrow(LogicException::class);
});

it('rejects updates that mutate key_prefix', function (): void {
    $plaintext = 'webte2_'.str_repeat('h', 48);
    $apiKey = ApiKey::create(['name' => 'test', 'key_hash' => $plaintext]);

    expect(fn () => $apiKey->update(['key_prefix' => 'tampered']))->toThrow(LogicException::class);
});
