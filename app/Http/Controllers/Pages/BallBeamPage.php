<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class BallBeamPage extends Controller
{
    public function __invoke(): Response
    {
        /** @var string|null $apiKey */
        $apiKey = config('cas.api_key_plaintext');

        // Slowdown factor: 1 = real-time, 2 = half speed, etc. Derived from
        // CAS_SLOWDOWN_MS by treating the bridge command-throttle as a
        // playback hint — every 500 ms of throttle slows simulation playback
        // by 1×. Mirrors PendulumPage logic exactly.
        $slowdownMsRaw = config('cas.cas_slowdown_ms', 500);
        $slowdownMs = is_numeric($slowdownMsRaw) ? (int) $slowdownMsRaw : 500;
        $slowdownFactor = max(1, (int) round($slowdownMs / 500));

        return Inertia::render('BallBeam', [
            'apiKey' => $apiKey ?? '',
            'slowdownFactor' => $slowdownFactor,
        ]);
    }
}
