<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class ConsolePage extends Controller
{
    public function __invoke(): Response
    {
        /** @var string|null $apiKey */
        $apiKey = config('cas.api_key_plaintext');

        return Inertia::render('Console', [
            // Plaintext key is intentionally surfaced to the browser so the
            // bilingual UI can call the protected /api/v1 routes without a
            // dedicated session-only auth path. In production the key is
            // rotated via `php artisan cas:create-key`.
            'apiKey' => $apiKey ?? '',
        ]);
    }
}
