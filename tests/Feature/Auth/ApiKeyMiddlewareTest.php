<?php

declare(strict_types=1);

use App\Events\ApiKeyUsed;
use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Register a minimal protected test route so middleware under test runs
    // in isolation, without depending on any real controller.
    Route::middleware('api-protected')
        ->get('/_test/protected', fn () => response()->json(['ok' => true]))
        ->name('_test.protected');
});

it('rejects requests without X-API-Key with 401', function (): void {
    getJson('/_test/protected')->assertStatus(401);
});

it('rejects requests with an invalid X-API-Key with 401', function (): void {
    getJson('/_test/protected', ['X-API-Key' => 'webte2_thisisnotavalidkeyatallaaaaaaaaaaaaaaaaaaaaaaaaaa'])
        ->assertStatus(401);
});

it('accepts requests with a valid X-API-Key and dispatches ApiKeyUsed', function (): void {
    Event::fake([ApiKeyUsed::class]);

    $plaintext = 'webte2_'.str_repeat('a', 48);
    $apiKey = ApiKey::create(['name' => 'test', 'key_hash' => $plaintext]);

    getJson('/_test/protected', ['X-API-Key' => $plaintext])->assertStatus(200);

    Event::assertDispatched(ApiKeyUsed::class, function (ApiKeyUsed $event) use ($apiKey): bool {
        return $event->apiKey->id === $apiKey->id;
    });
});

it('does not write to the api_keys table during auth (last_used_at stays null)', function (): void {
    Event::fake([ApiKeyUsed::class]);

    $plaintext = 'webte2_'.str_repeat('b', 48);
    $apiKey = ApiKey::create(['name' => 'test', 'key_hash' => $plaintext]);

    getJson('/_test/protected', ['X-API-Key' => $plaintext]);

    /** @var ApiKey $fresh */
    $fresh = $apiKey->fresh();

    // The listener is queued and faked — last_used_at must still be null.
    expect($fresh->last_used_at)->toBeNull();
});
