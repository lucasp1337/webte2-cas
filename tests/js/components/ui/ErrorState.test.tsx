import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import ErrorState from '@/Components/ui/ErrorState';

describe('ErrorState', () => {
    it('renders the title', () => {
        render(<ErrorState title="Something went wrong" />);

        expect(screen.getByText('Something went wrong')).toBeInTheDocument();
    });

    it('renders the message when provided', () => {
        render(<ErrorState title="Error" message="The server returned a 503." />);

        expect(screen.getByText('The server returned a 503.')).toBeInTheDocument();
    });

    it('omits the message when not provided', () => {
        render(<ErrorState title="Error" />);

        // No secondary paragraph should exist.
        expect(screen.queryByText('The server returned a 503.')).not.toBeInTheDocument();
    });

    it('renders a retry button when onRetry is provided', () => {
        render(<ErrorState title="Error" onRetry={() => undefined} />);

        expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument();
    });

    it('calls onRetry when the retry button is clicked', async () => {
        const user = userEvent.setup();
        const onRetry = vi.fn();
        render(<ErrorState title="Error" onRetry={onRetry} />);

        await user.click(screen.getByRole('button', { name: 'Retry' }));

        expect(onRetry).toHaveBeenCalledOnce();
    });

    it('omits the retry button when onRetry is not provided', () => {
        render(<ErrorState title="Error" />);

        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('uses a custom retryLabel when provided', () => {
        render(<ErrorState title="Error" onRetry={() => undefined} retryLabel="Try again" />);

        expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument();
    });
});
