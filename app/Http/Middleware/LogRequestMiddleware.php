<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\RequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class LogRequestMiddleware
{
    /** Tracks whether the fallback-salt warning has been emitted this process. */
    private static bool $saltFallbackWarned = false;

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = Str::ulid()->toBase32();
        $request->attributes->set('request_id', $requestId);

        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $status = $response->getStatusCode();

        $ipHash = $this->hashIp($request);

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        $command = null;
        if ($request->has('command')) {
            /** @var string $rawCommand */
            $rawCommand = $request->input('command', '');
            $command = substr($rawCommand, 0, 1024);
        }

        $parameters = null;
        if ($request->has('parameters')) {
            /** @var array<string, mixed>|null $params */
            $params = $request->input('parameters');
            $parameters = is_array($params) ? $params : null;
        }

        RequestLog::create([
            'id' => $requestId,
            'api_key_id' => $apiKey?->id,
            'method' => $request->method(),
            'route' => $request->route()?->getName() ?? '',
            'path' => $request->path(),
            'status' => $status,
            'success' => $status < 400,
            'duration_ms' => $durationMs,
            'ip_hash' => $ipHash,
            'user_agent' => substr((string) $request->userAgent(), 0, 1024),
            'command' => $command,
            'parameters' => $parameters,
        ]);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function hashIp(Request $request): string
    {
        /** @var string|null $salt */
        $salt = config('cas.stats_anon_token_salt');

        if ($salt === null || $salt === '') {
            if (! self::$saltFallbackWarned) {
                Log::warning('STATS_ANON_TOKEN_SALT is not set; falling back to app.key for IP hashing');
                self::$saltFallbackWarned = true;
            }

            /** @var string $salt */
            $salt = config('app.key');
        }

        return hash_hmac('sha256', (string) $request->ip(), $salt);
    }
}
