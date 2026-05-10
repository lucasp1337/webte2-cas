<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\BallBeam\RunBallBeamSimulation;
use App\Data\BallBeamParameters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RunBallBeamSimulationRequest;
use App\Http\Resources\SimulationTrajectoryResource;
use App\Services\Octave\Exceptions\OctaveBridgeUnavailableException;
use App\Services\Octave\Exceptions\OctaveCommandRejectedException;
use App\Services\Octave\Exceptions\OctaveTimeoutException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class RunBallBeamSimulationController extends Controller
{
    public function __construct(private readonly RunBallBeamSimulation $action) {}

    public function __invoke(RunBallBeamSimulationRequest $request): JsonResponse|SimulationTrajectoryResource
    {
        /** @var array<string, mixed> $parametersArray */
        $parametersArray = $request->validated('parameters');
        $parameters = BallBeamParameters::from($parametersArray);

        /** @var list<float>|null $continueFrom */
        $continueFrom = $request->validated('continue_from');

        // EnsureAnonTokenMiddleware writes the resolved token to request attributes
        // so we do not need to decrypt the cookie again here.
        $anonTokenRaw = $request->attributes->get('anon_token', '');
        $anonToken = is_string($anonTokenRaw) ? $anonTokenRaw : '';
        $ip = $request->ip() ?? '';

        try {
            $trajectory = $this->action->handle($parameters, $continueFrom, $anonToken, $ip);
        } catch (OctaveBridgeUnavailableException) {
            return new JsonResponse(
                ['error' => 'bridge_unavailable', 'message' => 'Octave bridge is unreachable'],
                HttpResponse::HTTP_SERVICE_UNAVAILABLE,
            );
        } catch (OctaveTimeoutException) {
            return new JsonResponse(
                ['error' => 'simulation_timeout', 'message' => 'Simulation timed out'],
                HttpResponse::HTTP_GATEWAY_TIMEOUT,
            );
        } catch (OctaveCommandRejectedException $e) {
            return new JsonResponse(
                ['error' => 'command_rejected', 'message' => $e->getMessage()],
                HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return SimulationTrajectoryResource::make($trajectory);
    }
}
