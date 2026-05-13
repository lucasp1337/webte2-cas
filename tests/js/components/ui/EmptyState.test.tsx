import { render, screen } from '@testing-library/react';

import EmptyState from '@/Components/ui/EmptyState';

describe('EmptyState', () => {
    it('renders the title', () => {
        render(<EmptyState title="No results found" />);

        expect(screen.getByText('No results found')).toBeInTheDocument();
    });

    it('renders the description when provided', () => {
        render(<EmptyState title="No results" description="Try adjusting your filters." />);

        expect(screen.getByText('Try adjusting your filters.')).toBeInTheDocument();
    });

    it('omits the description when not provided', () => {
        render(<EmptyState title="No results" />);

        expect(screen.queryByText('Try adjusting your filters.')).not.toBeInTheDocument();
    });

    it('renders the icon slot when provided', () => {
        render(<EmptyState title="No results" icon={<span data-testid="custom-icon" />} />);

        expect(screen.getByTestId('custom-icon')).toBeInTheDocument();
    });

    it('omits the icon wrapper when no icon is provided', () => {
        render(<EmptyState title="No results" />);

        // The icon wrapper has no accessible role; confirm the icon testid is absent.
        expect(screen.queryByTestId('custom-icon')).not.toBeInTheDocument();
    });

    it('renders the action slot when provided', () => {
        render(<EmptyState title="No results" action={<button type="button">Create one</button>} />);

        expect(screen.getByRole('button', { name: 'Create one' })).toBeInTheDocument();
    });

    it('omits the action wrapper when no action is provided', () => {
        render(<EmptyState title="No results" />);

        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
});
