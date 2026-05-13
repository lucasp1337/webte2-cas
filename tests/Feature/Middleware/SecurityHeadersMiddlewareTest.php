<?php

declare(strict_types=1);

use function Pest\Laravel\get;

describe('SecurityHeadersMiddleware', function (): void {
    it('sets X-Content-Type-Options to nosniff', function (): void {
        $response = get('/sk');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    it('sets X-Frame-Options to DENY', function (): void {
        $response = get('/sk');

        $response->assertHeader('X-Frame-Options', 'DENY');
    });

    it('sets Referrer-Policy to strict-origin-when-cross-origin', function (): void {
        $response = get('/sk');

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    });

    it('sets Permissions-Policy disabling sensitive browser features', function (): void {
        $response = get('/sk');

        $response->assertHeader('Permissions-Policy');
        $policy = (string) $response->headers->get('Permissions-Policy', '');
        expect($policy)->toContain('geolocation=()');
        expect($policy)->toContain('camera=()');
        expect($policy)->toContain('microphone=()');
    });

    it('sets X-XSS-Protection to 0 per OWASP recommendation', function (): void {
        $response = get('/sk');

        $response->assertHeader('X-XSS-Protection', '0');
    });

    it('applies security headers to API responses as well', function (): void {
        $response = get('/api/v1/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    });

    it('does not include HSTS in non-production environments', function (): void {
        // Test environment — HSTS must be absent.
        $response = get('/sk');

        $response->assertHeaderMissing('Strict-Transport-Security');
    });

    it('includes HSTS only in the production environment', function (): void {
        app()->detectEnvironment(fn (): string => 'production');

        $response = get('/sk');

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        app()->detectEnvironment(fn (): string => 'testing');
    });
});
