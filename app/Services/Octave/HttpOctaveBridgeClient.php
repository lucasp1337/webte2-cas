<?php

declare(strict_types=1);

namespace App\Services\Octave;

use App\Services\Octave\Exceptions\OctaveBridgeUnavailableException;
use App\Services\Octave\Exceptions\OctaveCommandRejectedException;
use App\Services\Octave\Exceptions\OctaveTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final readonly class HttpOctaveBridgeClient implements OctaveBridgeClient
{
    /**
     * Mirrors the bridge's anchored regex (docker/octave-bridge/src/runner.py).
     * Defence in depth: validate PHP-side too so a malformed `$sessionId` fails
     * fast with a typed exception rather than producing a malformed URL or
     * routing to a different bridge endpoint before the bridge regex runs.
     */
    private const string SESSION_ID_PATTERN = '/^[A-Za-z0-9_-]{8,64}$/';

    /** Slack added to the HTTP transport timeout so the bridge's own clamp wins on a per-call basis. */
    private const int HTTP_TIMEOUT_SLACK_SECONDS = 2;

    public function __construct(
        private string $baseUrl,
        private int $timeoutSeconds,
    ) {}

    public function execute(string $sessionId, string $command, ?int $timeoutSeconds = null): OctaveExecutionResult
    {
        $timeout = $timeoutSeconds ?? $this->timeoutSeconds;

        try {
            $response = Http::timeout($timeout + self::HTTP_TIMEOUT_SLACK_SECONDS)->post("{$this->baseUrl}/exec", [
                'session_id' => $sessionId,
                'command' => $command,
                'timeout_seconds' => $timeout,
            ]);
        } catch (ConnectionException $e) {
            throw new OctaveBridgeUnavailableException(
                "Bridge connection failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        $status = $response->status();

        if ($status === 200) {
            /** @var array<string, mixed> $json */
            $json = $response->json();

            return OctaveExecutionResult::fromArray($json);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        if ($status === 422) {
            $reasonRaw = $json['reason'] ?? 'unknown';
            $reason = is_scalar($reasonRaw) ? (string) $reasonRaw : 'unknown';

            throw new OctaveCommandRejectedException(
                "Command rejected: {$reason}",
                reason: $reason,
            );
        }

        if ($status === 408) {
            throw new OctaveTimeoutException("Octave timed out after {$timeout}s");
        }

        $body = $response->body();

        throw new OctaveBridgeUnavailableException("Bridge returned {$status}: {$body}");
    }

    public function clearSession(string $sessionId): bool
    {
        if (preg_match(self::SESSION_ID_PATTERN, $sessionId) !== 1) {
            throw new OctaveCommandRejectedException(
                'invalid session_id',
                reason: 'session_id must match '.self::SESSION_ID_PATTERN,
            );
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)->delete("{$this->baseUrl}/sessions/{$sessionId}");
        } catch (ConnectionException $e) {
            throw new OctaveBridgeUnavailableException(
                "Bridge connection failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        $status = $response->status();

        if ($status === 200) {
            /** @var array<string, mixed> $json */
            $json = $response->json();
            $cleared = $json['cleared'] ?? false;

            return is_scalar($cleared) ? (bool) $cleared : false;
        }

        $body = $response->body();

        throw new OctaveBridgeUnavailableException("Bridge returned {$status}: {$body}");
    }

    public function pruneSessions(int $olderThanHours): int
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)->post("{$this->baseUrl}/sessions/prune", [
                'older_than_hours' => $olderThanHours,
            ]);
        } catch (ConnectionException $e) {
            throw new OctaveBridgeUnavailableException(
                "Bridge connection failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        $status = $response->status();

        if ($status === 200) {
            /** @var array<string, mixed> $json */
            $json = $response->json();
            $removed = $json['removed'] ?? 0;

            return is_numeric($removed) ? (int) $removed : 0;
        }

        $body = $response->body();

        throw new OctaveBridgeUnavailableException("Bridge returned {$status}: {$body}");
    }
}
