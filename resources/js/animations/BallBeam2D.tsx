import { type ReactElement } from 'react';
import { Circle, Layer, Line, Stage, Text } from 'react-konva';

import type { AnimationRendererProps, BallBeamFrame } from '@/animations/types';
import { useFitScale } from '@/hooks/useFitScale';
import { useT } from '@/hooks/useT';

/**
 * Real beam angles from the gulicka.m PD controller are tiny (~1e-4 rad) and
 * invisible without amplification. This constant scales only the visual rotation;
 * the chart always shows the real angle.
 */
const ANGLE_VISUAL_MULTIPLIER = 500;

/** Beam rendered as a thick line. */
const BEAM_THICKNESS_PX = 6;

/** Radius of the ball circle in pixels. */
const BALL_RADIUS_PX = 14;

/** Radius of the pivot triangle base / circle in pixels. */
const PIVOT_RADIUS_PX = 8;

/**
 * Vertical fraction at which the pivot (beam centre) sits.
 * 0.55 gives the beam space above and below.
 */
const ORIGIN_FRACTION = 0.55;

const DEFAULT_LENGTH_M = 1.0;

/** Horizontal margin in pixels to prevent the beam from touching canvas edges. */
const HORIZ_MARGIN = 20;

/** Vertical padding in pixels above/below the ball tip. */
const VERTICAL_PADDING = 20;

const COLOR_BEAM = 'var(--color-on-surface)';
const COLOR_BALL = 'var(--color-primary)';
const COLOR_PIVOT = 'var(--color-secondary)';
const COLOR_BADGE = 'var(--color-on-surface-muted)';
const COLOR_CAPTION = 'var(--color-on-surface-muted)';

type BallBeam2DProps = AnimationRendererProps<BallBeamFrame> & {
    /**
     * Physical beam length in metres.
     * Pulled out of the generic AnimationRendererProps so the generic
     * AnimationRenderer<TFrame> type stays clean.
     */
    lengthMeters?: number;
};

type EmptyStageProps = {
    width: number;
    height: number;
    pivotX: number;
    pivotY: number;
    beamHalfPx: number;
};

function EmptyStage({ width, height, pivotX, pivotY, beamHalfPx }: EmptyStageProps): ReactElement {
    return (
        <div className="bg-surface-muted">
            <Stage width={width} height={height}>
                <Layer>
                    {/* Un-tilted beam through pivot */}
                    <Line
                        points={[pivotX - beamHalfPx, pivotY, pivotX + beamHalfPx, pivotY]}
                        stroke={COLOR_BEAM}
                        strokeWidth={BEAM_THICKNESS_PX}
                        lineCap="round"
                    />
                    {/* Pivot triangle */}
                    <Line
                        points={[
                            pivotX,
                            pivotY,
                            pivotX - PIVOT_RADIUS_PX,
                            pivotY + PIVOT_RADIUS_PX * 2,
                            pivotX + PIVOT_RADIUS_PX,
                            pivotY + PIVOT_RADIUS_PX * 2,
                        ]}
                        closed
                        fill={COLOR_PIVOT}
                    />
                </Layer>
            </Stage>
        </div>
    );
}

/**
 * 2D Konva renderer for the ball-on-beam animation.
 *
 * Conforms to `AnimationRenderer<BallBeamFrame>` so the page can swap in
 * a 3D variant without changing its own state or loop logic.
 *
 * This is a pure component — it reads `frames[cursorIndex]` and renders.
 * It never owns the animation loop.
 *
 * Visual tilt is amplified by ANGLE_VISUAL_MULTIPLIER for visibility.
 * The chart sibling shows the real beam_angle.
 */
export default function BallBeam2D({
    frames,
    cursorIndex,
    width,
    height,
    lengthMeters = DEFAULT_LENGTH_M,
}: BallBeam2DProps): ReactElement {
    const t = useT();

    // Fit-bbox scale: half the beam extends in each direction from the pivot,
    // so verticalReachMeters = beam_length / 2 is the tightest bound that
    // keeps the beam endpoints in frame.
    const pxPerM = useFitScale({
        frames,
        horizontalExtractor: (f) => f.position,
        verticalReachMeters: lengthMeters / 2,
        width,
        height,
        originFraction: ORIGIN_FRACTION,
        horizontalMarginPx: HORIZ_MARGIN,
        verticalPaddingPx: VERTICAL_PADDING,
        // No fixed-size body anchored to the horizontal axis — the pivot is a
        // point, so 0 px half-width avoids artificially shrinking the canvas.
        horizontalBodyHalfPx: 0,
        verticalBodyRadiusPx: BALL_RADIUS_PX,
    });

    const pivotX = width / 2;
    const pivotY = height * ORIGIN_FRACTION;
    const beamHalfPx = (lengthMeters / 2) * pxPerM;

    const frame = frames[cursorIndex];

    if (frame === undefined) {
        return <EmptyStage width={width} height={height} pivotX={pivotX} pivotY={pivotY} beamHalfPx={beamHalfPx} />;
    }

    // Visual angle — amplified purely for rendering; chart uses real angle.
    const thetaVisual = frame.beam_angle * ANGLE_VISUAL_MULTIPLIER;
    const cosT = Math.cos(thetaVisual);
    const sinT = Math.sin(thetaVisual);

    // Beam endpoints: rotate (±beamHalfPx, 0) around pivot.
    const x1 = pivotX - beamHalfPx * cosT;
    const y1 = pivotY - beamHalfPx * sinT;
    const x2 = pivotX + beamHalfPx * cosT;
    const y2 = pivotY + beamHalfPx * sinT;

    // Ball position along the beam from the pivot (positive = toward x2 end).
    const ballOffsetPx = frame.position * pxPerM;
    const ballX = pivotX + ballOffsetPx * cosT;
    const ballY = pivotY + ballOffsetPx * sinT;

    return (
        <div className="bg-surface-muted">
            <Stage width={width} height={height}>
                <Layer>
                    {/* Beam */}
                    <Line
                        points={[x1, y1, x2, y2]}
                        stroke={COLOR_BEAM}
                        strokeWidth={BEAM_THICKNESS_PX}
                        lineCap="round"
                    />

                    {/* Pivot support triangle */}
                    <Line
                        points={[
                            pivotX,
                            pivotY,
                            pivotX - PIVOT_RADIUS_PX,
                            pivotY + PIVOT_RADIUS_PX * 2,
                            pivotX + PIVOT_RADIUS_PX,
                            pivotY + PIVOT_RADIUS_PX * 2,
                        ]}
                        closed
                        fill={COLOR_PIVOT}
                    />

                    {/* Ball */}
                    <Circle x={ballX} y={ballY} radius={BALL_RADIUS_PX} fill={COLOR_BALL} />

                    {/* Scale badge — lower-left corner, muted, 11 px */}
                    <Text
                        x={8}
                        y={height - 34}
                        text={`1 m ≈ ${Math.round(pxPerM).toString()} px`}
                        fontSize={11}
                        fill={COLOR_BADGE}
                    />

                    {/* Exaggeration caption — lower-right corner */}
                    <Text
                        x={0}
                        y={height - 18}
                        width={width - 8}
                        align="right"
                        text={t.ballBeam.tiltExaggerated}
                        fontSize={11}
                        fill={COLOR_CAPTION}
                    />
                </Layer>
            </Stage>
        </div>
    );
}
