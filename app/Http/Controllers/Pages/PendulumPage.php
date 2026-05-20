<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class PendulumPage extends Controller
{
    public function __invoke(): Response
    {
        /** @var string|null $apiKey */
        $apiKey = config('cas.api_key_plaintext');

        // Slowdown factor: 1 = real-time, 2 = half speed, etc. Derived from
        // CAS_SLOWDOWN_MS by treating the bridge command-throttle as a
        // playback hint — every 500 ms of throttle slows simulation playback
        // by 1×. A dedicated env var could be surfaced later if this proves coarse.
        $slowdownMsRaw = config('cas.cas_slowdown_ms', 500);
        $slowdownMs = is_numeric($slowdownMsRaw) ? (int) $slowdownMsRaw : 500;
        $slowdownFactor = max(1, (int) round($slowdownMs / 500));

        return Inertia::render('Pendulum', [
            'apiKey' => $apiKey ?? '',
            'slowdownFactor' => $slowdownFactor,
        ]);
    }
}
