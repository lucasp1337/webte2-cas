<?php

declare(strict_types=1);

use App\Services\Octave\OctaveBridgeClient;

/**
 * Returns true when the octave-bridge container is reachable on port 8001.
 * Used to skip integration tests gracefully in environments without Docker.
 */
function octaveBridgeReachable(): bool
{
    $socket = @fsockopen('octave-bridge', 8001, $errno, $errstr, 1);

    if ($socket === false) {
        return false;
    }

    fclose($socket);

    return true;
}

it('round-trips a real command through the live bridge', function (): void {
    $bridge = app(OctaveBridgeClient::class);
    $result = $bridge->execute('e2e_test_'.bin2hex(random_bytes(4)), 'disp(1+1)', 10);

    expect($result->stdout)->toContain('2')
        ->and($result->exitCode)->toBe(0);
})->group('integration')->skip(fn () => ! octaveBridgeReachable(), 'octave-bridge not reachable; run via docker compose');

it('persists workspace across two calls', function (): void {
    $sid = 'e2e_persist_'.bin2hex(random_bytes(4));
    $bridge = app(OctaveBridgeClient::class);

    $bridge->execute($sid, 'a = 1+1;', 10);
    $result = $bridge->execute($sid, 'disp(a + 2)', 10);

    expect($result->stdout)->toContain('4');

    $bridge->clearSession($sid);
})->group('integration')->skip(fn () => ! octaveBridgeReachable(), 'octave-bridge not reachable; run via docker compose');
