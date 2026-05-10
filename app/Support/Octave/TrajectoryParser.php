<?php

declare(strict_types=1);

namespace App\Support\Octave;

use App\Data\BallBeamParameters;
use App\Data\PendulumParameters;
use App\Data\SimulationTrajectory;
use App\Enums\AnimationName;
use App\Services\Octave\Exceptions\OctaveBridgeUnavailableException;

/**
 * Extracts structured trajectory data from raw Octave stdout.
 *
 * Each simulation Blade template emits marker-delimited blocks that this class
 * parses into a {@see SimulationTrajectory}.
 *
 * Pendulum markers (pendulum.blade.php):
 *   ---T---          time vector
 *   ---END-T---
 *   ---X---          full state matrix (x, x_dot, theta, theta_dot)
 *   ---END-X---
 *   ---THETA---      output matrix y (cart_pos, angle) — not used directly
 *   ---END-THETA---
 *   ---FINAL_STATE--- last row of state matrix
 *   ---END-FINAL_STATE---
 *
 * Ball-beam markers (ball-beam.blade.php):
 *   ---T---          time vector
 *   ---END-T---
 *   ---POSITION---   ball position output (y vector, 1-column)
 *   ---END-POSITION---
 *   ---ANGLE---      beam angle from state column 3 (x(:,3))
 *   ---END-ANGLE---
 *   ---FINAL_STATE--- last row of state matrix: position, velocity, angle, angular_velocity
 *   ---END-FINAL_STATE---
 *
 * If any expected block is absent or its content cannot be parsed as
 * whitespace-separated floats, {@see OctaveBridgeUnavailableException} is
 * thrown (the bridge produced unexpected output — treated as a bridge fault,
 * not a user error).
 */
