<?php

declare(strict_types=1);

use App\Jobs\PruneStaleOctaveSessionsJob;
use App\Services\Octave\OctaveBridgeClient;
use App\Services\Octave\Testing\FakeOctaveBridgeClient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

it('calls pruneSessions on the bridge with the configured idle hours', function (): void {
    $fake = new FakeOctaveBridgeClient;
    $fake->setNextPruneResult(3);

    $job = new PruneStaleOctaveSessionsJob(24);
    $job->handle($fake);

    expect($fake->calls)->toHaveCount(1)
        ->and($fake->calls[0]['method'])->toBe('pruneSessions')
        ->and($fake->calls[0]['args'][0])->toBe(24);
});

it('passes the custom olderThanHours value to the bridge', function (): void {
    $fake = new FakeOctaveBridgeClient;

    $job = new PruneStaleOctaveSessionsJob(48);
    $job->handle($fake);

    expect($fake->calls[0]['args'][0])->toBe(48);
});

it('logs at info level on success', function (): void {
    $fake = new FakeOctaveBridgeClient;
    $fake->setNextPruneResult(5);

    Log::shouldReceive('info')
        ->once()
        ->with('octave-bridge: pruned stale sessions', Mockery::subset([
            'removed' => 5,
            'older_than_hours' => 24,
        ]));

    $job = new PruneStaleOctaveSessionsJob(24);
    $job->handle($fake);
});

it('logs at error level when failed() is called', function (): void {
    Log::shouldReceive('error')
        ->once()
        ->with('octave-bridge: prune sessions job failed', Mockery::subset([
            'exception' => RuntimeException::class,
            'message' => 'bridge is down',
            'older_than_hours' => 24,
        ]));

    $job = new PruneStaleOctaveSessionsJob(24);
    $exception = new RuntimeException('bridge is down');
    $job->failed($exception);
});

it('can be dispatched to the queue', function (): void {
    // Use array cache so the ShouldBeUnique lock is always acquirable in tests.
    config()->set('cache.default', 'array');

    Queue::fake();

    PruneStaleOctaveSessionsJob::dispatch(24);

    Queue::assertPushed(PruneStaleOctaveSessionsJob::class, fn (PruneStaleOctaveSessionsJob $job): bool => $job->olderThanHours === 24);
});

it('resolves the bridge from the container and calls prune', function (): void {
    $fake = new FakeOctaveBridgeClient;
    app()->bind(OctaveBridgeClient::class, fn () => $fake);

    $job = new PruneStaleOctaveSessionsJob(24);
    $job->handle(app(OctaveBridgeClient::class));

    expect($fake->calls)->toHaveCount(1)
        ->and($fake->calls[0]['method'])->toBe('pruneSessions');
});

it('is registered in the daily schedule at 02:00', function (): void {
    $schedule = app(Schedule::class);
    $events = $schedule->events();

    $pruneEvent = collect($events)->first(
        fn ($event) => str_contains((string) ($event->description ?? ''), 'prune-stale-octave-sessions')
    );

    expect($pruneEvent)->not->toBeNull();
});
