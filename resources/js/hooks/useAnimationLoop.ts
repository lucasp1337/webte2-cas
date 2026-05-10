import { useEffect, useRef, useState } from 'react';

type UseAnimationLoopOptions = {
    frameCount: number;
    stepSizeSeconds: number;
    slowdownFactor: number;
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
 */
export function useAnimationLoop({
    frameCount,
    stepSizeSeconds,
    slowdownFactor,
    isPlaying,
    onComplete,
    now = () => performance.now(),
}: UseAnimationLoopOptions): UseAnimationLoopResult {
    const [cursorIndex, setCursorIndex] = useState<number>(0);

    // Use refs for values that the RAF callback closes over so we never
    // restart the loop just because the callback identity changed.
    const onCompleteRef = useRef<(() => void) | undefined>(onComplete);
    onCompleteRef.current = onComplete;

    const completedRef = useRef<boolean>(false);

    useEffect(() => {
        if (!isPlaying || frameCount === 0) return;

        completedRef.current = false;
        let rafId = 0;
        let lastWall = now();
        let simElapsed = 0;

        const step = (wallNow: number): void => {
            const delta = (wallNow - lastWall) / 1000; // seconds of real time
            lastWall = wallNow;
            simElapsed += delta / slowdownFactor;

            const idx = Math.min(Math.floor(simElapsed / stepSizeSeconds), frameCount - 1);
            setCursorIndex(idx);

            if (idx < frameCount - 1) {
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
    }, [isPlaying, frameCount, stepSizeSeconds, slowdownFactor, now]);

    return { cursorIndex, setCursorIndex };
}
