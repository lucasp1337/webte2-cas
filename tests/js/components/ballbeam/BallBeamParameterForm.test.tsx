import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { AnchorHTMLAttributes, ReactNode } from 'react';

// Inertia stub — needed because BallBeamParameterForm imports useT which
// calls usePage internally.
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'en' }, url: '/en/ball-beam' }),
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

import BallBeamParameterForm, { ballBeamDefaultValues } from '@/Components/ballbeam/BallBeamParameterForm';
import type { BallBeamParameters } from '@/api/ballBeam';

type OnRunFn = (parameters: BallBeamParameters) => void;

describe('BallBeamParameterForm', () => {
    it('submits the form with valid default values', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();

        render(<BallBeamParameterForm onRun={onRun} />);

        await user.click(screen.getByRole('button', { name: /run simulation/i }));

        await waitFor(() => {
            expect(onRun).toHaveBeenCalledOnce();
        });

        const submitted = onRun.mock.calls[0]?.[0];
        expect(submitted).toBeDefined();
        expect(submitted?.ball_mass).toBe(ballBeamDefaultValues.ball_mass);
        expect(submitted?.duration_seconds).toBe(ballBeamDefaultValues.duration_seconds);
    });

    it('blocks submit when ball_mass is non-positive', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();

        render(<BallBeamParameterForm onRun={onRun} />);

        const input = screen.getByLabelText(/ball mass/i);
        await user.clear(input);
        await user.type(input, '0');

        await user.click(screen.getByRole('button', { name: /run simulation/i }));

        await waitFor(() => {
            expect(onRun).not.toHaveBeenCalled();
        });

        await waitFor(() => {
            const paras = Array.from(document.querySelectorAll('p'));
            const hasError = paras.some((p) => p.textContent && p.textContent.length > 0);
            expect(hasError).toBe(true);
        });
    });

    it('blocks submit when ball_mass exceeds 100', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();

        render(<BallBeamParameterForm onRun={onRun} />);

        const input = screen.getByLabelText(/ball mass/i);
        await user.clear(input);
        await user.type(input, '200');

        await user.click(screen.getByRole('button', { name: /run simulation/i }));

        await waitFor(() => {
            expect(onRun).not.toHaveBeenCalled();
        });
    });

    it('blocks submit when ball_radius exceeds 1', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();

        render(<BallBeamParameterForm onRun={onRun} />);

        const input = screen.getByLabelText(/ball radius/i);
        await user.clear(input);
        await user.type(input, '2');

        await user.click(screen.getByRole('button', { name: /run simulation/i }));

        await waitFor(() => {
            expect(onRun).not.toHaveBeenCalled();
        });
    });

    it('blocks submit when inertia exceeds 1', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();

        render(<BallBeamParameterForm onRun={onRun} />);

        const input = screen.getByLabelText(/moment of inertia/i);
        await user.clear(input);
        await user.type(input, '2');

        await user.click(screen.getByRole('button', { name: /run simulation/i }));

        await waitFor(() => {
            expect(onRun).not.toHaveBeenCalled();
        });
    });

    it('blocks submit when beam_length exceeds 10', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();

        render(<BallBeamParameterForm onRun={onRun} />);

        const input = screen.getByLabelText(/beam length/i);
        await user.clear(input);
        await user.type(input, '11');

        await user.click(screen.getByRole('button', { name: /run simulation/i }));

        await waitFor(() => {
            expect(onRun).not.toHaveBeenCalled();
        });
    });

    it('blocks submit when gravity exceeds 30', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();

        render(<BallBeamParameterForm onRun={onRun} />);

        const input = screen.getByLabelText(/gravity/i);
        await user.clear(input);
        await user.type(input, '31');

        await user.click(screen.getByRole('button', { name: /run simulation/i }));

        await waitFor(() => {
            expect(onRun).not.toHaveBeenCalled();
        });
    });

    it('blocks submit when duration_seconds exceeds 30', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();

        render(<BallBeamParameterForm onRun={onRun} />);

        const input = screen.getByLabelText(/simulation time/i);
        await user.clear(input);
        await user.type(input, '31');

        await user.click(screen.getByRole('button', { name: /run simulation/i }));

        await waitFor(() => {
            expect(onRun).not.toHaveBeenCalled();
        });
    });

    it('blocks submit when step_size is below 0.001', async () => {
        const user = userEvent.setup();
        const onRun = vi.fn<OnRunFn>();

        render(<BallBeamParameterForm onRun={onRun} />);

        const input = screen.getByLabelText(/step size/i);
        await user.clear(input);
        await user.type(input, '0.0005');

        await user.click(screen.getByRole('button', { name: /run simulation/i }));

        await waitFor(() => {
            expect(onRun).not.toHaveBeenCalled();
        });
    });

    it('shows the restart-with-new-r button only when the prop is provided', () => {
        const { rerender } = render(<BallBeamParameterForm onRun={vi.fn<OnRunFn>()} />);

        expect(screen.queryByRole('button', { name: /restart with new r/i })).not.toBeInTheDocument();

        rerender(<BallBeamParameterForm onRun={vi.fn<OnRunFn>()} onRestartWithNewR={vi.fn<OnRunFn>()} />);

        expect(screen.getByRole('button', { name: /restart with new r/i })).toBeInTheDocument();
    });
});
