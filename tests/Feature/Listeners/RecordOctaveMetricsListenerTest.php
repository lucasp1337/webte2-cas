<?php

declare(strict_types=1);

use App\Events\OctaveCommandExecuted;
use App\Listeners\RecordOctaveMetricsListener;
use App\Services\Octave\OctaveExecutionResult;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

it('increments total + successful counters and aggregates duration on success', function (): void {
    $result = new OctaveExecutionResult('req-1', "2\n", '', 0, 42);

    $listener = new RecordOctaveMetricsListener;
    $listener->handle(new OctaveCommandExecuted($result, 'session-abc'));

    expect(Cache::get(RecordOctaveMetricsListener::KEY_TOTAL))->toBe(1)
        ->and(Cache::get(RecordOctaveMetricsListener::KEY_SUCCESSFUL))->toBe(1)
        ->and(Cache::get(RecordOctaveMetricsListener::KEY_FAILED))->toBeNull()
        ->and(Cache::get(RecordOctaveMetricsListener::KEY_DURATION_MS_TOTAL))->toBe(42);
});

it('increments failed counter when the result is a rejection', function (): void {
    $result = OctaveExecutionResult::rejected("Forbidden token: 'system'");

    $listener = new RecordOctaveMetricsListener;
    $listener->handle(new OctaveCommandExecuted($result, 'session-abc'));

    expect(Cache::get(RecordOctaveMetricsListener::KEY_TOTAL))->toBe(1)
        ->and(Cache::get(RecordOctaveMetricsListener::KEY_SUCCESSFUL))->toBeNull()
        ->and(Cache::get(RecordOctaveMetricsListener::KEY_FAILED))->toBe(1);
});

it('increments failed counter when the result is a timeout', function (): void {
    $result = OctaveExecutionResult::timedOut();

    $listener = new RecordOctaveMetricsListener;
    $listener->handle(new OctaveCommandExecuted($result, 'session-abc'));

    expect(Cache::get(RecordOctaveMetricsListener::KEY_FAILED))->toBe(1);
});

it('aggregates duration across multiple successful events', function (): void {
    $listener = new RecordOctaveMetricsListener;

    $listener->handle(new OctaveCommandExecuted(
        new OctaveExecutionResult('a', '', '', 0, 10),
        'session-abc',
    ));
    $listener->handle(new OctaveCommandExecuted(
        new OctaveExecutionResult('b', '', '', 0, 25),
        'session-abc',
    ));

    expect(Cache::get(RecordOctaveMetricsListener::KEY_TOTAL))->toBe(2)
        ->and(Cache::get(RecordOctaveMetricsListener::KEY_SUCCESSFUL))->toBe(2)
        ->and(Cache::get(RecordOctaveMetricsListener::KEY_DURATION_MS_TOTAL))->toBe(35);
});
