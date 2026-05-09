<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

it('redirects the root url to the locale-prefixed home page', function (): void {
    $response = get('/');

    $response->assertRedirect('/sk');
});

it('renders the home page through inertia under the sk locale prefix', function (): void {
    $response = get('/sk');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('Home')->where('locale', 'sk'));
});

it('renders the home page through inertia under the en locale prefix', function (): void {
    $response = get('/en');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('Home')->where('locale', 'en'));
});