final readonly class TrajectoryParser
{
    /**
     * Parse Octave stdout from a pendulum simulation run.
     *
     * @throws OctaveBridgeUnavailableException when markers are missing or
     *                                          the numeric content is malformed
     */
    public function parsePendulum(
        string $stdout,
        PendulumParameters $parameters,
        string $requestId,
    ): SimulationTrajectory {
        $tRaw = $this->extractBlock($stdout, 'T');
        $xRaw = $this->extractBlock($stdout, 'X');
        $finalStateRaw = $this->extractBlock($stdout, 'FINAL_STATE');

        $t = $this->parseVector($tRaw, 'T');
        $xMatrix = $this->parseMatrix($xRaw, 'X');
        $finalStateRow = $this->parseVector($finalStateRaw, 'FINAL_STATE');

        $sampleCount = count($t);

        if ($sampleCount === 0) {
            throw new OctaveBridgeUnavailableException(
                'Trajectory parser: time vector T is empty.',
            );
        }

        if (count($xMatrix) !== $sampleCount) {
            throw new OctaveBridgeUnavailableException(
                sprintf(
                    'Trajectory parser: X matrix has %d rows but T has %d entries.',
                    count($xMatrix),
                    $sampleCount,
                ),
            );
        }

        if (count($finalStateRow) !== 4) {
            throw new OctaveBridgeUnavailableException(
                sprintf(
                    'Trajectory parser: FINAL_STATE must have exactly 4 values, got %d.',
                    count($finalStateRow),
                ),
            );
        }

        /** @var array<int, array<string, float>> $samples */
        $samples = [];
        foreach ($t as $i => $time) {
            $stateRow = $xMatrix[$i] ?? [];
            $samples[] = [
                't' => $time,
                'x' => $stateRow[0] ?? 0.0,
                'theta' => $stateRow[2] ?? 0.0,
            ];
        }

        /** @var array{0: float, 1: float, 2: float, 3: float} $finalState */
        $finalState = [
            $finalStateRow[0],
            $finalStateRow[1],
            $finalStateRow[2],
            $finalStateRow[3],
        ];

        return new SimulationTrajectory(
            requestId: $requestId,
            animation: AnimationName::Pendulum,
            durationSeconds: $parameters->duration_seconds,
            stepSize: $parameters->step_size,
            samples: $samples,
            finalState: $finalState,
        );
    }

    /**
     * Parse Octave stdout from a ball-on-beam simulation run.
     *
     * Marker blocks: T, POSITION, ANGLE, FINAL_STATE.
     * State vector ordering: [position, velocity, angle, angular_velocity].
     * Sample shape: { t: float, position: float, beam_angle: float }.
     *
     * @throws OctaveBridgeUnavailableException when markers are missing or
     *                                          the numeric content is malformed
     */
    public function parseBallBeam(
        string $stdout,
        BallBeamParameters $parameters,
        string $requestId,
    ): SimulationTrajectory {
        $tRaw = $this->extractBlock($stdout, 'T');
        $positionRaw = $this->extractBlock($stdout, 'POSITION');
        $angleRaw = $this->extractBlock($stdout, 'ANGLE');
        $finalStateRaw = $this->extractBlock($stdout, 'FINAL_STATE');

        $t = $this->parseVector($tRaw, 'T');
        $position = $this->parseVector($positionRaw, 'POSITION');
        $angle = $this->parseVector($angleRaw, 'ANGLE');
        $finalStateRow = $this->parseVector($finalStateRaw, 'FINAL_STATE');

        $sampleCount = count($t);

        if ($sampleCount === 0) {
            throw new OctaveBridgeUnavailableException(
                'Trajectory parser (ball-beam): time vector T is empty.',
            );
        }

        if (count($position) !== $sampleCount) {
            throw new OctaveBridgeUnavailableException(
                sprintf(
                    'Trajectory parser (ball-beam): POSITION vector has %d entries but T has %d.',
                    count($position),
                    $sampleCount,
                ),
            );
        }

        if (count($angle) !== $sampleCount) {
            throw new OctaveBridgeUnavailableException(
                sprintf(
                    'Trajectory parser (ball-beam): ANGLE vector has %d entries but T has %d.',
                    count($angle),
                    $sampleCount,
                ),
            );
        }

        if (count($finalStateRow) !== 4) {
            throw new OctaveBridgeUnavailableException(
                sprintf(
                    'Trajectory parser (ball-beam): FINAL_STATE must have exactly 4 values, got %d.',
                    count($finalStateRow),
                ),
            );
        }

        /** @var array<int, array<string, float>> $samples */
        $samples = [];
        foreach ($t as $i => $time) {
            $samples[] = [
                't' => $time,
                'position' => $position[$i] ?? 0.0,
                'beam_angle' => $angle[$i] ?? 0.0,
            ];
        }

        /** @var array{0: float, 1: float, 2: float, 3: float} $finalState */
        $finalState = [
            $finalStateRow[0],
            $finalStateRow[1],
            $finalStateRow[2],
            $finalStateRow[3],
        ];

        return new SimulationTrajectory(
            requestId: $requestId,
            animation: AnimationName::BallBeam,
            durationSeconds: $parameters->duration_seconds,
            stepSize: $parameters->step_size,
            samples: $samples,
            finalState: $finalState,
        );
    }

    /**
     * Extract the raw text between `---MARKER---` and `---END-MARKER---`.
     *
     * @throws OctaveBridgeUnavailableException when the marker pair is absent
     */
    private function extractBlock(string $stdout, string $marker): string
    {
        $open = "---{$marker}---";
        $close = "---END-{$marker}---";

        $start = strpos($stdout, $open);
        $end = strpos($stdout, $close);

        if ($start === false || $end === false || $end <= $start) {
            throw new OctaveBridgeUnavailableException(
                "Trajectory parser: missing marker pair for '{$marker}' in Octave output.",
            );
        }

        return substr($stdout, $start + strlen($open), $end - $start - strlen($open));
    }

    /**
     * Parse a block of whitespace-separated floats into a flat array.
     *
     * Octave prints vectors as whitespace-delimited sequences (possibly across
     * multiple lines). We split on any run of whitespace and cast each token.
     *
     * @return list<float>
     *
     * @throws OctaveBridgeUnavailableException when any token cannot be cast
     */
    private function parseVector(string $raw, string $context): array
    {
        $tokens = preg_split('/\s+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);

        if ($tokens === false || $tokens === []) {
            throw new OctaveBridgeUnavailableException(
                "Trajectory parser: block '{$context}' contained no numeric tokens.",
            );
        }

        $values = [];
        foreach ($tokens as $token) {
            if (! is_numeric($token)) {
                throw new OctaveBridgeUnavailableException(
                    "Trajectory parser: non-numeric token '{$token}' in block '{$context}'.",
                );
            }
            $values[] = (float) $token;
        }

        return $values;
    }

    /**
     * Parse a block into a 2-D array of floats (one inner array per line).
     *
     * Octave prints matrices row-by-row, one row per line, columns separated
     * by spaces.
     *
     * @return list<list<float>>
     *
     * @throws OctaveBridgeUnavailableException on malformed content
     */
    private function parseMatrix(string $raw, string $context): array
    {
        $lines = preg_split('/\r?\n/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);

        if ($lines === false || $lines === []) {
            throw new OctaveBridgeUnavailableException(
                "Trajectory parser: block '{$context}' contained no lines.",
            );
        }

        $matrix = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $matrix[] = $this->parseVector($line, $context);
        }

        return $matrix;
    }
}
