<?php

declare(strict_types=1);

namespace App\Events;

use App\Services\Octave\OctaveExecutionResult;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class OctaveCommandExecuted
{
    use Dispatchable;

    public function __construct(
        public OctaveExecutionResult $result,
        public string $sessionId,
    ) {}
}
