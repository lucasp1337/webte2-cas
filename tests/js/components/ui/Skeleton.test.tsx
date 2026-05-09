import { render, screen } from '@testing-library/react';

import Skeleton from '@/Components/ui/Skeleton';

describe('Skeleton', () => {
    it('exposes a status role marked busy', () => {
        render(<Skeleton className="h-4 w-24" />);

        const status = screen.getByRole('status');
        expect(status).toHaveAttribute('aria-busy', 'true');
        expect(status).toHaveClass('animate-pulse');
    });
});
