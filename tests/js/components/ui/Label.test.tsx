import { render, screen } from '@testing-library/react';

import Label from '@/Components/ui/Label';

describe('Label', () => {
    it('renders the children inside a label element', () => {
        render(<Label htmlFor="email">Email</Label>);

        const label = screen.getByText('Email');
        expect(label.tagName).toBe('LABEL');
        expect(label).toHaveAttribute('for', 'email');
    });

    it('renders a required marker when required is true', () => {
        render(<Label required>Name</Label>);

        const marker = screen.getByText('*');
        expect(marker).toHaveAttribute('aria-hidden', 'true');
    });

    it('omits the required marker by default', () => {
        render(<Label>Name</Label>);

        expect(screen.queryByText('*')).not.toBeInTheDocument();
    });
});
