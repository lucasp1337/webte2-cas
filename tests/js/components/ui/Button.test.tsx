import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import Button from '@/Components/ui/Button';

describe('Button', () => {
    it('renders the children as the accessible label', () => {
        render(<Button>Save</Button>);

        expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument();
    });

    it('defaults to type=button so it does not submit forms accidentally', () => {
        render(<Button>Save</Button>);

        expect(screen.getByRole('button')).toHaveAttribute('type', 'button');
    });

    it('fires onClick when activated', async () => {
        const user = userEvent.setup();
        const onClick = vi.fn();
        render(<Button onClick={onClick}>Click me</Button>);

        await user.click(screen.getByRole('button', { name: 'Click me' }));

        expect(onClick).toHaveBeenCalledOnce();
    });

    it('disables the button and shows the spinner while loading', () => {
        render(<Button loading>Saving</Button>);

        const button = screen.getByRole('button');
        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('aria-busy', 'true');
        expect(screen.getByRole('status')).toBeInTheDocument();
        expect(button).toHaveTextContent('Saving');
    });

    it('renders the danger variant with the error background token', () => {
        render(<Button variant="danger">Delete</Button>);

        expect(screen.getByRole('button')).toHaveClass('bg-error');
    });
});
