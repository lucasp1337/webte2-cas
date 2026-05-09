<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RequestLog;
use Illuminate\Support\Str;

final class RequestLogObserver
{
    public function creating(RequestLog $log): void
    {
        if ($log->id === null || $log->id === '') {
            $log->id = Str::ulid()->toBase32();
        }

        if ($log->created_at === null) {
            $log->created_at = now();
        }
    }
}
