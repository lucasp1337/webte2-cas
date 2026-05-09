<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stub — real handler ships in Phase 09.
 */
final class StatsForAnimationController extends Controller
{
    public function __invoke(Request $request, string $animation): JsonResponse
    {
        return response()->json([
            'animation' => $animation,
            'from' => null,
            'to' => null,
            'timeline' => [],
            'geo' => [],
        ]);
    }
}
