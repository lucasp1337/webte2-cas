<?php

declare(strict_types=1);

use App\Jobs\PruneStaleOctaveSessionsJob;
use App\Jobs\RefreshGeolocationDatabaseJob;
use App\Jobs\RegenerateApiDocsCacheJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/** @var int $idleHours */
$idleHours = config('cas.octave_session_idle_hours', 24);

Schedule::job(new PruneStaleOctaveSessionsJob($idleHours))
    ->dailyAt('02:00')
    ->name('prune-stale-octave-sessions')
    ->onOneServer();

Schedule::job(new RegenerateApiDocsCacheJob)
    ->dailyAt('04:00')
    ->onOneServer()
    ->name('regenerate-openapi-cache');

Schedule::job(new RefreshGeolocationDatabaseJob)
    ->monthlyOn(1, '05:00')
    ->onOneServer()
    ->name('refresh-geolite-db');
