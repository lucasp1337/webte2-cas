import { type ReactElement, useState } from 'react';

import {
    runBallBeamSimulation,
    type BallBeamParameters,
    type BallBeamTrajectory,
    SimulationApiError,
} from '@/api/ballBeam';
import BallBeam2D from '@/animations/BallBeam2D';
import type { BallBeamFrame } from '@/animations/types';
import Badge from '@/Components/ui/Badge';
import EmptyState from '@/Components/ui/EmptyState';
import ErrorState from '@/Components/ui/ErrorState';
import LoadingState from '@/Components/ui/LoadingState';
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
    /** Playback-speed multiplier chosen via the player controls. */
    const [speed, setSpeed] = useState<number>(1);
    /** Most recent validated parameters — needed for "Restart with new r". */
    const [lastParameters, setLastParameters] = useState<BallBeamParameters | null>(null);

    /** Tracks the canvas-container width so the Konva stage fills its column. */
    const { ref: canvasContainerRef, width: canvasWidth } = useElementWidth<HTMLDivElement>(CANVAS_WIDTH_FALLBACK);

    const frameCount = frames.length;
    // Canvas height derived from 16:9 aspect ratio of the measured container width
    const canvasHeight = Math.round(canvasWidth * (9 / 16));

    const { cursorIndex, setCursorIndex } = useAnimationLoop({
        frameCount,
        stepSizeSeconds: trajectory?.step_size ?? 0.05,
        slowdownFactor,
        speedFactor: speed,
        isPlaying: playerState === 'playing',
        onComplete: () => {
            setPlayerState('finished');
        },
    });

    // Current time in seconds for the scrub bar label
    const currentFrame = frames[cursorIndex];
    const currentTimeSeconds = currentFrame?.t ?? 0;

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

    function handleScrub(index: number): void {
        setCursorIndex(index);
        if (playerState === 'playing') {
            setPlayerState('paused');
        }
    }

    // -----------------------------------------------------------------------
    // Status badge variant
    // -----------------------------------------------------------------------

    type BadgeVariant = 'neutral' | 'accent' | 'success' | 'error';

    function statusBadgeVariant(): BadgeVariant {
        if (loading) return 'accent';
        if (error !== null) return 'error';
        if (trajectory !== null) return 'success';
        return 'neutral';
    }

    function statusBadgeLabel(): string {
        if (loading) return t.ballBeam.runningLabel;
        if (error !== null) return t.ballBeam.errorStatus;
        if (trajectory !== null) return t.ballBeam.okStatus;
        return t.ballBeam.idleStatus;
    }

    // -----------------------------------------------------------------------
    // Render
    // -----------------------------------------------------------------------

    return (
        <AppLayout title={t.ballBeam.title}>
            {/* Page header */}
            <div className="mb-7 flex flex-col gap-2">
                <h1 className="text-[28px] font-semibold leading-[1.1] tracking-[-0.025em] text-on-surface">
                    {t.ballBeam.title}
                </h1>
                <p className="max-w-[60ch] text-[15px] tracking-[-0.005em] text-on-surface-muted">
                    {t.ballBeam.subtitle}
                </p>
            </div>

            {/* Two-column body */}
            <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-[360px_1fr]">
                {/* Left — parameter form */}
                <BallBeamParameterForm
                    onRun={handleRun}
                    onRestartWithNewR={trajectory !== null ? handleRestartWithNewR : undefined}
                    disabled={loading}
                />

                {/* Right — animation + controls + chart */}
                <div className="flex min-w-0 flex-col gap-4">
                    {/* Animation card */}
                    <div className="rounded-md border border-border bg-surface-raised">
                        {/* Card header */}
                        <div className="flex items-center justify-between border-b border-border px-4 py-2">
                            <span className="font-mono text-[11px] uppercase tracking-[0.08em] text-on-surface-muted">
                                {t.ballBeam.simulationTitle}
                            </span>
                            <Badge variant={statusBadgeVariant()} dot square>
                                {statusBadgeLabel()}
                            </Badge>
                        </div>

                        {/* Canvas area — 16:9 */}
                        <div ref={canvasContainerRef} className="w-full">
                            {loading ? (
                                <LoadingState
                                    variant="spinner"
                                    label={t.ballBeam.runningLabel}
                                    className="aspect-video rounded-none border-0"
                                />
                            ) : error !== null ? (
                                <ErrorState
                                    title={t.ballBeam.errorTitle}
                                    message={error}
                                    onRetry={() => {
                                        setError(null);
                                    }}
                                    retryLabel={t.common.back}
                                    className="aspect-video rounded-none border-0"
                                />
                            ) : frames.length === 0 ? (
                                <EmptyState
                                    title={t.ballBeam.emptyTitle}
                                    description={t.ballBeam.emptySub}
                                    className="aspect-video rounded-none border-0"
                                />
                            ) : (
                                <BallBeam2D
                                    frames={frames}
                                    cursorIndex={cursorIndex}
                                    width={canvasWidth}
                                    height={canvasHeight}
                                    lengthMeters={lastParameters?.beam_length ?? 1.0}
                                />
                            )}
                        </div>
                    </div>

                    {/* Replay controls — attached to bottom of animation card */}
                    <PlayerControls
                        state={playerState}
                        hasTrajectory={trajectory !== null}
                        frameIndex={cursorIndex}
                        frameCount={frameCount}
                        currentTimeSeconds={currentTimeSeconds}
                        speed={speed}
                        onPlay={handlePlay}
                        onPause={handlePause}
                        onReset={handleReset}
                        onRestartWithNewR={handlePlayerRestartWithNewR}
                        onScrub={handleScrub}
                        onSpeedChange={setSpeed}
                    />

                    {/* Chart card */}
                    <div className="rounded-md border border-border bg-surface-raised">
                        {/* Card header */}
                        <div className="border-b border-border px-4 py-2">
                            <span className="font-mono text-[11px] uppercase tracking-[0.08em] text-on-surface-muted">
                                {t.ballBeam.chartTitle}
                            </span>
                        </div>
                        {/* Chart body */}
                        <div className="p-3">
                            {loading ? (
                                <LoadingState variant="skeleton" rows={3} className="border-0 bg-transparent p-0" />
                            ) : (
                                <BallBeamChart trajectory={trajectory} cursorIndex={cursorIndex} />
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
