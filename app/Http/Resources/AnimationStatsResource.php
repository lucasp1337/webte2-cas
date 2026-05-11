<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Summary stats resource — 30-day totals, per-day breakdown, top countries.
 *
 * Expects $resource to be an array with keys:
 *   totals:        array<string, int>
 *   per_day:       list<array{date: string, animation: string, count: int}>
 *   top_countries: list<array{country_iso: string, country: string|null, count: int}>
 */
final class AnimationStatsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{totals: array<string,int>, per_day: list<array{date: string, animation: string, count: int}>, top_countries: list<array{country_iso: string, country: string|null, count: int}>} $data */
        $data = $this->resource;

        return [
            'totals' => $data['totals'],
            'per_day' => $data['per_day'],
            'top_countries' => $data['top_countries'],
        ];
    }
}
