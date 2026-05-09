<?php

declare(strict_types=1);

namespace App\Actions\Octave;

use App\Events\OctaveCommandExecuted;
use App\Services\Octave\Exceptions\OctaveCommandRejectedException;
use App\Services\Octave\Exceptions\OctaveTimeoutException;
use App\Services\Octave\OctaveBridgeClient;
use App\Services\Octave\OctaveExecutionResult;

/**
 * Runs a single Octave command for a console session and dispatches the
 * `OctaveCommandExecuted` event so downstream listeners (metrics, audit) can
 * react asynchronously.
 *
 * Bridge unavailability is allowed to propagate so the global exception
 * handler can map it to HTTP 503; rejection and timeout are folded into a
 * synthetic {@see OctaveExecutionResult} so the controller still returns a
 * stable response shape and the event still fires (failure is data, not a
 * crash).
 */
final readonly class ExecuteOctaveCommand
{
    public function __construct(private OctaveBridgeClient $bridge) {}

    public function handle(string $sessionId, string $command): OctaveExecutionResult
    {
        try {
            $result = $this->bridge->execute($sessionId, $command);
        } catch (OctaveCommandRejectedException $e) {
            $result = OctaveExecutionResult::rejected($e->reason);
        } catch (OctaveTimeoutException) {
            $result = OctaveExecutionResult::timedOut();
        }

        OctaveCommandExecuted::dispatch($result, $sessionId);

        return $result;
    }
}
