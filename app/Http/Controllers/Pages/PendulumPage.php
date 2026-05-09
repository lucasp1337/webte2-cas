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
        return Inertia::render('Pendulum');
    }
}
