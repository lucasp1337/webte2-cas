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

        return response()->json($spec);
    }
}
