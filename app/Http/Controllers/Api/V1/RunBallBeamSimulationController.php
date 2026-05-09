<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RunBallBeamSimulationRequest;
use Illuminate\Http\JsonResponse;

/**
 * Stub — real handler ships in Phase 07.
 */
final class RunBallBeamSimulationController extends Controller
{
    public function __invoke(RunBallBeamSimulationRequest $request): JsonResponse
    {
        /** @var string $requestId */
        $requestId = $request->attributes->get('request_id', '');

        return response()->json([
            'request_id' => $requestId,
            'animation' => 'ball-beam',
            'duration_seconds' => 0,
            'step_size' => 0,
            'samples' => [],
        ]);
    }
}
