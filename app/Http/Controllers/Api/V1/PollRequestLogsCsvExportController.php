<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PollRequestLogsCsvExportController extends Controller
{
    #[ScrambleResponse(status: 200, description: 'CSV stream when the export is ready', mediaType: 'text/csv', type: 'string', format: 'binary')]
    #[ScrambleResponse(status: 202, description: 'Export still queued or running; poll again', type: 'array{job_id: string, status: string, poll_url: string}')]
    #[ScrambleResponse(status: 404, description: 'Export id unknown or expired', type: 'array{error: string, message: string}')]
    public function __invoke(Request $request, string $exportId): JsonResponse|StreamedResponse
    {
        /** @var array{status: string, filters: array<string, mixed>}|null $state */
        $state = Cache::get("csv_export:{$exportId}");

        if ($state === null) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Export not found or expired.',
            ], 404);
        }

        if ($state['status'] === 'done') {
            $path = storage_path("app/exports/{$exportId}.csv");

            return response()->streamDownload(function () use ($path): void {
                $handle = fopen($path, 'r');

                if ($handle === false) {
                    return;
                }

                while (! feof($handle)) {
                    $chunk = fread($handle, 8192);

                    if ($chunk !== false) {
                        echo $chunk;
                    }
                }

                fclose($handle);
            }, "{$exportId}.csv", ['Content-Type' => 'text/csv']);
        }

        return response()->json([
            'job_id' => $exportId,
            'status' => $state['status'],
            'poll_url' => route('v1.logs.export.poll', ['exportId' => $exportId]),
        ], 202);
    }
}
