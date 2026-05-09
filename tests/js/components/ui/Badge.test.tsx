import { render, screen } from '@testing-library/react';

import Badge from '@/Components/ui/Badge';

describe('Badge', () => {
    it('renders the children', () => {
        render(<Badge>online</Badge>);

        expect(screen.getByText('online')).toBeInTheDocument();
    });

    it('applies the success variant token', () => {
        render(<Badge variant="success">ok</Badge>);

        expect(screen.getByText('ok')).toHaveClass('bg-success');
    });

    it('applies the error variant token', () => {
        render(<Badge variant="error">fail</Badge>);

        expect(screen.getByText('fail')).toHaveClass('bg-error');
    });
});
