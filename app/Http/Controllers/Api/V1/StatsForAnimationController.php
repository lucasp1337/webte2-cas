<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Stats\AggregateUsageStats;
use App\Enums\AnimationName;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnimationStatsDetailResource;

final class StatsForAnimationController extends Controller
{
    public function __construct(private readonly AggregateUsageStats $stats) {}

    public function __invoke(AnimationName $animation): AnimationStatsDetailResource
    {
        return AnimationStatsDetailResource::make(
            $this->stats->handleForAnimation($animation),
        );
    }
}
