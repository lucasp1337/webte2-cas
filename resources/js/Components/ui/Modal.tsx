import { useCallback, useEffect, useRef, type KeyboardEvent, type MouseEvent, type ReactNode } from 'react';

import { cn } from '@/lib/cn';

export type ModalProps = {
    open: boolean;
    onClose: () => void;
    title?: string;
    children: ReactNode;
    footer?: ReactNode;
    closeLabel?: string;
    className?: string;
};

const FOCUSABLE_SELECTOR =
    'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export default function Modal({ open, onClose, title, children, footer, closeLabel = 'Close', className }: ModalProps) {
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

    // Stable ID — only one modal can be open at a time so no collision risk.
    const titleId = 'modal-title';

    return (
        <div
            className="fixed inset-0 z-20 flex items-center justify-center backdrop-blur-sm"
            style={{ background: 'var(--surface-overlay)' }}
            onClick={handleBackdropClick}
            onKeyDown={handleKeyDown}
            data-testid="modal-backdrop"
        >
            <div
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby={title !== undefined ? titleId : undefined}
                tabIndex={-1}
                className={cn(
                    'w-full max-w-[380px] rounded-lg border border-border bg-surface-raised text-on-surface',
                    'p-[22px]',
                    // Design spec: 0 8px 32px rgba(0,0,0,0.18)
                    'shadow-[0_8px_32px_rgba(0,0,0,0.18)]',
                    'focus-visible:outline-none',
                    className,
                )}
            >
                {/* Header row — title + close button */}
                <div className="mb-4 flex items-start justify-between gap-3">
                    {title !== undefined ? (
                        <h2 id={titleId} className="text-[15px] font-semibold leading-tight tracking-[-0.01em]">
                            {title}
                        </h2>
                    ) : (
                        // Keeps the close button right-aligned when there's no title
                        <span aria-hidden="true" />
                    )}
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label={closeLabel}
                        className={cn(
                            'flex h-6 w-6 shrink-0 items-center justify-center rounded',
                            'text-on-surface-muted',
                            'hover:bg-surface-sunken hover:text-on-surface',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                        )}
                    >
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 16 16"
                            className="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                        >
                            <path d="M4 4l8 8M12 4L4 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div>{children}</div>

                {/* Optional footer slot */}
                {footer !== undefined && <div className="mt-4">{footer}</div>}
            </div>
        </div>
    );
}
