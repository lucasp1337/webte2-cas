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
}
