import { act, renderHook } from '@testing-library/react';

import { useAnimationLoop } from '@/hooks/useAnimationLoop';

/**
 * Helper that creates a manually-stepped fake clock.
 * `advance(ms)` advances the fake time and triggers the pending RAF callback.
 */
function makeFakeClock(): { now: () => number; advance: (ms: number) => void } {
    let t = 0;
    const callbacks: Array<(now: number) => void> = [];

    // Replace rAF/cAF with our manual stubs on the global object.
    vi.spyOn(globalThis, 'requestAnimationFrame').mockImplementation((cb) => {
        callbacks.push(cb);
        return callbacks.length;
    });

    vi.spyOn(globalThis, 'cancelAnimationFrame').mockImplementation(() => {
        // noop — cleanup cancels the pending callback implicitly via unmount
    });

    return {
        now: () => t,
        advance(ms: number) {
            t += ms;
            const pending = callbacks.splice(0);
            for (const cb of pending) {
                cb(t);
            }
        },
    };
}

describe('useAnimationLoop', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('advances cursorIndex based on real-time delta divided by slowdownFactor', () => {
        const clock = makeFakeClock();

        const { result } = renderHook(() =>
            useAnimationLoop({
                frameCount: 10,
                stepSizeSeconds: 0.1,
                slowdownFactor: 2,
                isPlaying: true,
                now: clock.now,
            }),
        );

        // Initial state
        expect(result.current.cursorIndex).toBe(0);

        // Advance 1000 ms real time → 500 ms sim time → floor(500ms / 100ms) = 5
        act(() => {
            clock.advance(1000);
        });

        expect(result.current.cursorIndex).toBe(5);
    });

    it('halves frame advancement when slowdownFactor doubles', () => {
        const clock1 = makeFakeClock();

        const { result: result1 } = renderHook(() =>
            useAnimationLoop({
                frameCount: 20,
                stepSizeSeconds: 0.1,
                slowdownFactor: 1,
                isPlaying: true,
                now: clock1.now,
            }),
        );

        act(() => {
            clock1.advance(500);
        });
        const indexAtSlowdown1 = result1.current.cursorIndex;

        vi.restoreAllMocks();
        const clock2 = makeFakeClock();

        const { result: result2 } = renderHook(() =>
            useAnimationLoop({
                frameCount: 20,
                stepSizeSeconds: 0.1,
                slowdownFactor: 2,
                isPlaying: true,
                now: clock2.now,
            }),
        );

        act(() => {
            clock2.advance(500);
        });
        const indexAtSlowdown2 = result2.current.cursorIndex;

        // At 500ms real time, slowdown=1 → 5 frames; slowdown=2 → 2 frames (~half)
        expect(indexAtSlowdown1).toBeGreaterThan(indexAtSlowdown2);
        expect(indexAtSlowdown2).toBeCloseTo(Math.floor(indexAtSlowdown1 / 2), 0);
    });

    it('stops at the last frame and calls onComplete exactly once even after extra ticks', () => {
        const clock = makeFakeClock();
        const onComplete = vi.fn();

        const { result } = renderHook(() =>
            useAnimationLoop({
                frameCount: 3,
                stepSizeSeconds: 0.1,
                slowdownFactor: 1,
                isPlaying: true,
                onComplete,
                now: clock.now,
            }),
        );

        // Advance past total duration (3 frames × 0.1 s = 0.3 s → 300 ms)
        act(() => {
            clock.advance(500);
        });

        expect(result.current.cursorIndex).toBe(2); // capped at last frame index
        expect(onComplete).toHaveBeenCalledOnce();

        // Extra ticks must not call onComplete again
        act(() => {
            clock.advance(500);
        });
        expect(onComplete).toHaveBeenCalledOnce();
    });

    it('does not advance the cursor when isPlaying is false', () => {
        const clock = makeFakeClock();

        const { result } = renderHook(() =>
            useAnimationLoop({
                frameCount: 10,
                stepSizeSeconds: 0.1,
                slowdownFactor: 1,
                isPlaying: false,
                now: clock.now,
            }),
        );

        act(() => {
            clock.advance(1000);
        });

        expect(result.current.cursorIndex).toBe(0);
    });

    it('does not advance when frameCount is 0', () => {
        const clock = makeFakeClock();

        const { result } = renderHook(() =>
            useAnimationLoop({
                frameCount: 0,
                stepSizeSeconds: 0.1,
                slowdownFactor: 1,
                isPlaying: true,
                now: clock.now,
            }),
        );

        act(() => {
            clock.advance(1000);
        });

        expect(result.current.cursorIndex).toBe(0);
    });

    it('resets from the cursor index set via setCursorIndex', () => {
        const clock = makeFakeClock();

        const { result } = renderHook(() =>
            useAnimationLoop({
                frameCount: 10,
                stepSizeSeconds: 0.1,
                slowdownFactor: 1,
                isPlaying: false,
                now: clock.now,
            }),
        );

        act(() => {
            result.current.setCursorIndex(5);
        });

        expect(result.current.cursorIndex).toBe(5);

        act(() => {
            result.current.setCursorIndex(0);
        });

        expect(result.current.cursorIndex).toBe(0);
    });
});
