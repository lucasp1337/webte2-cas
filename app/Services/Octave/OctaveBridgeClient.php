<?php

declare(strict_types=1);

namespace App\Services\Octave;

interface OctaveBridgeClient
{
    public function execute(string $sessionId, string $command, ?int $timeoutSeconds = null): OctaveExecutionResult;

    public function clearSession(string $sessionId): bool;

    public function pruneSessions(int $olderThanHours): int;
}
