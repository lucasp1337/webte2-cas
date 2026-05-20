import { useEffect, useRef, useState } from 'react';

type UseAnimationLoopOptions = {
    frameCount: number;
    stepSizeSeconds: number;
    slowdownFactor: number;
    /**
     * User-chosen playback multiplier (0.5 = half speed, 2 = double speed).
     * Read live via a ref so changing it mid-playback is seamless. Defaults
     * to 1 when omitted.
     */
    speedFactor?: number;
    isPlaying: boolean;
    onComplete?: () => void;
    /** Injectable clock for tests — defaults to `performance.now`. */
    now?: () => number;
};

type UseAnimationLoopResult = {
    cursorIndex: number;
    setCursorIndex: (i: number) => void;
};

/**
 * Drives an animation frame cursor forward in real time, honouring a
 * server-supplied slowdown factor so the simulation plays back at the
 * intended visual speed rather than at simulation rate.
 *
 * The hook owns the RAF loop but never owns trajectory data — it is a pure
 * timing primitive. The renderer and chart are siblings that both read
 * `cursorIndex` from the parent.
 *
 * `now` is injectable for tests (Vitest fake timers). Internally it is
 * stored in a ref so changing the function reference between renders does not
 * restart the RAF loop.
 */
export function useAnimationLoop({
    frameCount,
    stepSizeSeconds,
    slowdownFactor,
    speedFactor = 1,
    isPlaying,
    onComplete,
    now,
}: UseAnimationLoopOptions): UseAnimationLoopResult {
    const [cursorIndex, setCursorIndex] = useState<number>(0);

    // All values the RAF step closure reads are stored in refs so the effect
    // only restarts when control parameters change (isPlaying, frameCount, etc.)
    // rather than on every render.
    const onCompleteRef = useRef<(() => void) | undefined>(onComplete);
    onCompleteRef.current = onComplete;

    const nowRef = useRef<() => number>(now ?? (() => performance.now()));
    nowRef.current = now ?? (() => performance.now());

    const stepSizeRef = useRef<number>(stepSizeSeconds);
    stepSizeRef.current = stepSizeSeconds;

    const slowdownRef = useRef<number>(slowdownFactor);
    slowdownRef.current = slowdownFactor;

    const speedRef = useRef<number>(speedFactor);
    speedRef.current = speedFactor;

    const frameCountRef = useRef<number>(frameCount);
    frameCountRef.current = frameCount;

    const completedRef = useRef<boolean>(false);

    useEffect(() => {
        if (!isPlaying || frameCount === 0) return;

        completedRef.current = false;
        let rafId = 0;
        let lastWall = nowRef.current();
        let simElapsed = 0;

        const step = (wallNow: number): void => {
            const delta = (wallNow - lastWall) / 1000; // seconds of real time
            lastWall = wallNow;
            // speedFactor scales how much simulation time elapses per real
            // second; slowdownFactor is the server-supplied baseline.
            simElapsed += (delta * speedRef.current) / slowdownRef.current;

            const idx = Math.min(Math.floor(simElapsed / stepSizeRef.current), frameCountRef.current - 1);
            setCursorIndex(idx);

            if (idx < frameCountRef.current - 1) {
                rafId = requestAnimationFrame(step);
            } else if (!completedRef.current) {
                completedRef.current = true;
                onCompleteRef.current?.();
            }
        };

        rafId = requestAnimationFrame(step);

        return () => {
            cancelAnimationFrame(rafId);
        };
        // isPlaying and frameCount are the only values that should restart the
        // loop. The rest are read via refs so they stay current without
        // triggering a new RAF cycle.
    }, [isPlaying, frameCount]);

    return { cursorIndex, setCursorIndex };
}
