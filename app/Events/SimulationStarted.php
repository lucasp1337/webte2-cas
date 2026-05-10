<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\AnimationName;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class SimulationStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public AnimationName $animation,
        public string $anonToken,
        public string $ip,
        public string $parameterHash,
    ) {}
}
