<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClearOctaveSessionRequest;
use App\Http\Requests\Api\V1\ExecuteOctaveCommandRequest;
use App\Services\Octave\Exceptions\OctaveBridgeUnavailableException;
use App\Services\Octave\Exceptions\OctaveCommandRejectedException;
use App\Services\Octave\OctaveBridgeClient;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ClearOctaveSessionController extends Controller
{
    public function __invoke(ClearOctaveSessionRequest $request, OctaveBridgeClient $bridge): HttpResponse
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

        return new HttpResponse('', HttpResponse::HTTP_NO_CONTENT);
    }
}
