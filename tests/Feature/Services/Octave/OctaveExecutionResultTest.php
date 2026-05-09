<?php

declare(strict_types=1);

use App\Services\Octave\OctaveExecutionResult;

it('creates a successful result via the constructor', function (): void {
    $result = new OctaveExecutionResult('req-1', "ok\n", '', 0, 12);

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->isRejected())->toBeFalse()
        ->and($result->isTimedOut())->toBeFalse()
        ->and($result->rejectionReason)->toBeNull();
});

it('creates a rejected result via the factory', function (): void {
    $result = OctaveExecutionResult::rejected('Forbidden token');

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->isRejected())->toBeTrue()
        ->and($result->rejectionReason)->toBe('Forbidden token')
        ->and($result->stderr)->toBe('Forbidden token')
        ->and($result->exitCode)->toBe(OctaveExecutionResult::EXIT_CODE_REJECTED);
});

it('creates a timed-out result via the factory', function (): void {
    $result = OctaveExecutionResult::timedOut();

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->isTimedOut())->toBeTrue()
        ->and($result->exitCode)->toBe(OctaveExecutionResult::EXIT_CODE_TIMEOUT);
});
