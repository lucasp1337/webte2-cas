import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react';

import Spinner from '@/Components/ui/Spinner';
import { cn } from '@/lib/cn';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';
export type ButtonSize = 'sm' | 'md' | 'lg';

export type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: ButtonVariant;
    size?: ButtonSize;
    loading?: boolean;
    leadingIcon?: ReactNode;
    trailingIcon?: ReactNode;
};

const VARIANT_CLASSES: Record<ButtonVariant, string> = {
    primary: 'bg-primary text-on-primary hover:bg-primary-hover focus-visible:ring-ring',
    secondary: 'bg-secondary text-on-secondary hover:bg-secondary-hover focus-visible:ring-ring',
    ghost: 'bg-transparent text-on-surface hover:bg-secondary focus-visible:ring-ring',
    danger: 'bg-error text-on-status hover:opacity-90 focus-visible:ring-error',
};

const SIZE_CLASSES: Record<ButtonSize, string> = {
    sm: 'h-8 px-3 text-sm rounded-md gap-1.5',
    md: 'h-10 px-4 text-base rounded-md gap-2',
    lg: 'h-12 px-5 text-lg rounded-lg gap-2',
};

const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
    {
        variant = 'primary',
        size = 'md',
        loading = false,
        leadingIcon,
        trailingIcon,
        className,
        children,
        disabled,
        type,
        ...props
    },
    ref,
) {
    const isDisabled = disabled === true || loading;

    return (
        <button
            ref={ref}
            type={type ?? 'button'}
            disabled={isDisabled}
            aria-busy={loading}
            className={cn(
                'inline-flex items-center justify-center font-medium transition-colors',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
                'disabled:cursor-not-allowed disabled:opacity-60',
                VARIANT_CLASSES[variant],
                SIZE_CLASSES[size],
                className,
            )}
            {...props}
        >
            {loading ? <Spinner size="sm" /> : leadingIcon}
            <span>{children}</span>
            {!loading && trailingIcon}
        </button>
    );
});

export default Button;
