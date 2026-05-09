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
function makePollTestApiKey(string $suffix): array
{
    $plaintext = 'webte2_'.str_pad($suffix, 48, 'p');
    $apiKey = ApiKey::create(['name' => "poll-{$suffix}", 'key_hash' => $plaintext]);

    return [$apiKey, $plaintext];
}

it('returns 404 for unknown exportId', function (): void {
    [, $plaintext] = makePollTestApiKey('q404test');
    $fakeId = Str::ulid()->toBase32();

    getJson("/api/v1/logs/export.csv/{$fakeId}", ['X-API-Key' => $plaintext])
        ->assertStatus(404)
        ->assertJsonPath('error', 'not_found');
});

it('returns 202 with status while running', function (): void {
    [, $plaintext] = makePollTestApiKey('rrunning0');
    $exportId = Str::ulid()->toBase32();

    Cache::put("csv_export:{$exportId}", ['status' => 'running', 'filters' => []], now()->addHour());

    getJson("/api/v1/logs/export.csv/{$exportId}", ['X-API-Key' => $plaintext])
        ->assertStatus(202)
        ->assertJsonPath('status', 'running')
        ->assertJsonPath('job_id', $exportId);
});

it('streams the file when done', function (): void {
    [, $plaintext] = makePollTestApiKey('sdone0000');
    $exportId = Str::ulid()->toBase32();

    $dir = storage_path('app/exports');

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $csvContent = "request_id,method,route\nfake-id,GET,/api/v1/logs\n";
    file_put_contents($dir."/{$exportId}.csv", $csvContent);

    Cache::put("csv_export:{$exportId}", ['status' => 'done', 'filters' => []], now()->addHour());

    $response = get("/api/v1/logs/export.csv/{$exportId}", ['X-API-Key' => $plaintext]);

    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    // Cleanup
    unlink($dir."/{$exportId}.csv");
});
