<?php

declare(strict_types=1);

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

it('creates an api key, prints the plaintext once, and exits with success', function (): void {
    $command = artisan('cas:create-api-key', ['name' => 'demo client']);

    assert($command instanceof PendingCommand);

    $command->assertExitCode(0)
        ->expectsOutputToContain('webte2_')
        ->expectsOutputToContain('Store this key now');
});

it('persists the api key to the database with a hashed key_hash', function (): void {
    Artisan::call('cas:create-api-key', ['name' => 'integration test']);

    /** @var ApiKey $key */
    $key = ApiKey::query()->where('name', 'integration test')->first();

    expect($key)->not->toBeNull()
        ->and($key->key_hash)->not->toStartWith('webte2_')
        ->and($key->key_prefix)->toStartWith('webte2_');
});

it('the created key can be verified to exist in the database', function (): void {
    Artisan::call('cas:create-api-key', ['name' => 'lookup test']);

    $key = ApiKey::query()->where('name', 'lookup test')->first();

    expect($key)->not->toBeNull();
});
