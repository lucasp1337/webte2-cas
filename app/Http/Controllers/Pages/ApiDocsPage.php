<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class ApiDocsPage extends Controller
{
    public function __invoke(): Response
    {
        $apiKey = config('cas.api_key_plaintext');

        return Inertia::render('ApiDocs', [
            'apiKey' => is_string($apiKey) ? $apiKey : '',
        ]);
    }
}
