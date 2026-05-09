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
        return Inertia::render('ApiDocs');
    }
}
