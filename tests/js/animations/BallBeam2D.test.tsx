import { render } from '@testing-library/react';
import type { ReactElement } from 'react';

// react-konva renders into a real Canvas, which jsdom does not support.
// Replace every shape with a plain <div> carrying data attributes so we can
// assert on structural presence without touching canvas APIs.
vi.mock('react-konva', () => {
    type ShapeProps = {
        children?: React.ReactNode;
        x?: number;
        y?: number;
        width?: number;
        height?: number;
        text?: string;
        [key: string]: unknown;
    };

    const make = (konvaType: string): ((props: ShapeProps) => ReactElement) => {
        function KonvaMock({ children, x, y, width, height, text, ...rest }: ShapeProps): ReactElement {
            const safe: Record<string, unknown> = {
                'data-konva': konvaType,
                ...(x !== undefined ? { 'data-x': x } : {}),
                ...(y !== undefined ? { 'data-y': y } : {}),
                ...(width !== undefined ? { 'data-width': width } : {}),
                ...(height !== undefined ? { 'data-height': height } : {}),
                ...(text !== undefined ? { 'data-text': text } : {}),
            };
            void rest;

            return (
                <div {...safe} data-konva={konvaType}>
                    {text}
                    {children}
                </div>
            );
        }
        KonvaMock.displayName = `Konva${konvaType}Mock`;

        return KonvaMock;
    };

    return {
        Stage: make('Stage'),
        Layer: make('Layer'),
        Rect: make('Rect'),
        Circle: make('Circle'),
        Line: make('Line'),
        Text: make('Text'),
    };
});

// Inertia stub — BallBeam2D imports useT which calls usePage internally.
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'en' }, url: '/en/ball-beam' }),
    router: { visit: () => undefined },
}));

import type { BallBeamFrame } from '@/animations/types';
import BallBeam2D from '@/animations/BallBeam2D';

const WIDTH = 800;
const HEIGHT = 300;

function makeFrame(position: number, beam_angle: number): BallBeamFrame {
    return { t: 0, position, beam_angle };
}

