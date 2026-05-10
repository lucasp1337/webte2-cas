<?php

declare(strict_types=1);

use App\Http\Controllers\Api\OpenApiSpecController;
use App\Http\Controllers\Api\V1\ClearOctaveSessionController;
use App\Http\Controllers\Api\V1\DownloadApiDocsPdfController;
use App\Http\Controllers\Api\V1\ExecuteOctaveCommandController;
use App\Http\Controllers\Api\V1\ExportRequestLogsCsvController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ListRequestLogsController;
use App\Http\Controllers\Api\V1\PollRequestLogsCsvExportController;
use App\Http\Controllers\Api\V1\RequestApiDocsPdfController;
use App\Http\Controllers\Api\V1\RunBallBeamSimulationController;
use App\Http\Controllers\Api\V1\RunPendulumSimulationController;
use App\Http\Controllers\Api\V1\StatsForAnimationController;
use App\Http\Controllers\Api\V1\StatsSummaryController;
use Illuminate\Support\Facades\Route;

// Spec — public, no auth required so Swagger UI and curl can fetch without X-API-Key.
Route::get('/openapi.json', OpenApiSpecController::class)->name('openapi.spec');

// Public — no api-protected (no X-API-Key required).
Route::get('/v1/health', HealthController::class)->name('v1.health');

// Simulation routes extend api-protected with EnsureAnonTokenMiddleware so
// the phase-09 stats listener has a stable anonymous token for cooldown.
Route::prefix('v1/simulations')->middleware('api-simulation')->group(function (): void {
    Route::post('/pendulum', RunPendulumSimulationController::class)->name('v1.simulations.pendulum');
    Route::post('/ball-beam', RunBallBeamSimulationController::class)->name('v1.simulations.ball-beam');
});

Route::prefix('v1')->middleware('api-protected')->group(function (): void {
    Route::post('/octave/exec', ExecuteOctaveCommandController::class)->name('v1.octave.exec');
    Route::delete('/octave/session', ClearOctaveSessionController::class)->name('v1.octave.session.clear');

    Route::get('/logs', ListRequestLogsController::class)->name('v1.logs.index');
    Route::get('/logs/export.csv', ExportRequestLogsCsvController::class)->name('v1.logs.export');
    Route::get('/logs/export.csv/{exportId}', PollRequestLogsCsvExportController::class)
        ->whereUlid('exportId')
        ->name('v1.logs.export.poll');

    Route::get('/stats', StatsSummaryController::class)->name('v1.stats.summary');
    Route::get('/stats/{animation}', StatsForAnimationController::class)
        ->whereIn('animation', ['pendulum', 'ball-beam'])
        ->name('v1.stats.detail');
});

// API-docs PDF — public, parallels the public openapi.json. The page itself
// is anonymous and we don't ship an API key to the browser, so these routes
// can't sit behind X-API-Key. Throttle keyed by IP to bound abuse.
Route::prefix('v1')->middleware('throttle:cas-api')->group(function (): void {
    Route::post('/api-docs/pdf', RequestApiDocsPdfController::class)->name('v1.api-docs.pdf.request');
    Route::get('/api-docs/pdf/{exportId}', DownloadApiDocsPdfController::class)
        ->whereUlid('exportId')
        ->name('v1.api-docs.pdf.download');
});
