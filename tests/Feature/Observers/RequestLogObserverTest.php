<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\RequestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('assigns a ULID to id automatically via HasUlids', function (): void {
    $plaintext = 'webte2_'.str_repeat('i', 48);
    $apiKey = ApiKey::create(['name' => 'test', 'key_hash' => $plaintext]);

    /** @var string $appKey */
    $appKey = config('app.key');

    $log = RequestLog::create([
        'api_key_id' => $apiKey->id,
        'method' => 'GET',
        'route' => 'logs.index',
        'path' => '/api/v1/logs',
        'status' => 200,
        'success' => true,
        'duration_ms' => 50,
        'ip_hash' => hash_hmac('sha256', '127.0.0.1', $appKey),
    ]);

    expect($log->id)->not->toBeNull()
        ->and($log->id)->toBeString()
        ->and(strlen($log->id))->toBeGreaterThan(0);
});

it('preserves a manually set id', function (): void {
    $plaintext = 'webte2_'.str_repeat('j', 48);
    $apiKey = ApiKey::create(['name' => 'test', 'key_hash' => $plaintext]);

    /** @var string $appKey */
    $appKey = config('app.key');

    // HasUlids validates that manually provided IDs are valid ULIDs.
    $customId = strtolower((string) Str::ulid());

    $log = RequestLog::create([
        'id' => $customId,
        'api_key_id' => $apiKey->id,
        'method' => 'POST',
        'route' => 'octave.exec',
        'path' => '/api/v1/octave/exec',
        'status' => 200,
        'success' => true,
        'duration_ms' => 120,
        'ip_hash' => hash_hmac('sha256', '127.0.0.1', $appKey),
    ]);

    expect($log->id)->toBe($customId);
});

it('defaults created_at to now when none is provided', function (): void {
    $plaintext = 'webte2_'.str_repeat('k', 48);
    $apiKey = ApiKey::create(['name' => 'test', 'key_hash' => $plaintext]);

    /** @var string $appKey */
    $appKey = config('app.key');

    $before = now()->subSecond();

    $log = RequestLog::create([
        'api_key_id' => $apiKey->id,
        'method' => 'DELETE',
        'route' => 'octave.clear-session',
        'path' => '/api/v1/octave/session',
        'status' => 204,
        'success' => true,
        'duration_ms' => 10,
        'ip_hash' => hash_hmac('sha256', '127.0.0.1', $appKey),
    ]);

    expect($log->created_at)->not->toBeNull()
        ->and($log->created_at->greaterThanOrEqualTo($before))->toBeTrue();
});
