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
        [key: string]: unknown;
    };

    const make = (konvaType: string): ((props: ShapeProps) => ReactElement) => {
        function KonvaMock({ children, x, y, width, height, ...rest }: ShapeProps): ReactElement {
            // Extract only DOM-safe attributes — discard Konva-specific props that
            // React would warn about when applied to a real DOM node.
            const safe: Record<string, unknown> = {
                'data-konva': konvaType,
                ...(x !== undefined ? { 'data-x': x } : {}),
                ...(y !== undefined ? { 'data-y': y } : {}),
                ...(width !== undefined ? { 'data-width': width } : {}),
                ...(height !== undefined ? { 'data-height': height } : {}),
            };
            void rest; // Konva props intentionally discarded

            return (
                <div {...safe} data-konva={konvaType}>
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
    };
});

import type { PendulumFrame } from '@/animations/types';
import Pendulum2D from '@/animations/Pendulum2D';

const WIDTH = 800;
const HEIGHT = 300;

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

    it('renders a cart Rect at the expected x position', () => {
        const { container } = render(
            <Pendulum2D frames={[makeFrame(0, 0)]} cursorIndex={0} width={WIDTH} height={HEIGHT} />,
        );

        // Cart is centred at width/2 + x * PX_PER_M, offset by -CART_W/2.
        // With x=0: cartX = 800/2 + 0 - 30 = 370
        const rect = container.querySelector('[data-konva="Rect"]');
        expect(rect).toBeInTheDocument();
        expect(Number(rect?.getAttribute('data-x'))).toBeCloseTo(370, 0);
    });

    it('shifts the cart right when x is positive', () => {
        const { container: c0 } = render(
            <Pendulum2D frames={[makeFrame(0, 0)]} cursorIndex={0} width={WIDTH} height={HEIGHT} />,
        );
        const { container: c1 } = render(
            <Pendulum2D frames={[makeFrame(0.5, 0)]} cursorIndex={0} width={WIDTH} height={HEIGHT} />,
        );

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

        // Frame 1 has x=0.3 m → cart should be 60 px further right than frame 0
        expect(x1).toBeGreaterThan(x0);
        expect(x1 - x0).toBeCloseTo(0.3 * 200, 0); // PX_PER_M = 200
    });
});
