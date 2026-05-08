<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

it('renders the welcome page through inertia', function (): void {
    $response = get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('Welcome'));
});
