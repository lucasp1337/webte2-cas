<?php

declare(strict_types=1);

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

/**
 * @return array{0: ApiKey, 1: string}
 */
function makePdfDownloadApiKey(string $suffix): array
{
    $plaintext = 'webte2_'.str_pad($suffix, 48, 'd');
    $apiKey = ApiKey::create(['name' => "pdf-dl-{$suffix}", 'key_hash' => $plaintext]);

    return [$apiKey, $plaintext];
}

it('returns 404 for an unknown export id', function (): void {
    [, $plaintext] = makePdfDownloadApiKey('notfound');
    $fakeId = Str::ulid()->toBase32();

    getJson("/api/v1/api-docs/pdf/{$fakeId}", ['X-API-Key' => $plaintext])
        ->assertStatus(404)
        ->assertJsonPath('error', 'not_found');
});

it('returns 202 while the job is running', function (): void {
    [, $plaintext] = makePdfDownloadApiKey('running0');
    $exportId = Str::ulid()->toBase32();

    Cache::put("pdf_export:{$exportId}", [
        'status' => 'running',
        'locale' => 'en',
    ], now()->addHour());

    getJson("/api/v1/api-docs/pdf/{$exportId}", ['X-API-Key' => $plaintext])
        ->assertStatus(202)
        ->assertJsonPath('status', 'running')
        ->assertJsonPath('export_id', $exportId);
});

it('returns 202 while the job is queued', function (): void {
    [, $plaintext] = makePdfDownloadApiKey('queued00');
    $exportId = Str::ulid()->toBase32();

    Cache::put("pdf_export:{$exportId}", [
        'status' => 'queued',
        'locale' => 'en',
    ], now()->addHour());

    getJson("/api/v1/api-docs/pdf/{$exportId}", ['X-API-Key' => $plaintext])
        ->assertStatus(202)
        ->assertJsonPath('status', 'queued');
});

it('streams the pdf file when done', function (): void {
    [, $plaintext] = makePdfDownloadApiKey('donefile');
    $exportId = Str::ulid()->toBase32();

    $dir = storage_path('app/exports');

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdfPath = "{$dir}/{$exportId}.pdf";
    file_put_contents($pdfPath, '%PDF-1.4 fake content for download test');

    Cache::put("pdf_export:{$exportId}", [
        'status' => 'done',
        'locale' => 'en',
        'path' => $pdfPath,
    ], now()->addHour());

    $response = get("/api/v1/api-docs/pdf/{$exportId}", ['X-API-Key' => $plaintext]);

    $response->assertStatus(200);

    expect($response->headers->get('Content-Type'))->toContain('application/pdf');

    // Cleanup
    unlink($pdfPath);
});

it('returns 500 when the job failed', function (): void {
    [, $plaintext] = makePdfDownloadApiKey('failed00');
    $exportId = Str::ulid()->toBase32();

    Cache::put("pdf_export:{$exportId}", [
        'status' => 'failed',
        'locale' => 'en',
        'error' => 'Chromium not found.',
    ], now()->addHour());

    getJson("/api/v1/api-docs/pdf/{$exportId}", ['X-API-Key' => $plaintext])
        ->assertStatus(500)
        ->assertJsonPath('error', 'render_failed')
        ->assertJsonPath('message', 'Chromium not found.');
});
