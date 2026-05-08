<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// TODO(phase-01): remove before merge — smoke test for the cli scheduler.
Schedule::call(fn () => Log::info('cli scheduler heartbeat', ['at' => now()->toIso8601String()]))
    ->everyMinute()
    ->name('phase-01-heartbeat');
