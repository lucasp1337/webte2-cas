<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Generator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class OpenApiSpecController extends Controller
{
    public function __invoke(Generator $generator): JsonResponse
    {
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

        // Use a relative server URL so Swagger UI's "Try it out" requests
        // follow the browser's current origin (e.g. localhost:8080 in dev).
        // Scramble's built-in server resolution would absolutise against
        // APP_URL on port 80, sending preflights to apache.
        $spec['servers'] = [
            ['url' => '/api/v1', 'description' => 'Same-origin'],
        ];

        // Scramble's Response attribute always emits a media-type schema, even
        // for status codes that carry no body. RFC 7231 §6.3.5 forbids 204
        // responses from carrying content, so strip the spec's content map for
        // every 204 entry — otherwise Swagger UI renders a fake "string" body.
        self::stripContentFromNoBodyResponses($spec);

        // Scramble doesn't infer 401/429 from middleware. Every operation that
        // declares `security` will return 401 on a missing/invalid key and
        // 429 when the rate limiter trips, so add canonical entries unless
        // the controller already documents them.
        self::injectAuthAndRateLimitResponses($spec);

        return response()->json($spec);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private static function stripContentFromNoBodyResponses(array &$spec): void
    {
        if (! isset($spec['paths']) || ! is_array($spec['paths'])) {
            return;
        }

        foreach ($spec['paths'] as &$pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }
            foreach ($pathItem as $method => &$operation) {
                if (! is_array($operation) || ! isset($operation['responses']) || ! is_array($operation['responses'])) {
                    continue;
                }
                foreach ($operation['responses'] as $status => &$response) {
                    if (in_array((string) $status, ['204', '304'], true) && is_array($response)) {
                        unset($response['content']);
                    }
                }
                unset($response);
            }
            unset($operation);
        }
        unset($pathItem);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private static function injectAuthAndRateLimitResponses(array &$spec): void
    {
        if (! isset($spec['paths']) || ! is_array($spec['paths'])) {
            return;
        }

        /** @var array<string, mixed> $unauthorized */
        $unauthorized = [
            'description' => 'Missing or invalid API key',
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'error' => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                        ],
                        'required' => ['error', 'message'],
                    ],
                ],
            ],
        ];

        /** @var array<string, mixed> $tooManyRequests */
        $tooManyRequests = [
            'description' => 'Rate limit exceeded; retry after the window resets',
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // Public routes — explicitly mark them as `security: []` so Swagger UI
        // doesn't ask the user to authorise (and so the 401/429 injector below
        // skips them). Scramble only emits root-level security, so all paths
        // inherit ApiKeyAuth by default; these few are the exceptions.
        $publicPaths = [
            '/health',
            '/api-docs/pdf',
            '/api-docs/pdf/{exportId}',
        ];

        $rootSecurity = $spec['security'] ?? null;
        $hasRootSecurity = is_array($rootSecurity) && $rootSecurity !== [];

        foreach ($spec['paths'] as $path => &$pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }
            $isPublic = in_array($path, $publicPaths, true);

            foreach ($pathItem as $method => &$operation) {
                if (! is_array($operation) || ! isset($operation['responses']) || ! is_array($operation['responses'])) {
                    continue;
                }

                if ($isPublic) {
                    $operation['security'] = [];

                    continue;
                }

                $opSecurity = $operation['security'] ?? null;
                $effectivelyAuthenticated = is_array($opSecurity)
                    ? $opSecurity !== []
                    : $hasRootSecurity;

                if (! $effectivelyAuthenticated) {
                    continue;
                }

                if (! array_key_exists('401', $operation['responses'])) {
                    $operation['responses']['401'] = $unauthorized;
                }
                if (! array_key_exists('429', $operation['responses'])) {
                    $operation['responses']['429'] = $tooManyRequests;
                }
            }
            unset($operation);
        }
        unset($pathItem);
    }
}
