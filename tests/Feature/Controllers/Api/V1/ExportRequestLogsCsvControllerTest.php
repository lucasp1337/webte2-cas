<?php

declare(strict_types=1);

use App\Jobs\GenerateLargeCsvExportJob;
use App\Models\ApiKey;
use App\Models\RequestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('streams CSV inline for small datasets', function (): void {
    $plaintext = 'webte2_'.str_repeat('e', 48);
    $apiKey = ApiKey::create(['name' => 'csv-small', 'key_hash' => $plaintext]);

    RequestLog::factory()->count(5)->create(['api_key_id' => $apiKey->id]);

    $response = get('/api/v1/logs/export.csv', ['X-API-Key' => $plaintext]);

    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $content = $response->streamedContent();
    $lines = array_filter(explode("\n", trim((string) $content)));

    // Header + seeded rows (middleware also logs the export request itself).
    expect(count($lines))->toBeGreaterThanOrEqual(6)
        ->and((string) array_values($lines)[0])->toContain('request_id');
});

it('queues a job for large datasets', function (): void {
    Queue::fake();

    config()->set('cas.csv_export_threshold', 5);

    $plaintext = 'webte2_'.str_repeat('f', 48);
    $apiKey = ApiKey::create(['name' => 'csv-large', 'key_hash' => $plaintext]);

    RequestLog::factory()->count(10)->create(['api_key_id' => $apiKey->id]);

    $response = getJson('/api/v1/logs/export.csv', ['X-API-Key' => $plaintext]);

    $response->assertStatus(202)
        ->assertJsonStructure(['job_id', 'status', 'poll_url'])
        ->assertJsonPath('status', 'queued');

    Queue::assertPushed(GenerateLargeCsvExportJob::class, function (GenerateLargeCsvExportJob $job) use ($response): bool {
        return $job->exportId === $response->json('job_id');
    });
});
