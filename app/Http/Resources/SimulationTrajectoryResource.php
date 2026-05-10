<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Data\SimulationTrajectory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialises a {@see SimulationTrajectory} to the JSON shape defined in
 * openapi.yaml `SimulationTrajectory` schema.
 *
 * The `data` envelope is suppressed because the OpenAPI spec documents
 * top-level keys and the frontend decodes against that flat shape.
 *
 * @mixin SimulationTrajectory
 */
final class SimulationTrajectoryResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SimulationTrajectory $trajectory */
        $trajectory = $this->resource;

        return [
            'request_id' => $trajectory->requestId,
            'animation' => $trajectory->animation->value,
            'duration_seconds' => $trajectory->durationSeconds,
            'step_size' => $trajectory->stepSize,
            'samples' => $trajectory->samples,
            'final_state' => $trajectory->finalState,
        ];
    }
}
