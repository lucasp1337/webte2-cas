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
        // Intentionally NO apiKey prop — the page is public docs and the PDF
        // endpoints (POST /api-docs/pdf, GET /api-docs/pdf/{exportId}) are
        // also public. The seeded demo key would otherwise leak to anonymous
        // visitors via the network tab. If a route here ever needs auth,
        // gate it via Swagger UI's own "Authorize" button (user-supplied key).
        return Inertia::render('ApiDocs');
    }
}
