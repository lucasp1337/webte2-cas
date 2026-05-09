<?php

declare(strict_types=1);

use App\Support\ApiKeyHasher;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
});

it('hashes a plaintext deterministically with the same app key', function (): void {
    $hasher = app(ApiKeyHasher::class);

    expect($hasher->hash('webte2_aaaaaaaaaaaaaaaa'))
        ->toBe($hasher->hash('webte2_aaaaaaaaaaaaaaaa'));
});

it('produces different hashes when the app key changes', function (): void {
    $hasher = app(ApiKeyHasher::class);
    $first = $hasher->hash('webte2_aaaaaaaaaaaaaaaa');

    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    /** @var ApiKeyHasher $rebuilt */
    $rebuilt = app()->make(ApiKeyHasher::class);
    $second = $rebuilt->hash('webte2_aaaaaaaaaaaaaaaa');

    expect($first)->not->toBe($second);
});

it('extracts the first 8 characters as the prefix', function (): void {
    $hasher = app(ApiKeyHasher::class);

    expect($hasher->prefixOf('webte2_abcdef'))->toBe('webte2_a');
});

it('verify returns true for the matching plaintext and hash', function (): void {
    $hasher = app(ApiKeyHasher::class);
    $plaintext = 'webte2_'.str_repeat('z', 48);

    expect($hasher->verify($plaintext, $hasher->hash($plaintext)))->toBeTrue();
});

it('verify returns false when the plaintext does not match', function (): void {
    $hasher = app(ApiKeyHasher::class);

    expect($hasher->verify('webte2_other', $hasher->hash('webte2_one')))->toBeFalse();
});
