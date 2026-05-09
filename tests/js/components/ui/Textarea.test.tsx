import { render, screen } from '@testing-library/react';
import { createRef } from 'react';

import Textarea from '@/Components/ui/Textarea';

describe('Textarea', () => {
    it('forwards the ref to the underlying textarea element', () => {
        const ref = createRef<HTMLTextAreaElement>();
        render(<Textarea ref={ref} aria-label="bio" />);

        expect(ref.current).toBeInstanceOf(HTMLTextAreaElement);
    });

    it('marks itself invalid when error is true', () => {
        render(<Textarea aria-label="bio" error />);

        expect(screen.getByLabelText('bio')).toHaveAttribute('aria-invalid', 'true');
    });

    it('defaults rows to 4 when not provided', () => {
        render(<Textarea aria-label="bio" />);

        expect(screen.getByLabelText('bio')).toHaveAttribute('rows', '4');
    });
});
