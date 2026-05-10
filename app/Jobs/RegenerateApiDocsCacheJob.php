<?php

declare(strict_types=1);

namespace App\Jobs;

use Dedoc\Scramble\Generator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RegenerateApiDocsCacheJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'regenerate-openapi-spec';
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60];
    }

    public function handle(Generator $generator): void
    {
        Cache::forget('openapi:spec:v1');

        /** @var array<string, mixed> $spec */
        $spec = Cache::remember(
            'openapi:spec:v1',
            now()->addHours(24),
            static function () use ($generator): array {
                /** @var array<string, mixed> $result */
                $result = $generator();

                return $result;
            },
        );

        /** @var array<string, mixed> $specPaths */
        $specPaths = is_array($spec['paths'] ?? null) ? $spec['paths'] : [];

        Log::info('openapi spec cache regenerated', [
            'paths' => count($specPaths),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('openapi spec cache regen failed', [
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
