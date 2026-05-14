<?php

declare(strict_types=1);

use function Pest\Laravel\get;

describe('ContentSecurityPolicyMiddleware', function (): void {
    describe('default (strict) policy', function (): void {
        it('sets a CSP header on the health endpoint', function (): void {
            $response = get('/api/v1/health');

            $response->assertHeader('Content-Security-Policy');
        });

        it('does not allow unsafe-inline in script-src on the health endpoint', function (): void {
            $response = get('/api/v1/health');

            $csp = (string) $response->headers->get('Content-Security-Policy', '');
            // Extract the script-src directive value.
            preg_match('/script-src ([^;]+)/', $csp, $matches);
            $scriptSrc = $matches[1] ?? '';

            expect($scriptSrc)->not->toContain("'unsafe-inline'");
        });

        it('does not allow unsafe-inline in style-src on the health endpoint', function (): void {
            $response = get('/api/v1/health');

            $csp = (string) $response->headers->get('Content-Security-Policy', '');
            preg_match('/style-src ([^;]+)/', $csp, $matches);
            $styleSrc = $matches[1] ?? '';

            expect($styleSrc)->not->toContain("'unsafe-inline'");
        });

        it('includes frame-ancestors none in strict policy', function (): void {
            $response = get('/api/v1/health');

            $csp = (string) $response->headers->get('Content-Security-Policy', '');
            expect($csp)->toContain("frame-ancestors 'none'");
        });
    });

    describe('relaxed policy on /api-docs', function (): void {
        it('allows unsafe-inline in script-src on the sk api-docs route', function (): void {
            $response = get('/sk/api-docs');

            $response->assertStatus(200);
            $csp = (string) $response->headers->get('Content-Security-Policy', '');
            preg_match('/script-src ([^;]+)/', $csp, $matches);
            $scriptSrc = $matches[1] ?? '';

            expect($scriptSrc)->toContain("'unsafe-inline'");
        });

        it('allows unsafe-inline in style-src on the sk api-docs route', function (): void {
            $response = get('/sk/api-docs');

            $response->assertStatus(200);
            $csp = (string) $response->headers->get('Content-Security-Policy', '');
            preg_match('/style-src ([^;]+)/', $csp, $matches);
            $styleSrc = $matches[1] ?? '';

            expect($styleSrc)->toContain("'unsafe-inline'");
        });

        it('allows unsafe-inline in script-src on the en api-docs route', function (): void {
            $response = get('/en/api-docs');

            $response->assertStatus(200);
            $csp = (string) $response->headers->get('Content-Security-Policy', '');
            preg_match('/script-src ([^;]+)/', $csp, $matches);
            $scriptSrc = $matches[1] ?? '';

            expect($scriptSrc)->toContain("'unsafe-inline'");
        });

        it('allows unsafe-inline in style-src on the en api-docs route', function (): void {
            $response = get('/en/api-docs');

            $response->assertStatus(200);
            $csp = (string) $response->headers->get('Content-Security-Policy', '');
            preg_match('/style-src ([^;]+)/', $csp, $matches);
            $styleSrc = $matches[1] ?? '';

            expect($styleSrc)->toContain("'unsafe-inline'");
        });
    });

    describe('partially relaxed policy on /console', function (): void {
        it('allows unsafe-inline in style-src on the sk console route', function (): void {
            $response = get('/sk/console');

            $response->assertStatus(200);
            $csp = (string) $response->headers->get('Content-Security-Policy', '');
            preg_match('/style-src ([^;]+)/', $csp, $matches);
            $styleSrc = $matches[1] ?? '';

            expect($styleSrc)->toContain("'unsafe-inline'");
        });

        it('allows unsafe-inline in script-src on the sk console route (theme detection inline script)', function (): void {
            $response = get('/sk/console');

            $response->assertStatus(200);
            $csp = (string) $response->headers->get('Content-Security-Policy', '');
            preg_match('/script-src ([^;]+)/', $csp, $matches);
            $scriptSrc = $matches[1] ?? '';

            expect($scriptSrc)->toContain("'unsafe-inline'");
        });

        it('allows unsafe-inline in style-src on the en console route', function (): void {
            $response = get('/en/console');

            $response->assertStatus(200);
            $csp = (string) $response->headers->get('Content-Security-Policy', '');
            preg_match('/style-src ([^;]+)/', $csp, $matches);
            $styleSrc = $matches[1] ?? '';

            expect($styleSrc)->toContain("'unsafe-inline'");
        });

        it('allows unsafe-inline in script-src on the en console route (theme detection inline script)', function (): void {
            $response = get('/en/console');

            $response->assertStatus(200);
            $csp = (string) $response->headers->get('Content-Security-Policy', '');
            preg_match('/script-src ([^;]+)/', $csp, $matches);
            $scriptSrc = $matches[1] ?? '';

            expect($scriptSrc)->toContain("'unsafe-inline'");
        });
    });
});
