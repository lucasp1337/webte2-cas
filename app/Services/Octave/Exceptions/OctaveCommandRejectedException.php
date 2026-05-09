<?php

declare(strict_types=1);

namespace App\Services\Octave\Exceptions;

final class OctaveCommandRejectedException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
