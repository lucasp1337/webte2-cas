<?php

declare(strict_types=1);

namespace App\Services\Octave;

use App\Services\Octave\Exceptions\OctaveBridgeUnavailableException;

final readonly class OctaveExecutionResult
{
    public function __construct(
        public string $requestId,
        public string $stdout,
        public string $stderr,
        public int $exitCode,
        public int $durationMs,
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
}
