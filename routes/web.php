<?php

declare(strict_types=1);

use App\Http\Controllers\Pages\ApiDocsPage;
use App\Http\Controllers\Pages\BallBeamPage;
use App\Http\Controllers\Pages\ConsolePage;
use App\Http\Controllers\Pages\HomePage;
use App\Http\Controllers\Pages\LogsPage;
use App\Http\Controllers\Pages\PendulumPage;
use App\Http\Controllers\Pages\StatsPage;
use App\Http\Controllers\SetLocaleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/locale', SetLocaleController::class)->name('locale.set');

Route::prefix('{locale}')
    ->where(['locale' => 'sk|en'])
    ->group(function (): void {
        Route::get('/', HomePage::class)->name('home');
        Route::get('/console', ConsolePage::class)->name('console');
        Route::get('/pendulum', PendulumPage::class)->name('pendulum');
        Route::get('/ball-beam', BallBeamPage::class)->name('ball-beam');
        Route::get('/logs', LogsPage::class)->name('logs');
        Route::get('/api-docs', ApiDocsPage::class)->name('api-docs');
        Route::get('/stats', StatsPage::class)->name('stats');
    });

Route::get('/', function (Request $request) {
    /** @var string|null $cookieLocale */
    $cookieLocale = $request->cookie('locale');
    $locale = is_string($cookieLocale) && in_array($cookieLocale, ['sk', 'en'], true) ? $cookieLocale : 'sk';

    return redirect('/'.$locale);
})->name('welcome');

// Catch-all: any unmatched web URL renders the Inertia NotFound page with a
// proper 404 status. The exception-handler in bootstrap/app.php covers
// route-level 404s; this fallback handles URLs that don't match any route
// pattern at all (e.g. /sk/does-not-exist).
Route::fallback(function () {
    return Inertia::render('NotFound')->toResponse(request())->setStatusCode(404);
});
