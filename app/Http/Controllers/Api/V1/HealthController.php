<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\HealthCheckResource;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class HealthController extends Controller
{
    #[ScrambleResponse(status: 200, type: HealthCheckResource::class)]
    #[ScrambleResponse(status: 503, type: HealthCheckResource::class)]
    public function __invoke(): JsonResponse
    {
        $mysql = $this->checkMysql();
        $redis = $this->checkRedis();
        $bridge = $this->checkOctaveBridge();

        $allOk = $mysql === 'ok' && $redis === 'ok' && $bridge === 'ok';

        return HealthCheckResource::make([
            'status' => $allOk ? 'ok' : 'degraded',
            'dependencies' => [
                'mysql' => $mysql,
                'redis' => $redis,
                'octave_bridge' => $bridge,
            ],
        ])
            ->response()
            ->setStatusCode($allOk ? 200 : 503);
    }

    private function checkMysql(): string
    {
        try {
            DB::connection()->getPdo();

            return 'ok';
        } catch (Throwable) {
            return 'down';
        }
    }

    private function checkRedis(): string
    {
        try {
            Redis::ping();

            return 'ok';
        } catch (Throwable) {
            return 'down';
        }
    }

    private function checkOctaveBridge(): string
    {
        try {
            /** @var string $bridgeUrl */
            $bridgeUrl = config('cas.octave_bridge_url');

            $response = Http::timeout(2)->get($bridgeUrl.'/health');

            return $response->successful() ? 'ok' : 'down';
        } catch (Throwable) {
            return 'down';
        }
    }
}
