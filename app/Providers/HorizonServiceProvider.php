<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * In local environments Horizon is open without a token — mirrors the
     * behaviour of Horizon's own Authorize middleware which bypasses the gate
     * locally.  In every other environment the request must supply the
     * HORIZON_ADMIN_TOKEN value either via the `token` query-string parameter
     * or the `X-Horizon-Token` header.  Comparison is constant-time to prevent
     * timing oracle attacks.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if (app()->environment('local')) {
                return true;
            }

            $expected = (string) config('cas.horizon_admin_token');

            if ($expected === '') {
                return false;
            }

            $supplied = (string) (request()->query('token')
                ?? request()->header('X-Horizon-Token', ''));

            if ($supplied === '') {
                return false;
            }

            return hash_equals($expected, $supplied);
        });
    }
}
