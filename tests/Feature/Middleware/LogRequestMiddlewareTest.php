<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\RequestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Register test-only routes under the api-protected group.
    Route::middleware('api-protected')
        ->get('/_test/log', fn () => response()->json(['ok' => true]))
        ->name('_test.log');

    Route::middleware('api-protected')
        ->post('/_test/exec', fn () => response()->json(['ok' => true]))
        ->name('_test.exec');
});

it('writes a RequestLog row for every protected request', function (): void {
    $plaintext = 'webte2_'.str_repeat('l', 48);
    $apiKey = ApiKey::create(['name' => 'log-test', 'key_hash' => $plaintext]);

    getJson('/_test/log', ['X-API-Key' => $plaintext])
        ->assertStatus(200)
        ->assertHeader('X-Request-Id');

    /** @var RequestLog $log */
    $log = RequestLog::query()->latest('created_at')->first();

    expect($log->method)->toBe('GET')
        ->and($log->api_key_id)->toBe($apiKey->id)
        ->and($log->status)->toBe(200)
        ->and($log->success)->toBeTrue()
        ->and($log->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('sets the X-Request-Id response header to the log row id', function (): void {
    $plaintext = 'webte2_'.str_repeat('m', 48);
    ApiKey::create(['name' => 'log-test-2', 'key_hash' => $plaintext]);

    $response = getJson('/_test/log', ['X-API-Key' => $plaintext]);

    $requestId = $response->headers->get('X-Request-Id');

    expect($requestId)->not->toBeNull();

    $log = RequestLog::query()->where('id', $requestId)->first();
    expect($log)->not->toBeNull();
});

it('truncates command to 1024 characters in the log', function (): void {
    $plaintext = 'webte2_'.str_repeat('n', 48);
    ApiKey::create(['name' => 'log-test-3', 'key_hash' => $plaintext]);

    $longCommand = str_repeat('x', 2000);

    postJson('/_test/exec', ['command' => $longCommand], ['X-API-Key' => $plaintext]);

    /** @var RequestLog $log */
    $log = RequestLog::query()->latest('created_at')->first();

    expect($log->command)->not->toBeNull()
        ->and(strlen((string) $log->command))->toBe(1024);
});

it('hashes the ip using STATS_ANON_TOKEN_SALT', function (): void {
    $salt = 'test-salt-for-hashing';
    config()->set('cas.stats_anon_token_salt', $salt);

    $plaintext = 'webte2_'.str_repeat('o', 48);
    ApiKey::create(['name' => 'log-test-4', 'key_hash' => $plaintext]);

    getJson('/_test/log', ['X-API-Key' => $plaintext]);

    /** @var RequestLog $log */
    $log = RequestLog::query()->latest('created_at')->first();

    $expected = hash_hmac('sha256', '127.0.0.1', $salt);

    expect($log->ip_hash)->toBe($expected);
});
