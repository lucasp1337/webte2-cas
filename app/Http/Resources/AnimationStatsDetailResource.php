<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Per-animation detail stats resource — same as summary but scoped to one
 * animation and includes a top_cities breakdown.
 *
 * Expects $resource to be an array with keys:
 *   animation:     string
 *   totals:        array<string, int>
 *   per_day:       list<array{date: string, animation: string, count: int}>
 *   top_countries: list<array{country_iso: string, country: string|null, count: int}>
 *   top_cities:    list<array{city: string, count: int}>
 */
final class AnimationStatsDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{animation: string, totals: array<string,int>, per_day: list<array{date: string, animation: string, count: int}>, top_countries: list<array{country_iso: string, country: string|null, count: int}>, top_cities: list<array{city: string, count: int}>} $data */
        $data = $this->resource;

        return [
            'animation' => $data['animation'],
            'totals' => $data['totals'],
            'per_day' => $data['per_day'],
            'top_countries' => $data['top_countries'],
            'top_cities' => $data['top_cities'],
        ];
    }
}
