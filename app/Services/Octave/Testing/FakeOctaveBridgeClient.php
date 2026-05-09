<?php

declare(strict_types=1);

namespace App\Services\Octave\Testing;

use App\Services\Octave\OctaveBridgeClient;
use App\Services\Octave\OctaveExecutionResult;

/**
 * In-memory fake for OctaveBridgeClient — swap in via the service container
 * in feature tests to avoid real HTTP calls to the bridge service.
 *
 * Usage:
 *   $this->app->bind(OctaveBridgeClient::class, FakeOctaveBridgeClient::class);
 *   $fake = app(OctaveBridgeClient::class);
 */
final class FakeOctaveBridgeClient implements OctaveBridgeClient
{
    /** @var array<int, array{method: string, args: array<int, mixed>}> */
    public array $calls = [];

    private ?\Throwable $nextException = null;

    private ?OctaveExecutionResult $nextResult = null;

    private ?bool $nextClearResult = null;

    private ?int $nextPruneResult = null;

    public function queueException(\Throwable $exception): void
    {
        $this->nextException = $exception;
    }

    public function setNextResult(OctaveExecutionResult $result): void
    {
        $this->nextResult = $result;
    }

    public function setNextClearResult(bool $cleared): void
    {
        $this->nextClearResult = $cleared;
    }

    public function setNextPruneResult(int $removed): void
    {
        $this->nextPruneResult = $removed;
    }

    public function execute(string $sessionId, string $command, ?int $timeoutSeconds = null): OctaveExecutionResult
    {
        $this->calls[] = ['method' => 'execute', 'args' => [$sessionId, $command, $timeoutSeconds]];

        $this->throwIfQueued();

        if ($this->nextResult !== null) {
            $result = $this->nextResult;
            $this->nextResult = null;

            return $result;
        }

        return new OctaveExecutionResult('fake_id', "stdout from fake\n", '', 0, 1);
    }

    public function clearSession(string $sessionId): bool
    {
        $this->calls[] = ['method' => 'clearSession', 'args' => [$sessionId]];

        $this->throwIfQueued();

        if ($this->nextClearResult !== null) {
            $result = $this->nextClearResult;
            $this->nextClearResult = null;

            return $result;
        }

        return true;
    }

    public function pruneSessions(int $olderThanHours): int
    {
        $this->calls[] = ['method' => 'pruneSessions', 'args' => [$olderThanHours]];

        $this->throwIfQueued();

        if ($this->nextPruneResult !== null) {
            $result = $this->nextPruneResult;
            $this->nextPruneResult = null;

            return $result;
        }

        return 0;
    }

    private function throwIfQueued(): void
    {
        if ($this->nextException !== null) {
            $exception = $this->nextException;
            $this->nextException = null;

            throw $exception;
        }
    }
}
