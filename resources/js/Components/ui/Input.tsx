import { forwardRef, type InputHTMLAttributes } from 'react';

import { cn } from '@/lib/cn';

export type InputProps = InputHTMLAttributes<HTMLInputElement> & {
    error?: boolean;
};

const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
    { className, error = false, type, ...props },
    ref,
) {
    return (
        <input
            ref={ref}
            type={type ?? 'text'}
            aria-invalid={error}
            className={cn(
                'h-10 w-full rounded-md border bg-surface px-3 text-base text-on-surface',
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

export default Input;
