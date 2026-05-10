<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Stub — real handler ships in Phase 08 (PDF job delegation).
 */
final class RequestApiDocsPdfController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $exportId = Str::ulid()->toBase32();

        return response()->json([
            'export_id' => $exportId,
            'status' => 'queued',
            'poll_url' => route('v1.api-docs.pdf.download', ['exportId' => $exportId]),
        ], 202);
    }
}
