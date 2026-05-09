<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RunPendulumSimulationRequest;
use Illuminate\Http\JsonResponse;

/**
 * Stub — real handler ships in Phase 06.
 */
final class RunPendulumSimulationController extends Controller
{
    public function __invoke(RunPendulumSimulationRequest $request): JsonResponse
    {
        /** @var string $requestId */
        $requestId = $request->attributes->get('request_id', '');

        return response()->json([
            'request_id' => $requestId,
            'animation' => 'pendulum',
            'duration_seconds' => 0,
            'step_size' => 0,
            'samples' => [],
        ]);
    }
}
