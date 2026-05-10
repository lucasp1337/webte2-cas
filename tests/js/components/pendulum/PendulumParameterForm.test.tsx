import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { AnchorHTMLAttributes, ReactNode } from 'react';

// Inertia stub — needed because PendulumParameterForm imports useT which
// calls usePage internally.
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'en' }, url: '/en/pendulum' }),
    router: { visit: () => undefined },
    Head: ({ title }: { title?: string }) => (title === undefined ? null : <title>{title}</title>),
    Link: ({
        href,
        children,
        ...rest
    }: { href: string; children: ReactNode } & AnchorHTMLAttributes<HTMLAnchorElement>) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

import PendulumParameterForm, { pendulumDefaultValues } from '@/Components/pendulum/PendulumParameterForm';
import type { PendulumParameters } from '@/api/pendulum';

type OnRunFn = (parameters: PendulumParameters) => void;

// Shared default values parity check — matches ValidPendulumParameters PHP rule.
const BACKEND_BOUNDS: Record<string, { min?: number; max?: number }> = {
    duration_seconds: { min: 0.001, max: 30 },
    step_size: { min: 0.001, max: 0.5 },
    cart_mass: { min: 0.001 },
    pendulum_mass: { min: 0.001 },
    friction: { min: 0 },
    inertia: { min: 0.001 },
    gravity: { min: 0.001 },
    length: { min: 0.001 },
};

describe('PendulumParameterForm', () => {
    it('submits the form with valid default values', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();

        render(<PendulumParameterForm onRun={onRun} />);

        await user.click(screen.getByRole('button', { name: /run simulation/i }));

        await waitFor(() => {
            expect(onRun).toHaveBeenCalledOnce();
        });

        const submitted = onRun.mock.calls[0]?.[0];
        expect(submitted).toBeDefined();
        expect(submitted?.cart_mass).toBe(pendulumDefaultValues.cart_mass);
        expect(submitted?.duration_seconds).toBe(pendulumDefaultValues.duration_seconds);
    });

    it('blocks submit when cart_mass is zero and shows an error', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();

        render(<PendulumParameterForm onRun={onRun} />);

        const cartMassInput = screen.getByLabelText(/cart mass/i);
        await user.clear(cartMassInput);
        await user.type(cartMassInput, '0');

        await user.click(screen.getByRole('button', { name: /run simulation/i }));

        // onRun must not have been called
        await waitFor(() => {
            expect(onRun).not.toHaveBeenCalled();
        });

        // A field error should appear in the DOM — look for FieldError components
        // which render paragraph elements with validation text.
        await waitFor(() => {
            const paras = Array.from(document.querySelectorAll('p'));
            const hasError = paras.some((p) => p.textContent && p.textContent.length > 0);
            expect(hasError).toBe(true);
        });
    });

    it('blocks submit when duration_seconds exceeds 30', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();

        render(<PendulumParameterForm onRun={onRun} />);

        const durationInput = screen.getByLabelText(/simulation time/i);
        await user.clear(durationInput);
        await user.type(durationInput, '31');

        await user.click(screen.getByRole('button', { name: /run simulation/i }));

        await waitFor(() => {
            expect(onRun).not.toHaveBeenCalled();
        });
    });

    it('blocks submit when step_size is below 0.001', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();

        render(<PendulumParameterForm onRun={onRun} />);

        const stepInput = screen.getByLabelText(/step size/i);
        await user.clear(stepInput);
        await user.type(stepInput, '0.0005');

        await user.click(screen.getByRole('button', { name: /run simulation/i }));

        await waitFor(() => {
            expect(onRun).not.toHaveBeenCalled();
        });
    });

    it('shows the "Restart with new r" button only when onRestartWithNewR is provided', () => {
        const { rerender } = render(<PendulumParameterForm onRun={vi.fn<OnRunFn>()} />);

        expect(screen.queryByRole('button', { name: /restart with new r/i })).not.toBeInTheDocument();

        rerender(<PendulumParameterForm onRun={vi.fn<OnRunFn>()} onRestartWithNewR={vi.fn<OnRunFn>()} />);

        expect(screen.getByRole('button', { name: /restart with new r/i })).toBeInTheDocument();
    });

    it('calls onRestartWithNewR with valid form data when that button is clicked', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();
        const onRestartWithNewR = vi.fn<OnRunFn>();

        render(<PendulumParameterForm onRun={onRun} onRestartWithNewR={onRestartWithNewR} />);

        await user.click(screen.getByRole('button', { name: /restart with new r/i }));

        await waitFor(() => {
            expect(onRestartWithNewR).toHaveBeenCalledOnce();
        });
        expect(onRun).not.toHaveBeenCalled();
    });

    it('default values satisfy the backend bounds (parity check)', () => {
        for (const [field, bounds] of Object.entries(BACKEND_BOUNDS)) {
            const value = pendulumDefaultValues[field as keyof typeof pendulumDefaultValues];
            if (typeof value !== 'number') continue;

            const { min, max } = bounds;
            if (min !== undefined) {
                expect(value).toBeGreaterThanOrEqual(min);
            }
            if (max !== undefined) {
                expect(value).toBeLessThanOrEqual(max);
            }
        }
    });
});
