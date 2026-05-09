<?php

declare(strict_types=1);

namespace App\Services\Octave;

use App\Services\Octave\Exceptions\OctaveBridgeUnavailableException;

final readonly class OctaveExecutionResult
{
    public const int EXIT_CODE_REJECTED = 422;

    public const int EXIT_CODE_TIMEOUT = 408;

    public function __construct(
        public string $requestId,
        public string $stdout,
        public string $stderr,
        public int $exitCode,
        public int $durationMs,
        public ?string $rejectionReason = null,
    ) {}

    /**
     * Construct from the JSON-decoded bridge response body.
     *
     * Raises OctaveBridgeUnavailableException if any required field is missing
     * or cannot be cast to the expected type — this indicates a protocol mismatch
     * between the PHP client and the bridge service.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        try {
            $requestId = array_key_exists('request_id', $payload) && is_scalar($payload['request_id'])
                ? (string) $payload['request_id']
                : '';
            $stdout = array_key_exists('stdout', $payload) && is_scalar($payload['stdout'])
                ? (string) $payload['stdout']
                : '';
            $stderr = array_key_exists('stderr', $payload) && is_scalar($payload['stderr'])
                ? (string) $payload['stderr']
                : '';

            if (! array_key_exists('exit_code', $payload) || ! is_numeric($payload['exit_code'])) {
                throw new \UnexpectedValueException('Missing or invalid exit_code');
            }

            if (! array_key_exists('duration_ms', $payload) || ! is_numeric($payload['duration_ms'])) {
                throw new \UnexpectedValueException('Missing or invalid duration_ms');
            }

            $exitCode = (int) $payload['exit_code'];
            $durationMs = (int) $payload['duration_ms'];
        } catch (\UnexpectedValueException $e) {
            throw new OctaveBridgeUnavailableException(
                'Bridge response is missing required fields: '.$e->getMessage(),
                previous: $e,
            );
        }

        return new self(
            requestId: $requestId,
            stdout: $stdout,
            stderr: $stderr,
            exitCode: $exitCode,
            durationMs: $durationMs,
        );
    }

    /**
     * Synthetic result used when the bridge rejects a command. The exit code
     * surfaces as 422 so the controller can map it to HTTP 422 without a
     * second exception trip.
     */
    public static function rejected(string $reason): self
    {
        return new self(
            requestId: '',
            stdout: '',
            stderr: $reason,
            exitCode: self::EXIT_CODE_REJECTED,
            durationMs: 0,
            rejectionReason: $reason,
        );
    }

    /**
     * Synthetic result used when Octave times out. Exit code mirrors HTTP 408
     * so the resource layer can serialise the status cleanly.
     */
    public static function timedOut(): self
    {
        return new self(
            requestId: '',
            stdout: '',
            stderr: 'Octave execution timed out',
            exitCode: self::EXIT_CODE_TIMEOUT,
            durationMs: 0,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    public function isRejected(): bool
    {
        return $this->exitCode === self::EXIT_CODE_REJECTED;
    }

    public function isTimedOut(): bool
    {
        return $this->exitCode === self::EXIT_CODE_TIMEOUT;
    }
}
