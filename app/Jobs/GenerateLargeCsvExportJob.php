<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ApiKey;
use App\Models\RequestLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class GenerateLargeCsvExportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 1800;

    /**
     * @param  array{from?: string|null, to?: string|null, route?: string|null}  $filters
     */
    public function __construct(
        public readonly string $exportId,
        public readonly array $filters,
    ) {}

    public function uniqueId(): string
    {
        return $this->exportId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        Cache::put(
            "csv_export:{$this->exportId}",
            ['status' => 'running', 'filters' => $this->filters],
            now()->addHours(24),
        );

        $dir = storage_path('app/exports');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir."/{$this->exportId}.csv";
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Could not open export file for writing: {$path}");
        }

        fputcsv($handle, ['request_id', 'method', 'route', 'status', 'duration_ms', 'api_key_prefix', 'created_at']);

        $query = RequestLog::query()->with('apiKey:id,key_prefix')->orderBy('id');

        $fromRaw = $this->filters['from'] ?? null;
        $toRaw = $this->filters['to'] ?? null;
        $routeFilter = $this->filters['route'] ?? null;

        if ($routeFilter !== null) {
            $query->forRoute($routeFilter);
        }

        if ($fromRaw !== null || $toRaw !== null) {
            $query->betweenDates(
                $fromRaw !== null ? Carbon::parse($fromRaw) : null,
                $toRaw !== null ? Carbon::parse($toRaw) : null,
            );
        }

        $query->lazyById(500)->each(function (RequestLog $log) use ($handle): void {
            /** @var ApiKey|null $relatedKey */
            $relatedKey = $log->getRelation('apiKey');
            fputcsv($handle, [
                (string) $log->id,
                (string) $log->method,
                (string) $log->path,
                (string) $log->status,
                (string) $log->duration_ms,
                (string) ($relatedKey !== null ? $relatedKey->key_prefix : ''),
                (string) $log->created_at->toIso8601String(),
            ]);
        });

        fclose($handle);

        Cache::put(
            "csv_export:{$this->exportId}",
            ['status' => 'done', 'filters' => $this->filters],
            now()->addHours(24),
        );
    }

    public function failed(Throwable $exception): void
    {
        Cache::put(
            "csv_export:{$this->exportId}",
            [
                'status' => 'failed',
                'filters' => $this->filters,
                'error' => $exception->getMessage(),
            ],
            now()->addHours(24),
        );

        Log::error('csv export job failed', [
            'export_id' => $this->exportId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
