<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stub — real handler ships in Phase 08 (PDF job delegation).
 */
final class DownloadApiDocsPdfController extends Controller
{
    public function __invoke(Request $request, string $exportId): JsonResponse
    {
        return response()->json([
            'export_id' => $exportId,
            'status' => 'queued',
        ], 202);
    }
}
