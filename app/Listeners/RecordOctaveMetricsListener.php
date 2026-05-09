<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OctaveCommandExecuted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Increments cache counters for every Octave execution. The counters are
 * plumbing for a future system-health page; the queued path keeps them off
 * the request thread.
 */
final class RecordOctaveMetricsListener implements ShouldQueue
{
    use InteractsWithQueue;

    public const string KEY_TOTAL = 'metrics:octave:total';

    public const string KEY_SUCCESSFUL = 'metrics:octave:successful';

    public const string KEY_FAILED = 'metrics:octave:failed';

    public const string KEY_DURATION_MS_TOTAL = 'metrics:octave:duration_ms_total';

    public int $tries = 3;

    public int $timeout = 30;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function handle(OctaveCommandExecuted $event): void
    {
        Cache::increment(self::KEY_TOTAL);

        if ($event->result->isSuccessful()) {
            Cache::increment(self::KEY_SUCCESSFUL);
        } else {
            Cache::increment(self::KEY_FAILED);
        }

        if ($event->result->durationMs > 0) {
            Cache::increment(self::KEY_DURATION_MS_TOTAL, $event->result->durationMs);
        }
    }

    public function failed(OctaveCommandExecuted $event, Throwable $exception): void
    {
        Log::error('octave-metrics: failed to record cache counters', [
            'session_id' => $event->sessionId,
            'exit_code' => $event->result->exitCode,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
