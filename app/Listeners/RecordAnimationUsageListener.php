<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SimulationStarted;
use App\Models\AnimationUsage;
use App\Services\GeolocationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecordAnimationUsageListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $timeout = 10;

    public function __construct(private readonly GeolocationService $geo) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [5, 15, 60];
    }

    public function handle(SimulationStarted $event): void
    {
        $configured = config('cas.stats_cooldown_minutes', 10);
        $cooldownMinutes = is_int($configured) ? $configured : 10;

        // Race-safe cooldown: the lock TTL matches the cooldown window so a
        // second worker cannot insert a duplicate row within the same window.
        $lockKey = "stats:cooldown:{$event->animation->value}:{$event->anonToken}";
        $lock = Cache::lock($lockKey, $cooldownMinutes * 60);

        if (! $lock->get()) {
            // Another worker holds the lock — this hit is within cooldown.
            return;
        }

        try {
            // Durable check: the Redis lock is best-effort; the DB read is
            // the authoritative source of truth.
            $recent = AnimationUsage::recentForAnonToken(
                $event->anonToken,
                $event->animation,
                CarbonImmutable::now()->subMinutes($cooldownMinutes),
            )->exists();

            if ($recent) {
                return;
            }

            $geo = $this->geo->lookup($event->ip);

            AnimationUsage::create([
                'animation' => $event->animation->value,
                'anon_token' => $event->anonToken,
                'started_at' => now(),
                'country_iso' => $geo->countryIso,
                'country' => $geo->country,
                'city' => $geo->city,
            ]);
        } finally {
            $lock->release();
        }
    }

    public function failed(SimulationStarted $event, Throwable $exception): void
    {
        Log::error('animation-usage: failed to record usage', [
            'animation' => $event->animation->value,
            'anon_token' => $event->anonToken,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
