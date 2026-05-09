<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Events\ApiKeyUsed;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = $request->header('X-API-Key', '');

        if ($plaintext === '' || $plaintext === null) {
            return response()->json(
                ['error' => 'unauthorized', 'message' => 'Missing X-API-Key header'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $apiKey = ApiKey::findByPlaintextKey($plaintext);

        if ($apiKey === null) {
            return response()->json(
                ['error' => 'unauthorized', 'message' => 'Invalid API key'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        // Fire event — queued listener updates last_used_at on cli.
        // Middleware must NOT write to the DB directly.
        event(new ApiKeyUsed($apiKey));

        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
