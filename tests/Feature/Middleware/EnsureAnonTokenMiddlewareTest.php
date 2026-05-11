<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAnonTokenMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

describe('EnsureAnonTokenMiddleware', function (): void {
    it('sets an anon_token cookie when no cookie is present', function (): void {
        $request = Request::create('/test', 'GET');
        $middleware = new EnsureAnonTokenMiddleware;

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $setCookie = $response->headers->get('Set-Cookie', '');
        expect((string) $setCookie)->toContain('anon_token=');
    });

    it('sets the cookie as HttpOnly', function (): void {
        $request = Request::create('/test', 'GET');
        $middleware = new EnsureAnonTokenMiddleware;

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $setCookie = strtolower((string) $response->headers->get('Set-Cookie', ''));
        expect($setCookie)->toContain('httponly');
    });

    it('sets the cookie with SameSite=Lax', function (): void {
        $request = Request::create('/test', 'GET');
        $middleware = new EnsureAnonTokenMiddleware;

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $setCookie = strtolower((string) $response->headers->get('Set-Cookie', ''));
        expect($setCookie)->toContain('samesite=lax');
    });

    it('sets the secure flag in non-local environments', function (): void {
        app()->detectEnvironment(fn () => 'production');

        $request = Request::create('/test', 'GET');
        $middleware = new EnsureAnonTokenMiddleware;

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $setCookie = strtolower((string) $response->headers->get('Set-Cookie', ''));
        expect($setCookie)->toContain('secure');

        app()->detectEnvironment(fn () => 'testing');
    });

    it('does not set the secure flag in local environment', function (): void {
        app()->detectEnvironment(fn () => 'local');

        $request = Request::create('/test', 'GET');
        $middleware = new EnsureAnonTokenMiddleware;

        $response = $middleware->handle($request, fn () => new Response('ok'));

        // In local environment, secure flag should not be present.
        // Split on semicolons and check that no segment is exactly "secure".
        $setCookie = (string) $response->headers->get('Set-Cookie', '');
        $parts = array_map('strtolower', array_map('trim', explode(';', $setCookie)));
        expect(in_array('secure', $parts, true))->toBeFalse();

        app()->detectEnvironment(fn () => 'testing');
    });

    it('preserves an existing valid UUID cookie without re-issuing', function (): void {
        $existingUuid = (string) Str::uuid();
        $request = Request::create('/test', 'GET', [], ['anon_token' => $existingUuid]);
        $middleware = new EnsureAnonTokenMiddleware;

        $capturedToken = null;
        $response = $middleware->handle($request, function (Request $req) use (&$capturedToken): Response {
            $capturedToken = $req->attributes->get('anon_token');

            return new Response('ok');
        });

        expect($capturedToken)->toBe($existingUuid);

        // No new cookie should be issued when the existing token is valid.
        $setCookie = $response->headers->get('Set-Cookie', '');
        expect((string) $setCookie)->not->toContain('anon_token=');
    });

    it('replaces a non-UUID cookie value with a fresh UUID', function (): void {
        $request = Request::create('/test', 'GET', [], ['anon_token' => 'not-a-uuid']);
        $middleware = new EnsureAnonTokenMiddleware;

        $capturedToken = null;
        $response = $middleware->handle($request, function (Request $req) use (&$capturedToken): Response {
            $capturedToken = $req->attributes->get('anon_token');

            return new Response('ok');
        });

        expect($capturedToken)->not->toBeNull()->toBeString();

        /** @var string $capturedToken */
        expect(Str::isUuid($capturedToken))->toBeTrue()
            ->and($capturedToken)->not->toBe('not-a-uuid');

        $setCookie = (string) $response->headers->get('Set-Cookie', '');
        expect($setCookie)->toContain('anon_token=');
    });

    it('exposes the token via request attributes for downstream use', function (): void {
        $request = Request::create('/test', 'GET');
        $middleware = new EnsureAnonTokenMiddleware;

        $capturedToken = null;
        $middleware->handle($request, function (Request $req) use (&$capturedToken): Response {
            $capturedToken = $req->attributes->get('anon_token');

            return new Response('ok');
        });

        expect($capturedToken)->not->toBeNull()->toBeString();

        /** @var string $capturedToken */
        expect(Str::isUuid($capturedToken))->toBeTrue();
    });

    it('sets a cookie valid for one year (525600 minutes)', function (): void {
        $request = Request::create('/test', 'GET');
        $middleware = new EnsureAnonTokenMiddleware;

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $setCookie = (string) $response->headers->get('Set-Cookie', '');
        // One year = 60 * 24 * 365 = 525600 minutes; cookie is expressed as Max-Age in seconds.
        expect($setCookie)->toContain('Max-Age=31536000');
    });
});
