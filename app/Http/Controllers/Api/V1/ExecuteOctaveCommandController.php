<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Octave\ExecuteOctaveCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExecuteOctaveCommandRequest;
use App\Http\Resources\OctaveExecutionResource;
use App\Services\Octave\Exceptions\OctaveBridgeUnavailableException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ExecuteOctaveCommandController extends Controller
{
    public function __construct(private readonly ExecuteOctaveCommand $action) {}

    public function __invoke(ExecuteOctaveCommandRequest $request): JsonResponse
    {
        /** @var string $command */
        $command = $request->validated('command');

        try {
            $result = $this->action->handle($request->consoleSessionId(), $command);
        } catch (OctaveBridgeUnavailableException) {
            return new JsonResponse(
                ['error' => 'bridge_unavailable', 'message' => 'Octave bridge is unreachable'],
                HttpResponse::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $status = match (true) {
            $result->isRejected() => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            $result->isTimedOut() => HttpResponse::HTTP_GATEWAY_TIMEOUT,
            default => HttpResponse::HTTP_OK,
        };

        return OctaveExecutionResource::make($result)
            ->response()
            ->setStatusCode($status);
    }
}
