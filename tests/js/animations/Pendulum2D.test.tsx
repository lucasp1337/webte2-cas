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
            // Extract only DOM-safe attributes — discard Konva-specific props that
            // React would warn about when applied to a real DOM node.
            const safe: Record<string, unknown> = {
                'data-konva': konvaType,
                ...(x !== undefined ? { 'data-x': x } : {}),
                ...(y !== undefined ? { 'data-y': y } : {}),
                ...(width !== undefined ? { 'data-width': width } : {}),
                ...(height !== undefined ? { 'data-height': height } : {}),
                ...(text !== undefined ? { 'data-text': text } : {}),
            };
            void rest; // Konva props intentionally discarded

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

import type { PendulumFrame } from '@/animations/types';
import Pendulum2D from '@/animations/Pendulum2D';

const WIDTH = 800;
const HEIGHT = 300;

/** Fixed cart width, must match CART_W constant in Pendulum2D. */
const CART_W = 60;

function makeFrame(x: number, theta: number): PendulumFrame {
    return { t: 0, x, theta };
}

describe('Pendulum2D', () => {
    it('renders the Stage with the given width and height', () => {
        const { container } = render(
            <Pendulum2D frames={[makeFrame(0, 0.15)]} cursorIndex={0} width={WIDTH} height={HEIGHT} />,
        );

        const stage = container.querySelector('[data-konva="Stage"]');
        expect(stage).toBeInTheDocument();
        expect(stage).toHaveAttribute('data-width', String(WIDTH));
        expect(stage).toHaveAttribute('data-height', String(HEIGHT));
    });

    it('renders the cart centred horizontally when frame.x is 0', () => {
        const { container } = render(
            <Pendulum2D frames={[makeFrame(0, 0)]} cursorIndex={0} width={WIDTH} height={HEIGHT} />,
        );

        // With x=0 the cart centre is at WIDTH/2, so cartX = WIDTH/2 - CART_W/2.
        // This is scale-independent — pxPerM * 0 = 0 regardless of scaling.
        const rect = container.querySelector('[data-konva="Rect"]');
        expect(rect).toBeInTheDocument();
        expect(Number(rect?.getAttribute('data-x'))).toBeCloseTo(WIDTH / 2 - CART_W / 2, 0);
    });

    it('shifts the cart right when frame.x is positive', () => {
        const frames: PendulumFrame[] = [makeFrame(0, 0), makeFrame(0.5, 0)];

        const { container: c0 } = render(<Pendulum2D frames={frames} cursorIndex={0} width={WIDTH} height={HEIGHT} />);
        const { container: c1 } = render(<Pendulum2D frames={frames} cursorIndex={1} width={WIDTH} height={HEIGHT} />);

        const x0 = Number(c0.querySelector('[data-konva="Rect"]')?.getAttribute('data-x'));
        const x1 = Number(c1.querySelector('[data-konva="Rect"]')?.getAttribute('data-x'));

        expect(x1).toBeGreaterThan(x0);
    });

    it('renders the empty stage when frames is empty', () => {
        const { container } = render(<Pendulum2D frames={[]} cursorIndex={0} width={WIDTH} height={HEIGHT} />);

        // EmptyStage also renders a Stage; Rect and Circle should NOT be present.
        expect(container.querySelector('[data-konva="Stage"]')).toBeInTheDocument();
        expect(container.querySelector('[data-konva="Rect"]')).not.toBeInTheDocument();
        expect(container.querySelector('[data-konva="Circle"]')).not.toBeInTheDocument();
    });

    it('renders the empty stage when cursorIndex is out of range', () => {
        const { container } = render(
            <Pendulum2D frames={[makeFrame(0, 0)]} cursorIndex={5} width={WIDTH} height={HEIGHT} />,
        );

        expect(container.querySelector('[data-konva="Rect"]')).not.toBeInTheDocument();
    });

    it('reflects a change in cursorIndex by updating cart position', () => {
        const frames: PendulumFrame[] = [makeFrame(0, 0), makeFrame(0.3, 0.1)];

        const { container, rerender } = render(
            <Pendulum2D frames={frames} cursorIndex={0} width={WIDTH} height={HEIGHT} />,
        );
        const x0 = Number(container.querySelector('[data-konva="Rect"]')?.getAttribute('data-x'));

        rerender(<Pendulum2D frames={frames} cursorIndex={1} width={WIDTH} height={HEIGHT} />);
        const x1 = Number(container.querySelector('[data-konva="Rect"]')?.getAttribute('data-x'));

        // Frame 1 has x=0.3 m → cart should be further right.
        // Assert directional only — exact pixel offset is scale-dependent.
        expect(x1).toBeGreaterThan(x0);
        // Sanity floor: some minimum visible motion (> 20 px at any sane scale).
        expect(x1 - x0).toBeGreaterThan(20);
    });

    it('positions the bob within canvas bounds for a long rod', () => {
        // lengthMeters=2.0 — the fit-bbox algorithm must shrink pxPerM so the
        // bob stays inside the canvas even when the rod is fully upright (theta=0).
        const { container } = render(
            <Pendulum2D frames={[makeFrame(0, 0)]} cursorIndex={0} width={WIDTH} height={HEIGHT} lengthMeters={2.0} />,
        );

        const circle = container.querySelector('[data-konva="Circle"]');
        expect(circle).toBeInTheDocument();
        const bobY = Number(circle?.getAttribute('data-y'));
        expect(bobY).toBeGreaterThanOrEqual(0);
        expect(bobY).toBeLessThanOrEqual(HEIGHT);
    });

    it('positions the bob within canvas bounds for a short rod with large cart travel', () => {
        // lengthMeters=0.1, frames spanning x ∈ [-2, 2] — the horizontal extent
        // drives pxPerM down enough that neither extreme cart position overflows.
        const frames: PendulumFrame[] = [makeFrame(-2, 0), makeFrame(0, 0), makeFrame(2, 0)];

        const { container: c0 } = render(
            <Pendulum2D frames={frames} cursorIndex={0} width={WIDTH} height={HEIGHT} lengthMeters={0.1} />,
        );
        const { container: c2 } = render(
            <Pendulum2D frames={frames} cursorIndex={2} width={WIDTH} height={HEIGHT} lengthMeters={0.1} />,
        );

        for (const container of [c0, c2]) {
            const circle = container.querySelector('[data-konva="Circle"]');
            expect(circle).toBeInTheDocument();
            const bobX = Number(circle?.getAttribute('data-x'));
            expect(bobX).toBeGreaterThanOrEqual(0);
            expect(bobX).toBeLessThanOrEqual(WIDTH);
        }
    });

    it('places the bob directly above the cart for theta=0 (upright)', () => {
        // theta=0 → bob is directly above the cart centre.
        // bobX must equal cartCx = WIDTH/2 + 0 * pxPerM = WIDTH/2.
        const { container } = render(
            <Pendulum2D frames={[makeFrame(0, 0)]} cursorIndex={0} width={WIDTH} height={HEIGHT} />,
        );

        const circle = container.querySelector('[data-konva="Circle"]');
        const rect = container.querySelector('[data-konva="Rect"]');
        expect(circle).toBeInTheDocument();
        expect(rect).toBeInTheDocument();

        const bobX = Number(circle?.getAttribute('data-x'));
        const cartX = Number(rect?.getAttribute('data-x'));
        const cartCx = cartX + CART_W / 2;

        // bobX should equal cartCx when theta=0 (sin(0)=0)
        expect(bobX).toBeCloseTo(cartCx, 1);
    });

    it('places the bob at cartCx + rodPx horizontally when theta=π/2', () => {
        // theta=π/2 → sin(π/2)=1, so bobX = cartCx + rodPx.
        // Use a single-frame trajectory with x=0 so cartCx = WIDTH/2 exactly.
        // With a deterministic single frame, pxPerM is driven by the vertical axis:
        //   pxPerMByHeight = (originY - BOB_RADIUS - VERTICAL_PADDING) / (length + 0.05)
        // We verify that bobX > cartCx (rod is fully to the right).
        const lengthMeters = 0.5;
        const { container } = render(
            <Pendulum2D
                frames={[makeFrame(0, Math.PI / 2)]}
                cursorIndex={0}
                width={WIDTH}
                height={HEIGHT}
                lengthMeters={lengthMeters}
            />,
        );

        const circle = container.querySelector('[data-konva="Circle"]');
        const rect = container.querySelector('[data-konva="Rect"]');
        expect(circle).toBeInTheDocument();
        expect(rect).toBeInTheDocument();

        const bobX = Number(circle?.getAttribute('data-x'));
        const cartX = Number(rect?.getAttribute('data-x'));
        const cartCx = cartX + CART_W / 2;

        // bob must be to the right of the cart centre
        expect(bobX).toBeGreaterThan(cartCx);
        // and the offset should equal rodPx (= lengthMeters * pxPerM), which is positive
        expect(bobX - cartCx).toBeGreaterThan(0);
    });

    it('renders a scale indicator in the canvas', () => {
        const { container } = render(
            <Pendulum2D frames={[makeFrame(0, 0)]} cursorIndex={0} width={WIDTH} height={HEIGHT} />,
        );

        // The badge is a Konva Text node whose text content starts with "1 m ≈".
        const textEl = container.querySelector('[data-konva="Text"]');
        expect(textEl).toBeInTheDocument();
        expect(textEl?.textContent).toMatch(/^1 m ≈ \d+ px$/);
    });
});
