<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClearOctaveSessionRequest;
use Illuminate\Http\JsonResponse;

/**
 * Stub — real handler ships in Phase 05.
 */
final class ClearOctaveSessionController extends Controller
{
    public function __invoke(ClearOctaveSessionRequest $request): JsonResponse
    {
        /** @var string $requestId */
        $requestId = $request->attributes->get('request_id', '');

        return response()->json([
            'request_id' => $requestId,
            'cleared' => true,
        ]);
    }
}
