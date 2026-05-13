<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClearOctaveSessionRequest;
use App\Http\Requests\Api\V1\ExecuteOctaveCommandRequest;
use App\Services\Octave\Exceptions\OctaveBridgeUnavailableException;
use App\Services\Octave\Exceptions\OctaveCommandRejectedException;
use App\Services\Octave\OctaveBridgeClient;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ClearOctaveSessionController extends Controller
{
    /**
     * Clear the caller's Octave workspace. Returns 204 with no body.
     *
     * The success path returns 204 with an empty body. The bridge-unavailable
     * path returns 503 with a JSON error envelope. Scramble's response
     * inference adds a spurious 200 entry on top of explicit annotations when
     * the controller's return type union spans body and no-body responses, so
     * both real status codes are annotated explicitly below.
     */
    #[ScrambleResponse(status: 204, description: 'Session cleared')]
    #[ScrambleResponse(status: 503, description: 'Octave bridge unreachable', type: 'array{error: string, message: string}')]
    public function __invoke(ClearOctaveSessionRequest $request, OctaveBridgeClient $bridge): JsonResponse|HttpResponse
    {
        /** @var string|null $sessionId */
        $sessionId = $request->session()->pull(ExecuteOctaveCommandRequest::SESSION_KEY);

        if ($sessionId !== null && $sessionId !== '') {
            try {
                $bridge->clearSession($sessionId);
            } catch (OctaveCommandRejectedException) {
                // Malformed session id stored in the cookie — treat as a no-op.
            } catch (OctaveBridgeUnavailableException) {
                return new JsonResponse(
                    ['error' => 'bridge_unavailable', 'message' => 'Octave bridge is unreachable'],
                    HttpResponse::HTTP_SERVICE_UNAVAILABLE,
                );
            }
        }

        return response()->noContent();
    }
}
