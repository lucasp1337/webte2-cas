<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Per-animation detail stats resource — drilldown for a single animation.
 *
 * Expects $resource to be an array with keys:
 *   animation:     string
 *   per_day:       list<array{date: string, count: int}>
 *   top_countries: list<array{country_iso: string, country: string|null, count: int}>
 *   top_cities:    list<array{city: string|null, count: int}>
 *
 * top_cities rows only appear when city is non-null (filtered at query level).
 */
final class AnimationStatsDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{animation: string, per_day: list<array{date: string, count: int}>, top_countries: list<array{country_iso: string, country: string|null, count: int}>, top_cities: list<array{city: string|null, count: int}>} $data */
        $data = $this->resource;

        return [
            'animation' => $data['animation'],
            'per_day' => $data['per_day'],
            'top_countries' => $data['top_countries'],
            'top_cities' => $data['top_cities'],
        ];
    }
}
