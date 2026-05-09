import { useCallback, useEffect, useRef, type KeyboardEvent, type MouseEvent, type ReactNode } from 'react';

import { cn } from '@/lib/cn';

export type ModalProps = {
    open: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    closeLabel?: string;
    className?: string;
};

const FOCUSABLE_SELECTOR =
    'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export default function Modal({ open, onClose, title, children, closeLabel = 'Close', className }: ModalProps) {
    const dialogRef = useRef<HTMLDivElement | null>(null);
    const previouslyFocused = useRef<HTMLElement | null>(null);

    const focusFirst = useCallback(() => {
        const dialog = dialogRef.current;
        if (dialog === null) {
            return;
        }
        const focusable = dialog.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR);
        const first = focusable[0];
        if (first !== undefined) {
            first.focus();
        } else {
            dialog.focus();
        }
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        previouslyFocused.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        focusFirst();

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previousOverflow;
            previouslyFocused.current?.focus();
        };
    }, [open, focusFirst]);

    const handleKeyDown = useCallback(
        (event: KeyboardEvent<HTMLDivElement>) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                onClose();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const dialog = dialogRef.current;
            if (dialog === null) {
                return;
            }

            const focusable = Array.from(dialog.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR));
            if (focusable.length === 0) {
                event.preventDefault();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (first === undefined || last === undefined) {
                return;
            }

            const active = document.activeElement;
            if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        },
        [onClose],
    );

    const handleBackdropClick = useCallback(
        (event: MouseEvent<HTMLDivElement>) => {
            if (event.target === event.currentTarget) {
                onClose();
            }
        },
        [onClose],
    );

    if (!open) {
        return null;
    }

    const titleId = 'modal-title';

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            onClick={handleBackdropClick}
            onKeyDown={handleKeyDown}
            data-testid="modal-backdrop"
        >
            <div
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                tabIndex={-1}
                className={cn(
                    'w-full max-w-lg rounded-lg border border-border bg-surface-raised text-on-surface shadow-xl',
                    'focus-visible:outline-none',
                    className,
                )}
            >
                <div className="flex items-start justify-between border-b border-border px-6 py-4">
                    <h2 id={titleId} className="text-lg font-semibold">
                        {title}
                    </h2>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label={closeLabel}
                        className="rounded-md p-1 text-on-surface-muted hover:bg-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 24 24"
                            className="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                        >
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>
                <div className="px-6 py-4">{children}</div>
            </div>
        </div>
    );
}
