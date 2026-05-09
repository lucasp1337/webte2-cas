<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RequestLog;

final class RequestLogObserver
{
    public function creating(RequestLog $log): void
    {
        // HasUlids on RequestLog already handles ULID generation for the id column.
        // Here we only backfill created_at when it is not set.
        if (! isset($log->getAttributes()['created_at'])) {
            $log->created_at = now();
        }
    }
}
