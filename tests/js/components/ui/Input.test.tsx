import { render, screen } from '@testing-library/react';
import { createRef } from 'react';

import Input from '@/Components/ui/Input';

describe('Input', () => {
    it('forwards the ref to the underlying input element', () => {
        const ref = createRef<HTMLInputElement>();
        render(<Input ref={ref} aria-label="email" />);

        expect(ref.current).toBeInstanceOf(HTMLInputElement);
    });

    it('marks itself invalid when error is true', () => {
        render(<Input aria-label="email" error />);

        expect(screen.getByLabelText('email')).toHaveAttribute('aria-invalid', 'true');
    });

    it('defaults type to text', () => {
        render(<Input aria-label="name" />);

        expect(screen.getByLabelText('name')).toHaveAttribute('type', 'text');
    });
});
