<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Octave\OctaveExecutionResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps an {@see OctaveExecutionResult} for the JSON response. Rejection and
 * timeout statuses surface via the controller's HTTP code (422/504) so the
 * payload itself stays uniform with the success shape.
 *
 * The default `data` envelope is removed because the OpenAPI sample in the
 * brief documents top-level keys (`stdout`, `stderr`, `exit_code`, ...), and
 * existing clients (incl. the frontend `octave.ts` wrapper) decode against
 * that flat shape.
 *
 * @mixin OctaveExecutionResult
 */
final class OctaveExecutionResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @return array{
     *     request_id: string,
     *     stdout: string,
     *     stderr: string,
     *     exit_code: int,
     *     duration_ms: int,
     *     rejection_reason: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var OctaveExecutionResult $result */
        $result = $this->resource;

        return [
            'request_id' => $result->requestId,
            'stdout' => $result->stdout,
            'stderr' => $result->stderr,
            'exit_code' => $result->exitCode,
            'duration_ms' => $result->durationMs,
            'rejection_reason' => $result->rejectionReason,
        ];
    }
}
