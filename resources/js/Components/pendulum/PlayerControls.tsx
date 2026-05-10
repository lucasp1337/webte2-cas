import { type ReactElement } from 'react';

import Button from '@/Components/ui/Button';
import { useT } from '@/hooks/useT';

export type PlayerState = 'idle' | 'playing' | 'paused' | 'finished';

type PlayerControlsProps = {
    state: PlayerState;
    hasTrajectory: boolean;
    onPlay: () => void;
    onPause: () => void;
    onReset: () => void;
    onRestartWithNewR: () => void;
};

/**
 * Pure-functional player controls. The component owns no state — all
 * transitions flow through the parent via callbacks.
 *
 * State machine:
 *   idle     → [Run pressed on form]  → playing
 *   playing  → onPause                → paused
 *   paused   → onPlay                 → playing
 *   playing  → animation completes    → finished
 *   finished → onPlay                 → playing (replay from start, driven by
 *                                       parent resetting cursorIndex)
 *   *        → onReset                → idle
 *   *        → onRestartWithNewR      → playing (new trajectory from final state)
 */
export default function PlayerControls({
    state,
    hasTrajectory,
    onPlay,
    onPause,
    onReset,
    onRestartWithNewR,
}: PlayerControlsProps): ReactElement {
    const t = useT();

    const isPlaying = state === 'playing';
    const canInteract = hasTrajectory;

    return (
        <div className="flex flex-wrap items-center gap-2 rounded-b-md border-x border-b border-border bg-surface px-3 py-2">
            {isPlaying ? (
                <Button variant="secondary" size="sm" onClick={onPause} disabled={!canInteract}>
                    {t.pendulum.player.pause}
                </Button>
            ) : (
                <Button size="sm" onClick={onPlay} disabled={!canInteract}>
                    {state === 'finished' ? t.pendulum.player.replay : t.pendulum.player.play}
                </Button>
            )}

            <Button variant="ghost" size="sm" onClick={onReset} disabled={!canInteract}>
                {t.pendulum.player.reset}
            </Button>

            <Button
                variant="secondary"
                size="sm"
                onClick={onRestartWithNewR}
                disabled={!hasTrajectory}
                title={t.pendulum.player.restartWithNewRHint}
            >
                {t.pendulum.player.restartWithNewR}
            </Button>

            <span className="ml-auto text-xs text-on-surface-muted capitalize">{t.pendulum.player.states[state]}</span>
        </div>
    );
}
