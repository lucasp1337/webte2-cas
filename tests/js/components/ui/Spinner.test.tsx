import { render, screen } from '@testing-library/react';

import Spinner from '@/Components/ui/Spinner';

describe('Spinner', () => {
    it('exposes a status role with the default loading label', () => {
        render(<Spinner />);

        const status = screen.getByRole('status');
        expect(status).toHaveAttribute('aria-busy', 'true');
        expect(status).toHaveTextContent('Loading');
    });

    it('uses a custom label when provided', () => {
        render(<Spinner label="Načítava sa" />);

        expect(screen.getByText('Načítava sa')).toBeInTheDocument();
    });
});
