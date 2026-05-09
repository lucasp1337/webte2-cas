<?php

declare(strict_types=1);

use App\Services\Octave\Exceptions\OctaveBridgeUnavailableException;
use App\Services\Octave\Exceptions\OctaveCommandRejectedException;
use App\Services\Octave\Exceptions\OctaveTimeoutException;
use App\Services\Octave\HttpOctaveBridgeClient;
use App\Services\Octave\OctaveExecutionResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('cas.octave_bridge_url', 'http://octave-bridge:8001');
    config()->set('cas.octave_bridge_timeout_seconds', 5);
});

it('returns OctaveExecutionResult on 200', function (): void {
    Http::fake([
        '*/exec' => Http::response([
            'request_id' => '01J9V0Z',
            'stdout' => "2\n",
            'stderr' => '',
            'exit_code' => 0,
            'duration_ms' => 42,
        ], 200),
    ]);

    $client = new HttpOctaveBridgeClient('http://octave-bridge:8001', 5);
    $result = $client->execute('abcdefgh', '1+1', 10);

    expect($result)->toBeInstanceOf(OctaveExecutionResult::class)
        ->and($result->stdout)->toBe("2\n")
        ->and($result->stderr)->toBe('')
        ->and($result->exitCode)->toBe(0)
        ->and($result->durationMs)->toBe(42)
        ->and($result->requestId)->toBe('01J9V0Z');
});

it('throws OctaveCommandRejectedException on 422', function (): void {
    Http::fake([
        '*/exec' => Http::response([
            'error' => 'command_rejected',
            'reason' => "Forbidden token: 'system'",
            'request_id' => 'req-abc',
        ], 422),
    ]);

    $client = new HttpOctaveBridgeClient('http://octave-bridge:8001', 5);

    expect(fn () => $client->execute('abcdefgh', "system('ls')"))
        ->toThrow(OctaveCommandRejectedException::class);
});

it('populates the reason on OctaveCommandRejectedException', function (): void {
    Http::fake([
        '*/exec' => Http::response([
            'error' => 'command_rejected',
            'reason' => "Forbidden token: 'system'",
            'request_id' => 'req-abc',
        ], 422),
    ]);

    $client = new HttpOctaveBridgeClient('http://octave-bridge:8001', 5);

    try {
        $client->execute('abcdefgh', "system('ls')");
        expect(false)->toBeTrue('Expected exception was not thrown');
    } catch (OctaveCommandRejectedException $e) {
        expect($e->reason)->toBe("Forbidden token: 'system'");
    }
});

it('throws OctaveTimeoutException on 408', function (): void {
    Http::fake([
        '*/exec' => Http::response([
            'error' => 'octave_timeout',
            'request_id' => 'req-abc',
        ], 408),
    ]);

    $client = new HttpOctaveBridgeClient('http://octave-bridge:8001', 5);

    expect(fn () => $client->execute('abcdefgh', 'while true; end'))
        ->toThrow(OctaveTimeoutException::class);
});

it('throws OctaveBridgeUnavailableException on 503', function (): void {
    Http::fake([
        '*/exec' => Http::response([
            'error' => 'bridge_unavailable',
            'request_id' => 'req-abc',
        ], 503),
    ]);

    $client = new HttpOctaveBridgeClient('http://octave-bridge:8001', 5);

    expect(fn () => $client->execute('abcdefgh', '1+1'))
        ->toThrow(OctaveBridgeUnavailableException::class);
});

it('throws OctaveBridgeUnavailableException on 500', function (): void {
    Http::fake([
        '*/exec' => Http::response([
            'error' => 'internal_error',
            'request_id' => 'req-abc',
        ], 500),
    ]);

    $client = new HttpOctaveBridgeClient('http://octave-bridge:8001', 5);

    expect(fn () => $client->execute('abcdefgh', '1+1'))
        ->toThrow(OctaveBridgeUnavailableException::class);
});

it('throws OctaveBridgeUnavailableException on connection failure', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $client = new HttpOctaveBridgeClient('http://octave-bridge:8001', 5);

    expect(fn () => $client->execute('abcdefgh', '1+1'))
        ->toThrow(OctaveBridgeUnavailableException::class);
});

it('clearSession returns the cleared bool from the bridge response', function (): void {
    Http::fake([
        '*/sessions/*' => Http::response([
            'cleared' => true,
            'request_id' => 'req-clear',
        ], 200),
    ]);

    $client = new HttpOctaveBridgeClient('http://octave-bridge:8001', 5);
    $cleared = $client->clearSession('abcdefgh');

    expect($cleared)->toBeTrue();
});

it('clearSession returns false when the bridge reports not cleared', function (): void {
    Http::fake([
        '*/sessions/*' => Http::response([
            'cleared' => false,
            'request_id' => 'req-clear',
        ], 200),
    ]);

    $client = new HttpOctaveBridgeClient('http://octave-bridge:8001', 5);
    $cleared = $client->clearSession('abcdefgh');

    expect($cleared)->toBeFalse();
});

it('clearSession throws OctaveBridgeUnavailableException on non-200', function (): void {
    Http::fake([
        '*/sessions/*' => Http::response([], 500),
    ]);

    $client = new HttpOctaveBridgeClient('http://octave-bridge:8001', 5);

    expect(fn () => $client->clearSession('abcdefgh'))
        ->toThrow(OctaveBridgeUnavailableException::class);
});

it('pruneSessions returns the removed count', function (): void {
    Http::fake([
        '*/sessions/prune' => Http::response([
            'removed' => 7,
            'older_than_hours' => 24,
            'request_id' => 'req-prune',
        ], 200),
    ]);

    $client = new HttpOctaveBridgeClient('http://octave-bridge:8001', 5);
    $removed = $client->pruneSessions(24);

    expect($removed)->toBe(7);
});

it('pruneSessions throws OctaveBridgeUnavailableException on non-200', function (): void {
    Http::fake([
        '*/sessions/prune' => Http::response([], 500),
    ]);

    $client = new HttpOctaveBridgeClient('http://octave-bridge:8001', 5);

    expect(fn () => $client->pruneSessions(24))
        ->toThrow(OctaveBridgeUnavailableException::class);
});

it('uses the per-call timeout override when provided', function (): void {
    Http::fake([
        '*/exec' => Http::response([
            'request_id' => 'req-timeout-override',
            'stdout' => "ok\n",
            'stderr' => '',
            'exit_code' => 0,
            'duration_ms' => 10,
        ], 200),
    ]);

    $client = new HttpOctaveBridgeClient('http://octave-bridge:8001', 5);
    $result = $client->execute('abcdefgh', 'disp("ok")', 15);

    expect($result->stdout)->toBe("ok\n");
});
