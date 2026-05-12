import { type ReactElement } from 'react';

import { PauseIcon, PlayIcon, ResetIcon } from '@/Components/icons';
import { useT } from '@/hooks/useT';
import { cn } from '@/lib/cn';

export type PlayerState = 'idle' | 'playing' | 'paused' | 'finished';

type PlayerControlsProps = {
    state: PlayerState;
    hasTrajectory: boolean;
    frameIndex: number;
    frameCount: number;
    currentTimeSeconds: number;
    onPlay: () => void;
    onPause: () => void;
    onReset: () => void;
    onRestartWithNewR: () => void;
    onScrub: (index: number) => void;
};

/**
 * Pure-functional player controls. The component owns no state — all
 * transitions flow through the parent via callbacks.
 *
 * Layout (per wireframe):
 *   [reset]  [play/pause — primary]  ────scrub slider────  t = X.XXX s  frame N/total
 */
export default function PlayerControls({
    state,
    hasTrajectory,
    frameIndex,
    frameCount,
    currentTimeSeconds,
    onPlay,
    onPause,
    onReset,
    onRestartWithNewR,
    onScrub,
}: PlayerControlsProps): ReactElement {
    const t = useT();

    const isPlaying = state === 'playing';
    const canInteract = hasTrajectory;
    const total = Math.max(frameCount - 1, 1);

    function handleScrubChange(e: React.ChangeEvent<HTMLInputElement>): void {
        onScrub(Number(e.target.value));
    }

    return (
        <div className="flex flex-col gap-2 rounded-b-md border-x border-b border-border bg-surface-raised px-4 py-3">
            {/* Controls row */}
            <div className="flex items-center gap-3">
                {/* Reset button */}
                <button
                    type="button"
                    aria-label={t.pendulum.player.reset}
                    title={t.pendulum.player.reset}
                    disabled={!canInteract}
                    onClick={onReset}
                    className={cn(
                        'inline-flex h-[30px] w-[30px] items-center justify-center rounded border',
                        'border-border bg-surface-raised text-on-surface-muted',
                        'transition-[background,color,border-color]',
                        'hover:border-border-strong hover:text-on-surface',
                        'disabled:cursor-not-allowed disabled:opacity-40',
                    )}
                >
                    <ResetIcon size={13} />
                </button>

                {/* Play / Pause — primary-styled */}
                <button
                    type="button"
                    aria-label={isPlaying ? t.pendulum.player.pause : t.pendulum.player.play}
                    title={isPlaying ? t.pendulum.player.pause : t.pendulum.player.play}
                    disabled={!canInteract}
                    onClick={isPlaying ? onPause : onPlay}
                    className={cn(
                        'inline-flex h-9 w-9 items-center justify-center rounded border',
                        'border-primary text-on-primary transition-[filter]',
                        'hover:brightness-[1.08]',
                        'disabled:cursor-not-allowed disabled:opacity-40',
                    )}
                    style={{ background: 'var(--cta-gradient)' }}
                >
                    {isPlaying ? <PauseIcon size={14} /> : <PlayIcon size={14} />}
                </button>

                {/* Scrub slider — fills remaining space */}
                <input
                    type="range"
                    min={0}
                    max={total}
                    value={canInteract ? frameIndex : 0}
                    disabled={!canInteract}
                    onChange={handleScrubChange}
                    aria-label={t.pendulum.replayLabel}
                    className={cn(
                        'h-[3px] flex-1 cursor-pointer appearance-none rounded-full',
                        'bg-border accent-accent',
                        'disabled:cursor-not-allowed disabled:opacity-40',
                    )}
                />

                {/* Mono timestamp */}
                <span className="shrink-0 font-mono text-[12px] text-on-surface-muted">
                    {t.pendulum.time}&nbsp;=&nbsp;
                    {canInteract ? currentTimeSeconds.toFixed(2) : '0.00'}&nbsp;s
                </span>
            </div>

            {/* Frame counter + restart-with-new-r */}
            <div className="flex items-center justify-between">
                <span className="font-mono text-[11px] text-on-surface-faint">
                    {canInteract
                        ? `${t.pendulum.frame} ${String(frameIndex + 1)} / ${String(frameCount)}`
                        : `${t.pendulum.frame} — / —`}
                </span>
                <button
                    type="button"
                    disabled={!hasTrajectory}
                    onClick={onRestartWithNewR}
                    title={t.pendulum.player.restartWithNewRHint}
                    className="font-mono text-[11px] text-on-surface-faint transition-colors hover:text-on-surface disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {t.pendulum.player.restartWithNewR}
                </button>
            </div>
        </div>
    );
}
