<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExecuteOctaveCommandRequest;
use Illuminate\Http\JsonResponse;

/**
 * Stub — real handler ships in Phase 05.
 */
final class ExecuteOctaveCommandController extends Controller
{
    public function __invoke(ExecuteOctaveCommandRequest $request): JsonResponse
    {
        /** @var string $requestId */
        $requestId = $request->attributes->get('request_id', '');

        return response()->json([
            'request_id' => $requestId,
            'stdout' => '',
            'stderr' => '',
            'exit_code' => 0,
            'duration_ms' => 0,
            'figures' => [],
        ]);
    }
}
