<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;

// Telescope is only registered when explicitly enabled via TELESCOPE_ENABLED=true.
// This prevents any chance of the provider activating in production due to a
// missing env var — the config default alone is not sufficient when the class
// is unconditionally autoloaded and registered.
return array_values(array_filter([
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    env('TELESCOPE_ENABLED', false) ? TelescopeServiceProvider::class : null,
]));
