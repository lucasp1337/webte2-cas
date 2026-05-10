import { type ReactElement, useState } from 'react';

import {
    runPendulumSimulation,
    type PendulumParameters,
    type PendulumTrajectory,
    SimulationApiError,
} from '@/api/pendulum';
import Pendulum2D from '@/animations/Pendulum2D';
import type { PendulumFrame } from '@/animations/types';
import PendulumChart from '@/Components/pendulum/PendulumChart';
import PendulumParameterForm from '@/Components/pendulum/PendulumParameterForm';
import PlayerControls, { type PlayerState } from '@/Components/pendulum/PlayerControls';
import { useAnimationLoop } from '@/hooks/useAnimationLoop';
import { useT } from '@/hooks/useT';
import AppLayout from '@/Layouts/AppLayout';

// ---------------------------------------------------------------------------
// Page props
// ---------------------------------------------------------------------------

export type PendulumPageProps = {
    apiKey: string;
    /**
     * Server-supplied slowdown factor. The animation loop divides real-time
     * delta by this value: 1 s of simulation plays back as `slowdownFactor`
     * seconds of wall time.
     *
     * NOTE for backend-dev: the PendulumController must pass `slowdownFactor`
     * as an Inertia prop (e.g. 5 is a good default for 10 s simulations).
     */
    slowdownFactor: number;
};

// ---------------------------------------------------------------------------
// Canvas dimensions
// ---------------------------------------------------------------------------

const CANVAS_WIDTH = 700;
const CANVAS_HEIGHT = 300;

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function toFrames(trajectory: PendulumTrajectory): PendulumFrame[] {
    return trajectory.samples.map((s) => ({
        t: s.t,
        x: s.x,
        theta: s.theta,
    }));
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function Pendulum({ apiKey, slowdownFactor }: PendulumPageProps): ReactElement {
    const t = useT();

    const [trajectory, setTrajectory] = useState<PendulumTrajectory | null>(null);
    const [frames, setFrames] = useState<PendulumFrame[]>([]);
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);
    const [playerState, setPlayerState] = useState<PlayerState>('idle');
    /** Most recent validated parameters — needed for "Restart with new r". */
    const [lastParameters, setLastParameters] = useState<PendulumParameters | null>(null);

    const frameCount = frames.length;

    const { cursorIndex, setCursorIndex } = useAnimationLoop({
        frameCount,
        stepSizeSeconds: trajectory?.step_size ?? 0.02,
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
        parameters: PendulumParameters,
        continueFrom?: [number, number, number, number],
    ): Promise<void> {
        setLoading(true);
        setError(null);
        setLastParameters(parameters);

        try {
            const result = await runPendulumSimulation({
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
                setError(`${t.pendulum.errors.simulationFailed} (HTTP ${err.httpStatus.toString()})`);
            } else {
                setError(t.pendulum.errors.unexpected);
            }
            setPlayerState('idle');
        } finally {
            setLoading(false);
        }
    }

    /** Submit from the parameter form — fresh run. */
    function handleRun(parameters: PendulumParameters): void {
        void startRun(parameters);
    }

    /**
     * "Restart with new r" — submitted by the form (which has validated
     * the current parameters). Continues from the previous trajectory's
     * final state with the updated `reference_position` from the form.
     */
    function handleRestartWithNewR(parameters: PendulumParameters): void {
        if (trajectory === null) return;
        // final_state = [x, x_dot, theta, theta_dot]
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
        <AppLayout title={t.pendulum.title}>
            <div className="flex flex-col gap-2">
                <h1 className="text-3xl font-bold text-on-surface">{t.pendulum.title}</h1>
                <p className="text-on-surface-muted">{t.pendulum.subtitle}</p>
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
                <PendulumParameterForm
                    onRun={handleRun}
                    onRestartWithNewR={trajectory !== null ? handleRestartWithNewR : undefined}
                    disabled={loading}
                />

                {/* Right — animation, controls, chart */}
                <div className="flex flex-col gap-0">
                    {/* Konva renderer */}
                    <div className="overflow-hidden rounded-t-md border border-border">
                        <Pendulum2D
                            frames={frames}
                            cursorIndex={cursorIndex}
                            width={CANVAS_WIDTH}
                            height={CANVAS_HEIGHT}
                            lengthMeters={lastParameters?.length ?? 0.5}
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
                        <PendulumChart trajectory={trajectory} cursorIndex={cursorIndex} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
