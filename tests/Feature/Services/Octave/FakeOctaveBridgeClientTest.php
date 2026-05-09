<?php

declare(strict_types=1);

use App\Services\Octave\Exceptions\OctaveTimeoutException;
use App\Services\Octave\OctaveExecutionResult;
use App\Services\Octave\Testing\FakeOctaveBridgeClient;

it('records execute calls', function (): void {
    $fake = new FakeOctaveBridgeClient;
    $fake->execute('session-abc', '1+1', 5);

    expect($fake->calls)->toHaveCount(1)
        ->and($fake->calls[0]['method'])->toBe('execute')
        ->and($fake->calls[0]['args'][0])->toBe('session-abc')
        ->and($fake->calls[0]['args'][1])->toBe('1+1')
        ->and($fake->calls[0]['args'][2])->toBe(5);
});

it('records clearSession calls', function (): void {
    $fake = new FakeOctaveBridgeClient;
    $fake->clearSession('session-abc');

    expect($fake->calls)->toHaveCount(1)
        ->and($fake->calls[0]['method'])->toBe('clearSession')
        ->and($fake->calls[0]['args'][0])->toBe('session-abc');
});

it('records pruneSessions calls', function (): void {
    $fake = new FakeOctaveBridgeClient;
    $fake->pruneSessions(24);

    expect($fake->calls)->toHaveCount(1)
        ->and($fake->calls[0]['method'])->toBe('pruneSessions')
        ->and($fake->calls[0]['args'][0])->toBe(24);
});

it('returns the queued result on next execute', function (): void {
    $fake = new FakeOctaveBridgeClient;
    $queued = new OctaveExecutionResult('req-123', "4\n", '', 0, 50);
    $fake->setNextResult($queued);

    $result = $fake->execute('session-abc', 'a = 2; a+2');

    expect($result->requestId)->toBe('req-123')
        ->and($result->stdout)->toBe("4\n")
        ->and($result->exitCode)->toBe(0)
        ->and($result->durationMs)->toBe(50);
});

it('consumes the queued result only once', function (): void {
    $fake = new FakeOctaveBridgeClient;
    $queued = new OctaveExecutionResult('req-123', "custom\n", '', 0, 1);
    $fake->setNextResult($queued);

    $fake->execute('session-abc', '1+1');
    $second = $fake->execute('session-abc', '2+2');

    // Second call falls back to the default stub
    expect($second->stdout)->toBe("stdout from fake\n");
});

it('returns the queued clear result', function (): void {
    $fake = new FakeOctaveBridgeClient;
    $fake->setNextClearResult(false);

    $result = $fake->clearSession('session-abc');

    expect($result)->toBeFalse();
});

it('returns the queued prune result', function (): void {
    $fake = new FakeOctaveBridgeClient;
    $fake->setNextPruneResult(13);

    $result = $fake->pruneSessions(24);

    expect($result)->toBe(13);
});

it('throws the queued exception on next execute call', function (): void {
    $fake = new FakeOctaveBridgeClient;
    $fake->queueException(new OctaveTimeoutException('boom'));

    expect(fn () => $fake->execute('session-abc', 'while true; end'))
        ->toThrow(OctaveTimeoutException::class, 'boom');
});

it('throws the queued exception on next clearSession call', function (): void {
    $fake = new FakeOctaveBridgeClient;
    $fake->queueException(new OctaveTimeoutException('clear boom'));

    expect(fn () => $fake->clearSession('session-abc'))
        ->toThrow(OctaveTimeoutException::class, 'clear boom');
});

it('consumes the queued exception only once', function (): void {
    $fake = new FakeOctaveBridgeClient;
    $fake->queueException(new OctaveTimeoutException('one-shot'));

    try {
        $fake->execute('session-abc', '1+1');
    } catch (OctaveTimeoutException) {
        // expected
    }

    // Second call should not throw
    $result = $fake->execute('session-abc', '1+1');
    expect($result->exitCode)->toBe(0);
});

it('returns sensible defaults when nothing is queued', function (): void {
    $fake = new FakeOctaveBridgeClient;

    $result = $fake->execute('session-abc', '1+1');
    $cleared = $fake->clearSession('session-abc');
    $pruned = $fake->pruneSessions(24);

    expect($result->requestId)->toBe('fake_id')
        ->and($result->stdout)->toBe("stdout from fake\n")
        ->and($result->stderr)->toBe('')
        ->and($result->exitCode)->toBe(0)
        ->and($result->durationMs)->toBe(1)
        ->and($cleared)->toBeTrue()
        ->and($pruned)->toBe(0);
});
