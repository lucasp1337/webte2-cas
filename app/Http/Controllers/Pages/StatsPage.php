<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pages;

use App\Actions\Stats\AggregateUsageStats;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class StatsPage extends Controller
{
    public function __construct(private readonly AggregateUsageStats $aggregate) {}

    public function __invoke(): Response
    {
        return Inertia::render('Stats', [
            'summary' => $this->aggregate->handle(),
        ]);
    }
}
