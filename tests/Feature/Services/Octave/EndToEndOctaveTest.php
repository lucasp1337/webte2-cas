<?php

declare(strict_types=1);

use App\Services\Octave\OctaveBridgeClient;

/**
 * Returns true when the bridge service is reachable on the configured URL.
 * Host/port are derived from `cas.octave_bridge_url` so the same probe works
 * locally (host=octave-bridge via the docker network) and in CI (host=localhost
 * via the published port from docker-compose.ci.yml).
 *
 * In CI (CI=true) the tests are NEVER skipped — if the bridge is unreachable
 * the workflow fails loudly at the dedicated "Probe bridge port from runner
 * host" curl step before Pest even runs, so this path should not be hit.
 * In local dev without docker-compose the tests skip gracefully.
 */
function octaveBridgeReachable(): bool
{
    $configured = config('cas.octave_bridge_url', 'http://octave-bridge:8001');
    $url = is_string($configured) ? $configured : 'http://octave-bridge:8001';
    $parts = parse_url($url);

    if ($parts === false) {
        return false;
    }

    $host = is_string($parts['host'] ?? null) ? $parts['host'] : 'octave-bridge';
    $port = is_int($parts['port'] ?? null) ? $parts['port'] : 8001;

    $socket = @fsockopen($host, $port, $errno, $errstr, 3);

    if ($socket === false) {
        return false;
    }

    fclose($socket);

    return true;
}

/**
 * Whether to skip when the bridge is unreachable.
 * In CI the bridge is always expected to be up — skip is disabled so a missing
 * bridge produces a test failure rather than a silent pass.
 * Locally, developers without docker-compose can opt out via --exclude-group=integration.
 */
function shouldSkipIfBridgeDown(): bool
{
    // GitHub Actions sets CI=true. Any truthy value disables the skip.
    $isCI = filter_var(env('CI', false), FILTER_VALIDATE_BOOLEAN) === true;

    if ($isCI) {
        return false;
    }

    return ! octaveBridgeReachable();
}

it('round-trips a real command through the live bridge', function (): void {
    $bridge = app(OctaveBridgeClient::class);
    $result = $bridge->execute('e2e_test_'.bin2hex(random_bytes(4)), 'disp(1+1)', 10);

    expect($result->stdout)->toContain('2')
        ->and($result->exitCode)->toBe(0);
})->group('integration')->skip(fn () => shouldSkipIfBridgeDown(), 'octave-bridge not reachable; run via docker compose');

it('persists workspace across two calls', function (): void {
    $sid = 'e2e_persist_'.bin2hex(random_bytes(4));
    $bridge = app(OctaveBridgeClient::class);

    $bridge->execute($sid, 'a = 1+1;', 10);
    $result = $bridge->execute($sid, 'disp(a + 2)', 10);

    expect($result->stdout)->toContain('4');

    $bridge->clearSession($sid);
})->group('integration')->skip(fn () => shouldSkipIfBridgeDown(), 'octave-bridge not reachable; run via docker compose');
