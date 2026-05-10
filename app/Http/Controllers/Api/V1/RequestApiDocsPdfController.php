<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RequestApiDocsPdfRequest;
use App\Jobs\GenerateApiDocsPdfJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class RequestApiDocsPdfController extends Controller
{
    public function __invoke(RequestApiDocsPdfRequest $request): JsonResponse
    {
        $exportId = Str::ulid()->toBase32();
        $locale = $request->locale();

        Cache::put("pdf_export:{$exportId}", [
            'status' => 'queued',
            'locale' => $locale,
        ], now()->addHours(24));

        GenerateApiDocsPdfJob::dispatch($exportId, $locale);

        return response()->json([
            'export_id' => $exportId,
            'status' => 'queued',
            'poll_url' => route('v1.api-docs.pdf.download', ['exportId' => $exportId]),
        ], 202);
    }
}
