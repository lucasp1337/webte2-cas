<?php

declare(strict_types=1);

use App\Jobs\GenerateLargeCsvExportJob;
use App\Models\ApiKey;
use App\Models\RequestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('writes a complete csv file and marks the cache state done', function (): void {
    $apiKey = ApiKey::create(['name' => 'job-test', 'key_hash' => 'webte2_'.str_repeat('j', 48)]);
    RequestLog::factory()->count(5)->create(['api_key_id' => $apiKey->id]);

    $exportId = Str::ulid()->toBase32();

    $job = new GenerateLargeCsvExportJob($exportId, []);
    $job->handle();

    $path = storage_path("app/exports/{$exportId}.csv");

    expect(file_exists($path))->toBeTrue();

    $lines = array_filter(explode("\n", trim((string) file_get_contents($path))));

    // Header + 5 rows
    expect(count($lines))->toBe(6)
        ->and((string) array_values($lines)[0])->toContain('request_id');

    /** @var array{status: string} $state */
    $state = Cache::get("csv_export:{$exportId}");

    expect($state['status'])->toBe('done');

    // Cleanup
    unlink($path);
});

it('logs an error and marks the cache state failed when failed() is called', function (): void {
    $exportId = Str::ulid()->toBase32();

    Cache::put("csv_export:{$exportId}", ['status' => 'queued', 'filters' => []], now()->addHour());

    $job = new GenerateLargeCsvExportJob($exportId, []);

    $exception = new RuntimeException('Test failure');
    $job->failed($exception);

    /** @var array{status: string, error: string} $state */
    $state = Cache::get("csv_export:{$exportId}");

    expect($state['status'])->toBe('failed')
        ->and($state['error'])->toBe('Test failure');
});
