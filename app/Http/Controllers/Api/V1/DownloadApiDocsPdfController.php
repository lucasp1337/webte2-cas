<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stub — real handler ships in Phase 08.
 */
final class DownloadApiDocsPdfController extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'job_id' => $id,
            'status' => 'queued',
        ], 202);
    }
}
