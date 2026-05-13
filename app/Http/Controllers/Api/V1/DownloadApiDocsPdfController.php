<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadApiDocsPdfController extends Controller
{
    #[ScrambleResponse(status: 200, description: 'PDF stream', mediaType: 'application/pdf', type: 'string', format: 'binary')]
    #[ScrambleResponse(status: 202, description: 'Render in progress; poll the same URL', type: 'array{export_id: string, status: string, poll_url: string}')]
    #[ScrambleResponse(status: 404, description: 'Export id unknown or expired', type: 'array{error: string, message: string}')]
    #[ScrambleResponse(status: 500, description: 'Render failed', type: 'array{error: string, message: string}')]
    public function __invoke(string $exportId): JsonResponse|StreamedResponse
    {
        /** @var array{status: string, locale: string, path?: string, error?: string}|null $state */
        $state = Cache::get("pdf_export:{$exportId}");

        if ($state === null) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Export not found or expired.',
            ], 404);
        }

        if ($state['status'] === 'failed') {
            return response()->json([
                'error' => 'render_failed',
                'message' => $state['error'] ?? 'PDF rendering failed.',
            ], 500);
        }

        if ($state['status'] === 'done' && isset($state['path'])) {
            $path = $state['path'];

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
            }, 'webte2-api-docs.pdf', ['Content-Type' => 'application/pdf']);
        }

        // queued or running
        return response()->json([
            'export_id' => $exportId,
            'status' => $state['status'],
            'poll_url' => route('v1.api-docs.pdf.download', ['exportId' => $exportId]),
        ], 202);
    }
}
