import { useMemo } from 'react';

type UseFitScaleOptions<TFrame> = {
    frames: readonly TFrame[];
    /** Function that returns the horizontal world-position of a frame, in metres. */
    horizontalExtractor: (frame: TFrame) => number;
    /**
     * Vertical reach in metres — for the pendulum this is the rod length;
     * for the ball-beam this is the beam half-length × |max angle|, but
     * a sensible upper bound (the full beam length) is fine.
     */
    verticalReachMeters: number;
    /** Canvas width in pixels. */
    width: number;
    /** Canvas height in pixels. */
    height: number;
    /** Vertical fraction at which the cart/origin sits (default 0.85). */
    originFraction?: number;
    /** Horizontal pixel margin at canvas edges (default 16). */
    horizontalMarginPx?: number;
    /** Vertical pixel padding above the bob/ball (default 16). */
    verticalPaddingPx?: number;
    /** Padding metres added to the trajectory bounding box (default 0.05). */
    paddingMeters?: number;
    /** Horizontal pixel size of the cart half-width (default 30, half of 60). */
    cartHalfWidthPx?: number;
    /** Vertical pixel size of the bob radius (default 12). */
    bobRadiusPx?: number;
};

/**
 * Computes a single px-per-metre scale factor that fits both the trajectory's
 * horizontal extent and the simulation's vertical reach into the canvas with
 * configurable margins.
 *
 * The more constrained axis wins. Floored at 1 px/m to avoid degenerate renders.
 *
 * Generic over `TFrame` via a `horizontalExtractor` so the same algorithm works
 * for `PendulumFrame` (extract `f.x`) and `BallBeamFrame` (extract `f.position`).
 *
 * Memoised on `[frames, verticalReachMeters, width, height, ...all override numbers]`
 * so the scale is frozen during playback — advancing `cursorIndex` does not
 * retrigger the computation.
 */
export function useFitScale<TFrame>({
    frames,
    horizontalExtractor,
    verticalReachMeters,
    width,
    height,
    originFraction = 0.85,
    horizontalMarginPx = 16,
    verticalPaddingPx = 16,
    paddingMeters = 0.05,
    cartHalfWidthPx = 30,
    bobRadiusPx = 12,
}: UseFitScaleOptions<TFrame>): number {
    return useMemo(() => {
        // Maximum absolute horizontal position across the whole trajectory.
        // reduce avoids the spread-arg-stack pressure that Math.max(...array) causes
        // on very large trajectories (>10k frames).
        const maxX = frames.reduce((acc, f) => Math.max(acc, Math.abs(horizontalExtractor(f))), 0);

        // Add a small floor to avoid division blow-up on zero-motion trajectories.
        const horizontalReach = Math.max(maxX + paddingMeters, paddingMeters);
        const verticalReach = Math.max(verticalReachMeters + paddingMeters, paddingMeters);

        // Origin Y in pixels — where the cart/origin sits vertically.
        const originY = height * originFraction;

        // How many px/m can the horizontal axis accommodate?
        // Half-canvas minus cart half-width minus the margin must cover horizontalReach.
        const pxPerMByWidth = (width / 2 - cartHalfWidthPx - horizontalMarginPx) / horizontalReach;

        // How many px/m can the vertical axis accommodate?
        // Distance from originY up to where the bob tip sits (bobRadiusPx + verticalPaddingPx).
        const pxPerMByHeight = (originY - bobRadiusPx - verticalPaddingPx) / verticalReach;

        // The more constrained axis wins; floor at 1 px/m to avoid degenerate renders.
        return Math.max(1, Math.min(pxPerMByWidth, pxPerMByHeight));
    }, [
        frames,
        horizontalExtractor,
        verticalReachMeters,
        width,
        height,
        originFraction,
        horizontalMarginPx,
        verticalPaddingPx,
        paddingMeters,
        cartHalfWidthPx,
        bobRadiusPx,
    ]);
}
