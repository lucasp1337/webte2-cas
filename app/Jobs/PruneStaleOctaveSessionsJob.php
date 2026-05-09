<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Octave\OctaveBridgeClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PruneStaleOctaveSessionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $olderThanHours = 24) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function uniqueId(): string
    {
        return 'prune-octave-sessions';
    }

    public function handle(OctaveBridgeClient $bridge): void
    {
        $removed = $bridge->pruneSessions($this->olderThanHours);

        Log::info('octave-bridge: pruned stale sessions', [
            'removed' => $removed,
            'older_than_hours' => $this->olderThanHours,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('octave-bridge: prune sessions job failed', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'older_than_hours' => $this->olderThanHours,
        ]);
    }
}
