<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListRequestLogsRequest;
use App\Http\Resources\RequestLogResource;
use App\Models\RequestLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

final class ListRequestLogsController extends Controller
{
    public function __invoke(ListRequestLogsRequest $request): AnonymousResourceCollection
    {
        $query = RequestLog::query()->with('apiKey:id,key_prefix')->orderByDesc('created_at');

        $apiKeyId = $request->validated('api_key_id');
        if ($apiKeyId !== null) {
            /** @var string $apiKeyId */
            $query->forApiKey($apiKeyId);
        }

        $route = $request->validated('route');
        if ($route !== null) {
            /** @var string $route */
            $query->forRoute($route);
        }

        /** @var int|null $status */
        $status = $request->validated('status');
        if ($status !== null) {
            $query->withStatus($status);
        }

        /** @var string|null $fromRaw */
        $fromRaw = $request->validated('from');
        /** @var string|null $toRaw */
        $toRaw = $request->validated('to');
        if ($fromRaw !== null || $toRaw !== null) {
            $from = $fromRaw !== null ? Carbon::parse($fromRaw) : null;
            $to = $toRaw !== null ? Carbon::parse($toRaw) : null;
            $query->betweenDates($from, $to);
        }

        /** @var int $perPage */
        $perPage = $request->validated('per_page') ?? 50;

        $paginator = $query->paginate(perPage: $perPage);

        return RequestLogResource::collection($paginator);
    }
}
