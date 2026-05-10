import { type ReactElement, useState } from 'react';

import {
    runBallBeamSimulation,
    type BallBeamParameters,
    type BallBeamTrajectory,
    SimulationApiError,
} from '@/api/ballBeam';
import BallBeam2D from '@/animations/BallBeam2D';
import type { BallBeamFrame } from '@/animations/types';
import BallBeamChart from '@/Components/ballbeam/BallBeamChart';
import BallBeamParameterForm from '@/Components/ballbeam/BallBeamParameterForm';
import PlayerControls, { type PlayerState } from '@/Components/pendulum/PlayerControls';
import { useAnimationLoop } from '@/hooks/useAnimationLoop';
import { useElementWidth } from '@/hooks/useElementWidth';
import { useT } from '@/hooks/useT';
import AppLayout from '@/Layouts/AppLayout';

// ---------------------------------------------------------------------------
// Page props
// ---------------------------------------------------------------------------

export type BallBeamPageProps = {
    apiKey: string;
    /**
     * Server-supplied slowdown factor. The animation loop divides real-time
     * delta by this value: 1 s of simulation plays back as `slowdownFactor`
     * seconds of wall time.
     */
    slowdownFactor: number;
};

// ---------------------------------------------------------------------------
// Canvas dimensions
// ---------------------------------------------------------------------------

/** Fallback used until ResizeObserver has measured the actual container. */
const CANVAS_WIDTH_FALLBACK = 700;
const CANVAS_HEIGHT = 300;

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function toFrames(trajectory: BallBeamTrajectory): BallBeamFrame[] {
    return trajectory.samples.map((s) => ({
        t: s.t,
        position: s.position,
        beam_angle: s.beam_angle,
    }));
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function BallBeam({ apiKey, slowdownFactor }: BallBeamPageProps): ReactElement {
    const t = useT();

    const [trajectory, setTrajectory] = useState<BallBeamTrajectory | null>(null);
    const [frames, setFrames] = useState<BallBeamFrame[]>([]);
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);
    const [playerState, setPlayerState] = useState<PlayerState>('idle');
    /** Most recent validated parameters — needed for "Restart with new r". */
    const [lastParameters, setLastParameters] = useState<BallBeamParameters | null>(null);

    /** Tracks the canvas-container width so the Konva stage fills its column. */
    const { ref: canvasContainerRef, width: canvasWidth } = useElementWidth<HTMLDivElement>(CANVAS_WIDTH_FALLBACK);

    const frameCount = frames.length;

    const { cursorIndex, setCursorIndex } = useAnimationLoop({
        frameCount,
        stepSizeSeconds: trajectory?.step_size ?? 0.05,
        slowdownFactor,
        isPlaying: playerState === 'playing',
        onComplete: () => {
            setPlayerState('finished');
        },
    });

    // -----------------------------------------------------------------------
    // Simulation
    // -----------------------------------------------------------------------

    async function startRun(
        parameters: BallBeamParameters,
        continueFrom?: [number, number, number, number],
    ): Promise<void> {
        setLoading(true);
        setError(null);
        setLastParameters(parameters);

        try {
            const result = await runBallBeamSimulation({
                parameters,
                ...(continueFrom !== undefined ? { continue_from: continueFrom } : {}),
                apiKey,
            });
            setTrajectory(result);
            setFrames(toFrames(result));
            setCursorIndex(0);
            setPlayerState('playing');
        } catch (err) {
            if (err instanceof SimulationApiError) {
                setError(`${t.ballBeam.errors.simulationFailed} (HTTP ${err.httpStatus.toString()})`);
            } else {
                setError(t.ballBeam.errors.unexpected);
            }
            setPlayerState('idle');
        } finally {
            setLoading(false);
        }
    }

    /** Submit from the parameter form — fresh run. */
    function handleRun(parameters: BallBeamParameters): void {
        void startRun(parameters);
    }

    /**
     * "Restart with new r" — submitted by the form (which has validated the
     * current parameters). Continues from the previous trajectory's final state
     * with the updated `reference_position` from the form.
     */
    function handleRestartWithNewR(parameters: BallBeamParameters): void {
        if (trajectory === null) return;
        // final_state = [position, velocity, angle, angular_velocity]
        void startRun(parameters, trajectory.final_state);
    }

    // -----------------------------------------------------------------------
    // Player controls
    // -----------------------------------------------------------------------

    function handlePlay(): void {
        if (playerState === 'finished') {
            // Replay from the beginning.
            setCursorIndex(0);
        }
        setPlayerState('playing');
    }

    function handlePause(): void {
        setPlayerState('paused');
    }

    function handleReset(): void {
        setCursorIndex(0);
        setPlayerState('idle');
    }

    /** Triggered by PlayerControls "Restart with new r" button. */
    function handlePlayerRestartWithNewR(): void {
        if (trajectory === null || lastParameters === null) return;
        void startRun(lastParameters, trajectory.final_state);
    }

    // -----------------------------------------------------------------------
    // Render
    // -----------------------------------------------------------------------

    return (
        <AppLayout title={t.ballBeam.title}>
            <div className="flex flex-col gap-2">
                <h1 className="text-3xl font-bold text-on-surface">{t.ballBeam.title}</h1>
                <p className="text-on-surface-muted">{t.ballBeam.subtitle}</p>
            </div>

            {error !== null && (
                <div
                    role="alert"
                    className="mt-4 rounded-md border border-error bg-error/10 px-4 py-3 text-sm text-error"
                >
                    {error}
                </div>
            )}

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[320px_1fr]">
                {/* Left — parameter form */}
                <BallBeamParameterForm
                    onRun={handleRun}
                    onRestartWithNewR={trajectory !== null ? handleRestartWithNewR : undefined}
                    disabled={loading}
                />

                {/* Right — animation, controls, chart */}
                <div className="flex flex-col gap-0">
                    {/* Konva renderer — responsive to container width */}
                    <div ref={canvasContainerRef} className="overflow-hidden rounded-t-md border border-border">
                        <BallBeam2D
                            frames={frames}
                            cursorIndex={cursorIndex}
                            width={canvasWidth}
                            height={CANVAS_HEIGHT}
                            lengthMeters={lastParameters?.beam_length ?? 1.0}
                        />
                    </div>

                    {/* Player controls */}
                    <PlayerControls
                        state={playerState}
                        hasTrajectory={trajectory !== null}
                        onPlay={handlePlay}
                        onPause={handlePause}
                        onReset={handleReset}
                        onRestartWithNewR={handlePlayerRestartWithNewR}
                    />

                    {/* Chart */}
                    <div className="mt-4">
                        <BallBeamChart trajectory={trajectory} cursorIndex={cursorIndex} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