describe('BallBeam2D', () => {
    it('renders the Stage with the given width and height', () => {
        const { container } = render(
            <BallBeam2D frames={[makeFrame(0, 0)]} cursorIndex={0} width={WIDTH} height={HEIGHT} />,
        );

        const stage = container.querySelector('[data-konva="Stage"]');
        expect(stage).toBeInTheDocument();
        expect(stage).toHaveAttribute('data-width', String(WIDTH));
        expect(stage).toHaveAttribute('data-height', String(HEIGHT));
    });

    it('renders the ball at pivot when position is 0 and beam_angle is 0', () => {
        const { container } = render(
            <BallBeam2D frames={[makeFrame(0, 0)]} cursorIndex={0} width={WIDTH} height={HEIGHT} />,
        );

        const circle = container.querySelector('[data-konva="Circle"]');
        expect(circle).toBeInTheDocument();

        // When position=0 and beam_angle=0: ballX = pivotX = WIDTH/2.
        const ballX = Number(circle?.getAttribute('data-x'));
        expect(ballX).toBeCloseTo(WIDTH / 2, 0);
    });

    it('shifts the ball along the rotated beam when position is positive', () => {
        // beam_angle=0 → cos(0)=1, sin(0)=0.
        // ballX = pivotX + position * pxPerM.
        const frames: BallBeamFrame[] = [makeFrame(0, 0), makeFrame(0.3, 0)];

        const { container: c0 } = render(<BallBeam2D frames={frames} cursorIndex={0} width={WIDTH} height={HEIGHT} />);
        const { container: c1 } = render(<BallBeam2D frames={frames} cursorIndex={1} width={WIDTH} height={HEIGHT} />);

        const x0 = Number(c0.querySelector('[data-konva="Circle"]')?.getAttribute('data-x'));
        const x1 = Number(c1.querySelector('[data-konva="Circle"]')?.getAttribute('data-x'));

        // Positive position moves the ball to the right.
        expect(x1).toBeGreaterThan(x0);
        // The shift must be proportional — assert some visible displacement.
        expect(x1 - x0).toBeGreaterThan(5);
    });

    it('positions the ball within canvas bounds for a long beam', () => {
        // lengthMeters=2.0 — the fit-bbox algorithm must shrink pxPerM so the ball
        // stays inside the canvas even at the beam extremity.
        const frames: BallBeamFrame[] = [makeFrame(-0.9, 0), makeFrame(0, 0), makeFrame(0.9, 0)];

        for (let i = 0; i < frames.length; i++) {
            const { container } = render(
                <BallBeam2D frames={frames} cursorIndex={i} width={WIDTH} height={HEIGHT} lengthMeters={2.0} />,
            );

            const circle = container.querySelector('[data-konva="Circle"]');
            expect(circle).toBeInTheDocument();

            const ballX = Number(circle?.getAttribute('data-x'));
            const ballY = Number(circle?.getAttribute('data-y'));

            expect(ballX).toBeGreaterThanOrEqual(0);
            expect(ballX).toBeLessThanOrEqual(WIDTH);
            expect(ballY).toBeGreaterThanOrEqual(0);
            expect(ballY).toBeLessThanOrEqual(HEIGHT);
        }
    });

    it('renders the empty stage when frames is empty', () => {
        const { container } = render(<BallBeam2D frames={[]} cursorIndex={0} width={WIDTH} height={HEIGHT} />);

        // EmptyStage renders a Stage with beam Line and pivot Line — but no Circle.
        expect(container.querySelector('[data-konva="Stage"]')).toBeInTheDocument();
        expect(container.querySelector('[data-konva="Circle"]')).not.toBeInTheDocument();
    });

    it('renders a scale indicator', () => {
        const { container } = render(
            <BallBeam2D frames={[makeFrame(0, 0)]} cursorIndex={0} width={WIDTH} height={HEIGHT} />,
        );

        // The badge is a Konva Text node whose text content starts with "1 m ≈".
        const textNodes = Array.from(container.querySelectorAll('[data-konva="Text"]'));
        const badge = textNodes.find((el) => el.textContent?.startsWith('1 m ≈'));
        expect(badge).toBeInTheDocument();
        expect(badge?.textContent).toMatch(/^1 m ≈ \d+ px$/);
    });

    it('renders a beam-tilt-exaggerated caption', () => {
        const { container } = render(
            <BallBeam2D frames={[makeFrame(0, 0)]} cursorIndex={0} width={WIDTH} height={HEIGHT} />,
        );

        const textNodes = Array.from(container.querySelectorAll('[data-konva="Text"]'));
        const caption = textNodes.find((el) => el.textContent?.includes('exaggerated'));
        expect(caption).toBeInTheDocument();
        expect(caption?.textContent).toContain('exaggerated');
    });

    it('applies the angle visual multiplier to the ball position', () => {
        // Real beam_angle = 0.001 rad → thetaVisual = 0.001 * 500 = 0.5 rad.
        // With position=0: ballX = pivotX, ballY = pivotY (angle only rotates beam).
        // With position=0.2: ballX = pivotX + 0.2 * pxPerM * cos(0.5).
        //                    ballY = pivotY + 0.2 * pxPerM * sin(0.5).
        // We assert y is non-zero (proving the sin component is active) when angle≠0.
        const ORIGIN_FRACTION = 0.55;
        const pivotY = HEIGHT * ORIGIN_FRACTION;

        const { container } = render(
            <BallBeam2D frames={[makeFrame(0.2, 0.001)]} cursorIndex={0} width={WIDTH} height={HEIGHT} />,
        );

        const circle = container.querySelector('[data-konva="Circle"]');
        expect(circle).toBeInTheDocument();

        // thetaVisual = 0.001 * 500 = 0.5 rad; sin(0.5) ≈ 0.479 — ball is below pivot.
        const ballY = Number(circle?.getAttribute('data-y'));

        // sin(0.5) ≈ 0.479 — ball must be below the pivot on a positive-angle beam
        // (Konva y-axis points down, positive sin pushes ball downward).
        expect(ballY).toBeGreaterThan(pivotY);
    });
});
