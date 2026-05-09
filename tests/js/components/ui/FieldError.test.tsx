import { render, screen } from '@testing-library/react';

import FieldError from '@/Components/ui/FieldError';

describe('FieldError', () => {
    it('renders an alert with the error message', () => {
        render(<FieldError>Required</FieldError>);

        const alert = screen.getByRole('alert');
        expect(alert).toHaveTextContent('Required');
    });

    it('renders nothing when no children are provided', () => {
        const { container } = render(<FieldError>{null}</FieldError>);

        expect(container).toBeEmptyDOMElement();
    });
});
