<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stub — real handler ships in Phase 09.
 */
final class StatsSummaryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'from' => null,
            'to' => null,
            'animations' => [],
        ]);
    }
}
