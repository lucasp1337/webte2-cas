<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ApiKey;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class ApiKeyUsed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ApiKey $apiKey) {}
}
