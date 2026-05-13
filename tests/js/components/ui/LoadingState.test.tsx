import { render, screen } from '@testing-library/react';

import LoadingState from '@/Components/ui/LoadingState';

describe('LoadingState', () => {
    describe('spinner variant (default)', () => {
        it('renders a spinner', () => {
            render(<LoadingState />);

            // Spinner exposes role="status" aria-busy="true"
            expect(screen.getByRole('status')).toBeInTheDocument();
        });

        it('renders the label when provided', () => {
            render(<LoadingState label="Fetching data…" />);

            expect(screen.getByText('Fetching data…')).toBeInTheDocument();
        });

        it('omits the label paragraph when not provided', () => {
            render(<LoadingState />);

            // The only text in the spinner variant (no label) is the sr-only
            // text inside the Spinner itself — there should be no visible label p.
            expect(screen.queryByText('Fetching data…')).not.toBeInTheDocument();
        });
    });

    describe('skeleton variant', () => {
        it('renders the default three skeleton bars', () => {
            render(<LoadingState variant="skeleton" />);

            // Skeleton exposes role="status" for each bar
            const bars = screen.getAllByRole('status');
            expect(bars).toHaveLength(3);
        });

        it('renders the requested number of skeleton bars', () => {
            render(<LoadingState variant="skeleton" rows={5} />);

            const bars = screen.getAllByRole('status');
            expect(bars).toHaveLength(5);
        });

        it('renders a single skeleton bar when rows=1', () => {
            render(<LoadingState variant="skeleton" rows={1} />);

            const bars = screen.getAllByRole('status');
            expect(bars).toHaveLength(1);
        });
    });
});
