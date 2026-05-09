<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExportRequestLogsRequest;
use App\Jobs\GenerateLargeCsvExportJob;
use App\Models\ApiKey;
use App\Models\RequestLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportRequestLogsCsvController extends Controller
{
    public function __invoke(ExportRequestLogsRequest $request): StreamedResponse|JsonResponse
    {
        /** @var string|null $from */
        $from = $request->validated('from');
        /** @var string|null $to */
        $to = $request->validated('to');
        /** @var string|null $route */
        $route = $request->validated('route');

        /** @var array{from: string|null, to: string|null, route: string|null} $filters */
        $filters = ['from' => $from, 'to' => $to, 'route' => $route];

        $count = $this->buildQuery($from, $to, $route)->count();

        /** @var int $threshold */
        $threshold = config('cas.csv_export_threshold');

        if ($count > $threshold) {
            $exportId = Str::ulid()->toBase32();

            Cache::put(
                "csv_export:{$exportId}",
                ['status' => 'queued', 'filters' => $filters],
                now()->addHours(24),
            );

            GenerateLargeCsvExportJob::dispatch($exportId, $filters);

            return response()->json([
                'job_id' => $exportId,
                'status' => 'queued',
                'poll_url' => route('v1.logs.export.poll', ['exportId' => $exportId]),
            ], 202);
        }

        return $this->streamSync($from, $to, $route);
    }

    private function streamSync(?string $from, ?string $to, ?string $route): StreamedResponse
    {
        return response()->streamDownload(function () use ($from, $to, $route): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['request_id', 'method', 'route', 'status', 'duration_ms', 'api_key_prefix', 'created_at']);

            $this->buildQuery($from, $to, $route)
                ->with('apiKey:id,key_prefix')
                ->orderBy('id')
                ->chunkById(500, function ($chunk) use ($out): void {
                    foreach ($chunk as $log) {
                        /** @var RequestLog $log */
                        /** @var ApiKey|null $relatedKey */
                        $relatedKey = $log->getRelation('apiKey');
                        fputcsv($out, [
                            (string) $log->id,
                            (string) $log->method,
                            (string) $log->path,
                            (string) $log->status,
                            (string) $log->duration_ms,
                            (string) ($relatedKey !== null ? $relatedKey->key_prefix : ''),
                            (string) $log->created_at->toIso8601String(),
                        ]);
                    }
                });

            fclose($out);
        }, 'request-logs-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return Builder<RequestLog>
     */
    private function buildQuery(?string $from, ?string $to, ?string $route): Builder
    {
        $query = RequestLog::query();

        if ($route !== null) {
            $query->forRoute($route);
        }

        if ($from !== null || $to !== null) {
            $query->betweenDates(
                $from !== null ? Carbon::parse($from) : null,
                $to !== null ? Carbon::parse($to) : null,
            );
        }

        return $query;
    }
}
