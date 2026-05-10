<?php

declare(strict_types=1);

use App\Data\PendulumParameters;
use App\Services\Octave\Exceptions\OctaveBridgeUnavailableException;
use App\Support\Octave\TrajectoryParser;
use Tests\TestCase;

uses(TestCase::class);

/** Build a minimal PendulumParameters DTO for parser tests. */
function parserTestParameters(float $durationSeconds = 0.08, float $stepSize = 0.02): PendulumParameters
{
    return new PendulumParameters(
        cart_mass: 1.0,
        pendulum_mass: 0.2,
        friction: 0.1,
        inertia: 0.006,
        gravity: 9.81,
        length: 0.5,
        reference_position: 0.2,
        initial_position: 0.0,
        initial_angle: 0.15,
        duration_seconds: $durationSeconds,
        step_size: $stepSize,
    );
}

/** Load the golden fixture from tests/Fixtures/octave/pendulum-default.txt. */
function goldenFixtureStdout(): string
{
    return (string) file_get_contents(
        base_path('tests/Fixtures/octave/pendulum-default.txt'),
    );
}

/** Build a minimal synthetic Octave stdout with `$rows` identical state rows. */
function syntheticStdout(int $rows, int $tCount = -1, bool $nanInSamples = false): string
{
    $tCount = $tCount < 0 ? $rows : $tCount;

    $tValues = [];
    for ($i = 0; $i < $tCount; $i++) {
        $tValues[] = sprintf('%.15e', $i * 0.02);
    }
    $tLine = '   '.implode('   ', $tValues);

    $xLines = [];
    for ($i = 0; $i < $rows; $i++) {
        if ($nanInSamples && $i === 2) {
            $xLines[] = '   NaN   0.00000000000000000e+00   1.50000000000000000e-01   0.00000000000000000e+00';
        } else {
            $xLines[] = '   0.00000000000000000e+00   0.00000000000000000e+00   1.50000000000000000e-01   0.00000000000000000e+00';
        }
    }
    $xBlock = implode("\n", $xLines);

    $finalState = '   1.92000000000000001e-02   2.40000000000000002e-01   1.19500000000000001e-01  -5.00000000000000000e-01';

    return <<<EOT
        ---T---
        {$tLine}
        ---END-T---
        ---X---
        {$xBlock}
        ---END-X---
        ---THETA---
           0.00000000000000000e+00   1.50000000000000000e-01
        ---END-THETA---
        ---FINAL_STATE---
        {$finalState}
        ---END-FINAL_STATE---
        EOT;
}

describe('TrajectoryParser', function (): void {
    it('parses a complete trajectory from the golden fixture', function (): void {
        /** @var TrajectoryParser $parser */
        $parser = app(TrajectoryParser::class);
        $params = parserTestParameters(durationSeconds: 0.08, stepSize: 0.02);

        $trajectory = $parser->parse(goldenFixtureStdout(), $params, 'req-golden');

        // 5 samples: t=0, 0.02, 0.04, 0.06, 0.08
        expect($trajectory->samples)->toHaveCount(5);

        // final_state has exactly 4 finite floats
        expect($trajectory->finalState)->toHaveCount(4);
        foreach ($trajectory->finalState as $value) {
            expect(is_finite($value))->toBeTrue();
        }

        // t values are strictly increasing
        $times = array_column($trajectory->samples, 't');
        for ($i = 1; $i < count($times); $i++) {
            expect($times[$i])->toBeGreaterThan($times[$i - 1]);
        }
    });

    it('throws OctaveBridgeUnavailableException when a marker block is missing', function (): void {
        /** @var TrajectoryParser $parser */
        $parser = app(TrajectoryParser::class);

        // Remove the FINAL_STATE block entirely
        $truncated = <<<'EOT'
            ---T---
               0.00000000000000000e+00   2.00000000000000001e-02
            ---END-T---
            ---X---
               0.00000000000000000e+00   0.00000000000000000e+00   1.50000000000000000e-01   0.00000000000000000e+00
               1.20000000000000001e-03   6.00000000000000001e-02   1.45000000000000001e-01  -2.50000000000000000e-01
            ---END-X---
            EOT;

        expect(fn () => $parser->parse($truncated, parserTestParameters(), 'req-trunc'))
            ->toThrow(OctaveBridgeUnavailableException::class);
    });

    it('throws when the X matrix row count does not match the T vector length', function (): void {
        /** @var TrajectoryParser $parser */
        $parser = app(TrajectoryParser::class);

        // 3 T values but only 2 X rows
        $mismatched = syntheticStdout(rows: 2, tCount: 3);

        expect(fn () => $parser->parse($mismatched, parserTestParameters(), 'req-mismatch'))
            ->toThrow(OctaveBridgeUnavailableException::class);
    });

    it('handles scientific notation floats correctly', function (): void {
        /** @var TrajectoryParser $parser */
        $parser = app(TrajectoryParser::class);

        $sciNotation = <<<'EOT'
            ---T---
               1.5e-3   3.0e-3
            ---END-T---
            ---X---
               1.5e-3   0.0   1.5e-1   0.0
               3.0e-3   0.0   1.4e-1   0.0
            ---END-X---
            ---THETA---
               1.5e-3   1.5e-1
               3.0e-3   1.4e-1
            ---END-THETA---
            ---FINAL_STATE---
               3.0e-3   0.0   1.4e-1   0.0
            ---END-FINAL_STATE---
            EOT;

        $trajectory = $parser->parse($sciNotation, parserTestParameters(), 'req-sci');

        expect($trajectory->samples)->toHaveCount(2);
        expect(abs($trajectory->samples[0]['t'] - 0.0015))->toBeLessThan(1e-9);
        expect(abs($trajectory->samples[1]['t'] - 0.003))->toBeLessThan(1e-9);
    });

    it('throws when a NaN token appears in the X matrix', function (): void {
        /** @var TrajectoryParser $parser */
        $parser = app(TrajectoryParser::class);

        $withNan = syntheticStdout(rows: 3, nanInSamples: true);

        expect(fn () => $parser->parse($withNan, parserTestParameters(), 'req-nan'))
            ->toThrow(OctaveBridgeUnavailableException::class);
    });
});
