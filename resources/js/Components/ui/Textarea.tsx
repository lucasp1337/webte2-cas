import { forwardRef, type TextareaHTMLAttributes } from 'react';

import { cn } from '@/lib/cn';

export type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & {
    error?: boolean;
};

const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
    { className, error = false, rows, ...props },
    ref,
) {
    return (
        <textarea
            ref={ref}
            rows={rows ?? 4}
            aria-invalid={error}
            className={cn(
                'block w-full rounded-md border bg-surface px-3 py-2 text-base text-on-surface',
                'placeholder:text-on-surface-muted',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
                'disabled:cursor-not-allowed disabled:opacity-60',
                error ? 'border-error focus-visible:ring-error' : 'border-border focus-visible:ring-ring',
                className,
            )}
            {...props}
        />
    );
});

export default Textarea;
