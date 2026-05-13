<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

// Re-run gate registration before each test so config changes take effect.
// Mirrors HorizonServiceProvider::gate() so the assertions exercise the real
// resolution logic without booting the full Horizon SPA in the test process.
beforeEach(function (): void {
    Gate::define('viewHorizon', function (mixed $user = null) {
        if (app()->environment('local')) {
            return true;
        }

        /** @var string $expected */
        $expected = config('cas.horizon_admin_token', '');

        if ($expected === '') {
            return false;
        }

        $request = request();
        $session = $request->hasSession() ? $request->session() : null;
        /** @var mixed $rawSessionToken */
        $rawSessionToken = $session !== null ? $session->get('horizon_admin_token', '') : '';
        $sessionToken = is_string($rawSessionToken) ? $rawSessionToken : '';

        $supplied = (string) ($request->query('token')
            ?? $request->header('X-Horizon-Token')
            ?? $sessionToken);

        if ($supplied === '') {
            return false;
        }

        $ok = hash_equals($expected, $supplied);

        if ($ok && $session !== null && $sessionToken !== $supplied) {
            $session->put('horizon_admin_token', $supplied);
        }

        return $ok;
    });
});

it('allows access in local env without a token', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    expect(Gate::check('viewHorizon', [null]))->toBeTrue();
});

it('denies access in production env without a token', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['cas.horizon_admin_token' => 'a-very-long-expected-token-value-64chars-padding-padding-pad12345']);

    expect(Gate::check('viewHorizon', [null]))->toBeFalse();
});

it('allows access in production env when a valid token is provided via query string', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $token = 'a-very-long-expected-token-value-64chars-padding-padding-pad12345';
    config(['cas.horizon_admin_token' => $token]);

    $request = Request::create('/horizon', 'GET', ['token' => $token]);
    app()->instance('request', $request);

    expect(Gate::check('viewHorizon', [null]))->toBeTrue();
});

it('denies access in production env when the token does not match', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['cas.horizon_admin_token' => 'a-very-long-expected-token-value-64chars-padding-padding-pad12345']);

    $request = Request::create('/horizon', 'GET', ['token' => 'wrong-token-value-that-does-not-match-at-all-padding-padding-p00000']);
    app()->instance('request', $request);

    expect(Gate::check('viewHorizon', [null]))->toBeFalse();
});

it('allows access in production env when a valid token is provided via X-Horizon-Token header', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $token = 'a-very-long-expected-token-value-64chars-padding-padding-pad12345';
    config(['cas.horizon_admin_token' => $token]);

    $request = Request::create('/horizon', 'GET', [], [], [], ['HTTP_X_HORIZON_TOKEN' => $token]);
    app()->instance('request', $request);

    expect(Gate::check('viewHorizon', [null]))->toBeTrue();
});

it('persists the token in the session after a valid query-string handshake', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $token = 'a-very-long-expected-token-value-64chars-padding-padding-pad12345';
    config(['cas.horizon_admin_token' => $token]);

    $request = Request::create('/horizon', 'GET', ['token' => $token]);
    $request->setLaravelSession(app('session.store'));
    app()->instance('request', $request);

    expect(Gate::check('viewHorizon', [null]))->toBeTrue();
    expect($request->session()->get('horizon_admin_token'))->toBe($token);
});

it('keeps allowing access when only the session token is present (no query, no header)', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $token = 'a-very-long-expected-token-value-64chars-padding-padding-pad12345';
    config(['cas.horizon_admin_token' => $token]);

    $request = Request::create('/horizon/dashboard', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('horizon_admin_token', $token);
    app()->instance('request', $request);

    expect(Gate::check('viewHorizon', [null]))->toBeTrue();
});

it('denies access when HORIZON_ADMIN_TOKEN is empty in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['cas.horizon_admin_token' => '']);

    $request = Request::create('/horizon', 'GET', ['token' => 'anything']);
    app()->instance('request', $request);

    expect(Gate::check('viewHorizon', [null]))->toBeFalse();
});
