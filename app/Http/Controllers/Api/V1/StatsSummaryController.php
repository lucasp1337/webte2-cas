<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Stats\AggregateUsageStats;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnimationStatsResource;

final class StatsSummaryController extends Controller
{
    public function __construct(private readonly AggregateUsageStats $aggregate) {}

    public function __invoke(): AnimationStatsResource
    {
        return AnimationStatsResource::make($this->aggregate->handle());
    }
}
