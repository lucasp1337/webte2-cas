<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ApiKey;
use App\Models\RequestLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestLog
 */
final class RequestLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RequestLog $model */
        $model = $this->resource;

        $apiKeyPrefix = null;
        if ($model->relationLoaded('apiKey')) {
            $key = $model->getRelation('apiKey');
            if ($key instanceof ApiKey) {
                $apiKeyPrefix = $key->key_prefix;
            }
        }

        return [
            'request_id' => $model->id,
            'method' => $model->method,
            'route' => $model->path,
            'status' => $model->status,
            'duration_ms' => $model->duration_ms,
            'api_key_prefix' => $apiKeyPrefix,
            'created_at' => $model->created_at->toIso8601String(),
        ];
    }
}
