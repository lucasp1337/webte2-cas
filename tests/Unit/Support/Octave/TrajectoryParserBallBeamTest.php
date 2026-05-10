<?php

declare(strict_types=1);

use App\Data\BallBeamParameters;
use App\Services\Octave\Exceptions\OctaveBridgeUnavailableException;
use App\Support\Octave\TrajectoryParser;
use Tests\TestCase;

uses(TestCase::class);

/** Build a minimal BallBeamParameters DTO for parser tests. */
function bbParserParams(float $durationSeconds = 0.08, float $stepSize = 0.02): BallBeamParameters
{
    return new BallBeamParameters(
        ball_mass: 0.11,
        ball_radius: 0.015,
        inertia: 0.00000999,
        beam_length: 1.0,
        gravity: 9.81,
        reference_position: 0.25,
        initial_position: 0.0,
        initial_velocity: 0.0,
        initial_angle: 0.0,
        duration_seconds: $durationSeconds,
        step_size: $stepSize,
    );
}

/** Load the ball-beam golden fixture. */
function bbGoldenFixture(): string
{
    return (string) file_get_contents(
        base_path('tests/Fixtures/octave/ball-beam-default.txt'),
    );
}

/**
 * Build a synthetic ball-beam stdout with $rows samples.
 *
 * @param  int  $rows  Number of POSITION / ANGLE rows.
 * @param  int  $tCount  Number of T entries (defaults to $rows).
 * @param  bool  $nanInPosition  Insert NaN into the POSITION block.
 * @param  bool  $nanInAngle  Insert NaN into the ANGLE block.
 */
function bbSyntheticStdout(
    int $rows,
    int $tCount = -1,
    bool $nanInPosition = false,
    bool $nanInAngle = false,
): string {
    $tCount = $tCount < 0 ? $rows : $tCount;

    $tLines = [];
    for ($i = 0; $i < $tCount; $i++) {
        $tLines[] = sprintf('   %.15e', $i * 0.02);
    }

    $posLines = [];
    $angleLines = [];
    for ($i = 0; $i < $rows; $i++) {
        $posLines[] = ($nanInPosition && $i === 1)
            ? '   NaN'
            : sprintf('   %.15e', $i * 0.001);
        $angleLines[] = ($nanInAngle && $i === 1)
            ? '   NaN'
            : sprintf('   %.15e', $i * 0.0001);
    }

    $tBlock = implode("\n", $tLines);
    $posBlock = implode("\n", $posLines);
    $angleBlock = implode("\n", $angleLines);
    $lastIdx = max(0, $rows - 1);
    $finalState = sprintf(
        '   %.15e   %.15e   %.15e   %.15e',
        $lastIdx * 0.001,
        $lastIdx * 0.05,
        $lastIdx * 0.0001,
        $lastIdx * 0.01,
    );

    return <<<EOT
---T---
{$tBlock}
---END-T---
---POSITION---
{$posBlock}
---END-POSITION---
---ANGLE---
{$angleBlock}
---END-ANGLE---
---FINAL_STATE---
{$finalState}
---END-FINAL_STATE---
EOT;
}

describe('TrajectoryParser::parseBallBeam', function (): void {
    it('parses a complete trajectory from the golden fixture', function (): void {
        /** @var TrajectoryParser $parser */
        $parser = app(TrajectoryParser::class);
        $params = bbParserParams(durationSeconds: 0.08, stepSize: 0.02);

        $trajectory = $parser->parseBallBeam(bbGoldenFixture(), $params, 'req-bb-golden');

        // 5 samples: t = 0, 0.02, 0.04, 0.06, 0.08
        expect($trajectory->samples)->toHaveCount(5);

        // final_state must carry exactly 4 finite floats
        expect($trajectory->finalState)->toHaveCount(4);
        foreach ($trajectory->finalState as $v) {
            expect(is_finite($v))->toBeTrue();
        }

        // t values are strictly increasing
        $times = array_column($trajectory->samples, 't');
        for ($i = 1; $i < count($times); $i++) {
            expect($times[$i])->toBeGreaterThan($times[$i - 1]);
        }

        // Each sample must have 'position' and 'beam_angle' keys
        foreach ($trajectory->samples as $sample) {
            expect($sample)->toHaveKey('position');
            expect($sample)->toHaveKey('beam_angle');
        }
    });

    it('throws OctaveBridgeUnavailableException when a marker block is missing', function (): void {
        /** @var TrajectoryParser $parser */
        $parser = app(TrajectoryParser::class);

        // Drop the ANGLE block entirely — the parser must throw
        $truncated = <<<'EOT'
---T---
   0.00000000000000000e+00   2.00000000000000011e-02
---END-T---
---POSITION---
   0.00000000000000000e+00
   2.50000000000000014e-04
---END-POSITION---
---FINAL_STATE---
   2.50000000000000014e-04   1.25000000000000007e-02   5.00000000000000028e-05   2.50000000000000014e-03
---END-FINAL_STATE---
EOT;

        expect(fn () => $parser->parseBallBeam($truncated, bbParserParams(), 'req-bb-trunc'))
            ->toThrow(OctaveBridgeUnavailableException::class);
    });

    it('throws when POSITION row count is inconsistent with T vector length', function (): void {
        /** @var TrajectoryParser $parser */
        $parser = app(TrajectoryParser::class);

        // 3 T entries but only 2 POSITION rows
        $mismatched = bbSyntheticStdout(rows: 2, tCount: 3);

        expect(fn () => $parser->parseBallBeam($mismatched, bbParserParams(), 'req-bb-mismatch'))
            ->toThrow(OctaveBridgeUnavailableException::class);
    });

    it('handles scientific notation in floats', function (): void {
        /** @var TrajectoryParser $parser */
        $parser = app(TrajectoryParser::class);

        $sciNotation = <<<'EOT'
---T---
   1.5e-3   3.0e-3
---END-T---
---POSITION---
   1.5e-3
   3.0e-3
---END-POSITION---
---ANGLE---
   1.5e-5
   3.0e-5
---END-ANGLE---
---FINAL_STATE---
   3.0e-3   1.5e-1   3.0e-5   1.5e-3
---END-FINAL_STATE---
EOT;

        $trajectory = $parser->parseBallBeam($sciNotation, bbParserParams(), 'req-bb-sci');

        expect($trajectory->samples)->toHaveCount(2);
        expect(abs($trajectory->samples[0]['t'] - 0.0015))->toBeLessThan(1e-9);
        expect(abs($trajectory->samples[1]['t'] - 0.003))->toBeLessThan(1e-9);
    });

    it('throws when NaN appears in the POSITION block', function (): void {
        /** @var TrajectoryParser $parser */
        $parser = app(TrajectoryParser::class);

        $withNan = bbSyntheticStdout(rows: 3, nanInPosition: true);

        expect(fn () => $parser->parseBallBeam($withNan, bbParserParams(), 'req-bb-nan'))
            ->toThrow(OctaveBridgeUnavailableException::class);
    });
});
