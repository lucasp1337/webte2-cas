<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pages;

use App\Actions\Stats\AggregateUsageStats;
use App\Enums\AnimationName;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StatsPage extends Controller
{
    public function __construct(private readonly AggregateUsageStats $aggregate) {}

    public function __invoke(Request $request): Response
    {
        $animationParam = $request->query('animation');
        $animation = is_string($animationParam) ? AnimationName::tryFrom($animationParam) : null;

        $props = [
            'summary' => $this->aggregate->handle(),
        ];

        if ($animation !== null) {
            $props['detail'] = $this->aggregate->handleForAnimation($animation);
            $props['animation'] = $animation->value;
        }

        return Inertia::render('Stats', $props);
    }
}
