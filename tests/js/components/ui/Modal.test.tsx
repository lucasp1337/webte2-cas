import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import Modal from '@/Components/ui/Modal';

describe('Modal', () => {
    it('renders nothing when closed', () => {
        render(
            <Modal open={false} onClose={() => undefined} title="Confirm">
                <p>body</p>
            </Modal>,
        );

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('renders a labelled dialog when open', () => {
        render(
            <Modal open onClose={() => undefined} title="Confirm">
                <p>body</p>
            </Modal>,
        );

        const dialog = screen.getByRole('dialog');
        expect(dialog).toHaveAttribute('aria-modal', 'true');
        expect(dialog).toHaveAccessibleName('Confirm');
    });

    it('closes when the user presses Escape', async () => {
        const user = userEvent.setup();
        const onClose = vi.fn();
        render(
            <Modal open onClose={onClose} title="Confirm">
                <button type="button">First</button>
            </Modal>,
        );

        await user.keyboard('{Escape}');

        expect(onClose).toHaveBeenCalledOnce();
    });

    it('closes when the backdrop is clicked', async () => {
        const user = userEvent.setup();
        const onClose = vi.fn();
        render(
            <Modal open onClose={onClose} title="Confirm">
                <p>body</p>
            </Modal>,
        );

        await user.click(screen.getByTestId('modal-backdrop'));

        expect(onClose).toHaveBeenCalledOnce();
    });

    it('closes when the close button is clicked', async () => {
        const user = userEvent.setup();
        const onClose = vi.fn();
        render(
            <Modal open onClose={onClose} title="Confirm" closeLabel="Close dialog">
                <p>body</p>
            </Modal>,
        );

        await user.click(screen.getByRole('button', { name: 'Close dialog' }));

        expect(onClose).toHaveBeenCalledOnce();
    });
});
