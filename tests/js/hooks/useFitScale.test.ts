import { renderHook } from '@testing-library/react';

import type { PendulumFrame } from '@/animations/types';
import { useFitScale } from '@/hooks/useFitScale';

/** Default canvas dimensions that give a comfortable scale for most tests. */
const WIDTH = 800;
const HEIGHT = 400;

function makeFrame(x: number): PendulumFrame {
    return { t: 0, x, theta: 0 };
}

describe('useFitScale', () => {
    it('returns a finite, positive scale for a non-empty trajectory', () => {
        const frames: PendulumFrame[] = [makeFrame(-0.5), makeFrame(0), makeFrame(0.5)];

        const { result } = renderHook(() =>
            useFitScale({
                frames,
                horizontalExtractor: (f) => f.x,
                horizontalBodyHalfPx: 30,
                verticalBodyRadiusPx: 12,
                verticalReachMeters: 0.5,
                width: WIDTH,
                height: HEIGHT,
            }),
        );

        expect(result.current).toBeGreaterThan(0);
        expect(Number.isFinite(result.current)).toBe(true);
    });

    it('floors at 1 px/m for degenerate inputs (zero-size canvas)', () => {
        const frames: PendulumFrame[] = [makeFrame(0.5)];

        const { result } = renderHook(() =>
            useFitScale({
                frames,
                horizontalExtractor: (f) => f.x,
                horizontalBodyHalfPx: 30,
                verticalBodyRadiusPx: 12,
                verticalReachMeters: 0.5,
                width: 0,
                height: 0,
            }),
        );

        // Both pxPerMByWidth and pxPerMByHeight will be <= 0; the floor kicks in.
        expect(result.current).toBe(1);
    });

    it('handles an empty frames array gracefully without dividing by zero', () => {
        const { result } = renderHook(() =>
            useFitScale({
                frames: [] as PendulumFrame[],
                horizontalExtractor: (f) => f.x,
                horizontalBodyHalfPx: 30,
                verticalBodyRadiusPx: 12,
                verticalReachMeters: 0.5,
                width: WIDTH,
                height: HEIGHT,
            }),
        );

        expect(result.current).toBeGreaterThan(0);
        expect(Number.isFinite(result.current)).toBe(true);
    });

    it('handles a 100k-frame trajectory without arg-stack overflow', () => {
        const frames = Array.from({ length: 100_000 }, (_, i) => ({
            t: i / 100,
            x: Math.sin(i / 1_000),
            theta: 0,
        }));

        let error: unknown = null;
        let scale: number | undefined;

        try {
            const { result } = renderHook(() =>
                useFitScale({
                    frames,
                    horizontalExtractor: (f) => f.x,
                    horizontalBodyHalfPx: 30,
                    verticalBodyRadiusPx: 12,
                    verticalReachMeters: 0.5,
                    width: WIDTH,
                    height: HEIGHT,
                }),
            );
            scale = result.current;
        } catch (e) {
            error = e;
        }

        expect(error).toBeNull();
        expect(typeof scale).toBe('number');
    });

    it('produces the same scale when called twice with the same input array reference', () => {
        const frames: PendulumFrame[] = [makeFrame(-1), makeFrame(0), makeFrame(1)];

        const { result: r1 } = renderHook(() =>
            useFitScale({
                frames,
                horizontalExtractor: (f) => f.x,
                horizontalBodyHalfPx: 30,
                verticalBodyRadiusPx: 12,
                verticalReachMeters: 0.5,
                width: WIDTH,
                height: HEIGHT,
            }),
        );

        const { result: r2 } = renderHook(() =>
            useFitScale({
                frames,
                horizontalExtractor: (f) => f.x,
                horizontalBodyHalfPx: 30,
                verticalBodyRadiusPx: 12,
                verticalReachMeters: 0.5,
                width: WIDTH,
                height: HEIGHT,
            }),
        );

        expect(r1.current).toBe(r2.current);
    });

    it('is width-constrained when horizontal travel is large', () => {
        // Very large maxX relative to the canvas width — horizontal axis should win.
        const maxX = 50; // 50 m of travel on an 800 px canvas
        const frames: PendulumFrame[] = [makeFrame(-maxX), makeFrame(maxX)];

        // Defaults: originFraction=0.85, horizontalMarginPx=16, paddingMeters=0.05,
        // horizontalBodyHalfPx=30, verticalBodyRadiusPx=12, verticalPaddingPx=16
        const paddingMeters = 0.05;
        const horizontalReach = maxX + paddingMeters;
        const expectedByWidth = (WIDTH / 2 - 30 - 16) / horizontalReach;

        const { result } = renderHook(() =>
            useFitScale({
                frames,
                horizontalExtractor: (f) => f.x,
                horizontalBodyHalfPx: 30,
                verticalBodyRadiusPx: 12,
                verticalReachMeters: 0.5,
                width: WIDTH,
                height: HEIGHT,
            }),
        );

        // Width-constrained: result should be close to pxPerMByWidth (and > 1 so no floor).
        expect(result.current).toBeCloseTo(expectedByWidth, 1);
    });

    it('is height-constrained when vertical reach is large', () => {
        // Very large rod — vertical axis should win.
        const verticalReachMeters = 100;
        const frames: PendulumFrame[] = [makeFrame(0)];

        // Defaults: originFraction=0.85, verticalPaddingPx=16, verticalBodyRadiusPx=12
        const paddingMeters = 0.05;
        const originY = HEIGHT * 0.85;
        const verticalReach = verticalReachMeters + paddingMeters;
        const expectedByHeight = (originY - 12 - 16) / verticalReach;

        const { result } = renderHook(() =>
            useFitScale({
                frames,
                horizontalExtractor: (f) => f.x,
                horizontalBodyHalfPx: 30,
                verticalBodyRadiusPx: 12,
                verticalReachMeters,
                width: WIDTH,
                height: HEIGHT,
            }),
        );

        // Height-constrained: result should be close to pxPerMByHeight (and > 1 so no floor).
        expect(result.current).toBeCloseTo(expectedByHeight, 1);
    });
});
