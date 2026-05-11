<?php

declare(strict_types=1);

use App\Jobs\GenerateApiDocsPdfJob;
use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

/**
 * @return array{0: ApiKey, 1: string}
 */
function makePdfRequestApiKey(string $suffix): array
{
    $plaintext = 'webte2_'.str_pad($suffix, 48, 'r');
    $apiKey = ApiKey::create(['name' => "pdf-req-{$suffix}", 'key_hash' => $plaintext]);

    return [$apiKey, $plaintext];
}

it('is public and accepts requests without an api key', function (): void {
    // The pdf endpoints sit alongside /api/openapi.json — both are public
    // documentation routes. The page that consumes them is anonymous and
    // we deliberately don't ship the seeded api key to the browser.
    Queue::fake();

    postJson('/api/v1/api-docs/pdf')
        ->assertStatus(202)
        ->assertJsonStructure(['export_id', 'status', 'poll_url']);
});

it('returns 202 and enqueues the job on a valid request', function (): void {
    Queue::fake();

    [, $plaintext] = makePdfRequestApiKey('happy00');

    $response = postJson('/api/v1/api-docs/pdf', [], ['X-API-Key' => $plaintext]);

    $response->assertStatus(202)
        ->assertJsonPath('status', 'queued')
        ->assertJsonStructure(['export_id', 'status', 'poll_url']);

    $exportId = $response->json('export_id');

    Queue::assertPushed(GenerateApiDocsPdfJob::class, function (GenerateApiDocsPdfJob $job) use ($exportId): bool {
        return $job->exportId === $exportId;
    });
});

it('returns 422 for an invalid locale', function (): void {
    Queue::fake();

    [, $plaintext] = makePdfRequestApiKey('422test0');

    postJson('/api/v1/api-docs/pdf?locale=fr', [], ['X-API-Key' => $plaintext])
        ->assertStatus(422)
        ->assertJsonPath('errors.locale.0', fn (string $msg): bool => str_contains($msg, 'locale') || str_contains($msg, 'in'));
});

it('uses the application locale when locale param is omitted', function (): void {
    Queue::fake();

    [, $plaintext] = makePdfRequestApiKey('deflocale');

    $defaultLocale = app()->getLocale();

    postJson('/api/v1/api-docs/pdf', [], ['X-API-Key' => $plaintext])
        ->assertStatus(202);

    Queue::assertPushed(GenerateApiDocsPdfJob::class, function (GenerateApiDocsPdfJob $job) use ($defaultLocale): bool {
        return $job->locale === $defaultLocale;
    });
});

it('dispatches job with the requested locale', function (): void {
    Queue::fake();

    [, $plaintext] = makePdfRequestApiKey('localsk0');

    postJson('/api/v1/api-docs/pdf?locale=sk', [], ['X-API-Key' => $plaintext])
        ->assertStatus(202);

    Queue::assertPushed(GenerateApiDocsPdfJob::class, function (GenerateApiDocsPdfJob $job): bool {
        return $job->locale === 'sk';
    });
});

it('response body includes export_id, status queued, and poll_url', function (): void {
    Queue::fake();

    [, $plaintext] = makePdfRequestApiKey('respbody0');

    $response = postJson('/api/v1/api-docs/pdf?locale=en', [], ['X-API-Key' => $plaintext]);

    $response->assertStatus(202);

    /** @var array{export_id: string, status: string, poll_url: string} $body */
    $body = $response->json();

    expect($body)->toHaveKeys(['export_id', 'status', 'poll_url'])
        ->and($body['status'])->toBe('queued')
        ->and($body['poll_url'])->toContain($body['export_id']);
});
