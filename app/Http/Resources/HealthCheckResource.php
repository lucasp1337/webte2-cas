<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the health-check payload for the JSON response.
 *
 * The default `data` envelope is removed so the JSON contract stays flat:
 * `{status, dependencies: {mysql, redis, octave_bridge}}`. Existing clients
 * (the frontend health probe, the deploy smoke test) decode against the
 * flat shape.
 *
 * @property-read array{
 *     status: string,
 *     dependencies: array{mysql: string, redis: string, octave_bridge: string}
 * } $resource
 */
final class HealthCheckResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @return array{
     *     status: string,
     *     dependencies: array{mysql: string, redis: string, octave_bridge: string},
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array{status: string, dependencies: array{mysql: string, redis: string, octave_bridge: string}} $payload */
        $payload = $this->resource;

        return $payload;
    }
}
