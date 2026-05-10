// TODO(phase-10): ship Pendulum3D variant under @/animations/Pendulum3D.tsx
// using Three.js — the Pendulum2D and Pendulum3D renderers are interchangeable
// via the AnimationRenderer<PendulumFrame> type; the page passes them as a prop.

import { type ReactElement } from 'react';
import { Circle, Layer, Line, Rect, Stage, Text } from 'react-konva';

import type { AnimationRendererProps, PendulumFrame } from '@/animations/types';
import { useFitScale } from '@/hooks/useFitScale';

/** Width and height of the cart rectangle in pixels. Fixed; never scaled. */
const CART_W = 60;
const CART_H = 30;

/** Radius of the pendulum bob in pixels. */
const BOB_RADIUS = 12;

/**
 * Horizontal margin in pixels beyond the cart's outermost canvas position.
 * Prevents the cart from touching the canvas edge when x is at its extreme.
 */
const HORIZ_MARGIN = 16;

/** Vertical margin above the bob, in pixels. */
const VERTICAL_PADDING = 16;

/**
 * Vertical position of the cart centre as a fraction of canvas height.
 * Putting the cart in the lower portion gives the upright rod room to render.
 */
const CART_VERTICAL_FRACTION = 0.85;

/** Fallback rod length in metres when the component receives no override. */
const DEFAULT_LENGTH_M = 0.5;

/** Colours expressed as CSS custom properties so they follow the theme. */
const COLOR_TRACK = 'var(--color-border)';
const COLOR_CART = 'var(--color-primary)';
const COLOR_ROD = 'var(--color-on-surface)';
const COLOR_BOB = 'var(--color-secondary)';
const COLOR_BADGE = 'var(--color-on-surface-muted)';

type Pendulum2DProps = AnimationRendererProps<PendulumFrame> & {
    /**
     * Physical length of the pendulum rod in metres.
     * Pulled out of the generic AnimationRendererProps so the generic
     * AnimationRenderer<TFrame> interface stays clean for phase-07 reuse.
     */
    lengthMeters?: number;
};

type EmptyStageProps = {
    width: number;
    height: number;
};

function EmptyStage({ width, height }: EmptyStageProps): ReactElement {
    const trackY = height * CART_VERTICAL_FRACTION;
    return (
        <div className="bg-surface-muted">
            <Stage width={width} height={height}>
                <Layer>
                    <Line points={[0, trackY, width, trackY]} stroke={COLOR_TRACK} strokeWidth={2} />
                </Layer>
            </Stage>
        </div>
    );
}

/**
 * 2D Konva renderer for the inverted pendulum animation.
 *
 * Conforms to `AnimationRenderer<PendulumFrame>` so the page can swap in
 * Pendulum3D (phase 10) without changing its own state or loop logic.
 *
 * This is a pure component — it reads `frames[cursorIndex]` and renders.
 * It never owns the animation loop.
 *
 * Scale algorithm ("fit-bbox", Design C):
 * Compute one pxPerM that fits BOTH the trajectory's horizontal extent AND
 * the rod's vertical extent into the canvas with margin. The more constrained
 * dimension wins. Memoised per trajectory so the scale is frozen during playback.
 */
export default function Pendulum2D({
    frames,
    cursorIndex,
    width,
    height,
    lengthMeters = DEFAULT_LENGTH_M,
}: Pendulum2DProps): ReactElement {
    const frame = frames[cursorIndex];

    // Compute a single pxPerM that fits both horizontal travel and rod length
    // into the canvas. Delegated to useFitScale so the same algorithm is reusable
    // for BallBeam2D — the extractor function selects the relevant frame field.
    const pxPerM = useFitScale({
        frames,
        horizontalExtractor: (f) => f.x,
        verticalReachMeters: lengthMeters,
        width,
        height,
        originFraction: CART_VERTICAL_FRACTION,
        horizontalMarginPx: HORIZ_MARGIN,
        verticalPaddingPx: VERTICAL_PADDING,
        cartHalfWidthPx: CART_W / 2,
        bobRadiusPx: BOB_RADIUS,
    });

    // Place the cart in the lower portion so the upright rod has room above.
    const originY = height * CART_VERTICAL_FRACTION;

    if (frame === undefined) {
        return <EmptyStage width={width} height={height} />;
    }

    const rodPx = lengthMeters * pxPerM;

    // Cart centre x in canvas pixels.
    const cartCx = width / 2 + frame.x * pxPerM;
    const cartX = cartCx - CART_W / 2;
    const cartY = originY - CART_H / 2;

    // Bob position — theta is measured from vertical (upright equilibrium).
    // sin(theta) gives horizontal offset, -cos(theta) gives vertical offset upward.
    const bobX = cartCx + Math.sin(frame.theta) * rodPx;
    const bobY = originY - Math.cos(frame.theta) * rodPx;

    return (
        <div className="bg-surface-muted">
            <Stage width={width} height={height}>
                <Layer>
                    {/* Ground track */}
                    <Line points={[0, originY, width, originY]} stroke={COLOR_TRACK} strokeWidth={2} />
                    {/* Cart body */}
                    <Rect x={cartX} y={cartY} width={CART_W} height={CART_H} fill={COLOR_CART} cornerRadius={4} />
                    {/* Pendulum rod */}
                    <Line points={[cartCx, originY, bobX, bobY]} stroke={COLOR_ROD} strokeWidth={3} />
                    {/* Pendulum bob */}
                    <Circle x={bobX} y={bobY} radius={BOB_RADIUS} fill={COLOR_BOB} />
                    {/* Scale badge — lower-left corner, muted, 11px */}
                    <Text
                        x={8}
                        y={height - 18}
                        text={`1 m ≈ ${Math.round(pxPerM).toString()} px`}
                        fontSize={11}
                        fill={COLOR_BADGE}
                    />
                </Layer>
            </Stage>
        </div>
    );
}
