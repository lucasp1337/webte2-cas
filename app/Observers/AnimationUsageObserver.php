<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AnimationUsage;
use App\Services\GeolocationService;

/**
 * Defence-in-depth geo backfill: the listener handles the normal path, but
 * rows inserted via seeders or console commands bypass the listener entirely.
 * The observer is the safety net that catches those cases when a request IP
 * is available.
 */
final readonly class AnimationUsageObserver
{
    public function __construct(private GeolocationService $geo) {}

    public function creating(AnimationUsage $usage): void
    {
        // Only backfill when all three geo columns are null and an HTTP
        // request is in-flight (not the case in queue workers or console).
        if ($usage->country_iso !== null || $usage->country !== null || $usage->city !== null) {
            return;
        }

        $ip = request()->ip();

        if ($ip === null) {
            return;
        }

        $geo = $this->geo->lookup($ip);

        $usage->country_iso = $geo->countryIso;
        $usage->country = $geo->country;
        $usage->city = $geo->city;
    }
}
