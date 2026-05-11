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
    // Primary uses the CTA gradient; brightness filter on hover avoids a second
    // gradient definition and matches the design spec exactly.
    primary: 'border-primary text-on-primary hover:brightness-[1.08] focus-visible:ring-accent',
    secondary:
        'bg-surface-raised text-on-surface border-border-strong hover:bg-surface-sunken focus-visible:ring-accent',
    ghost: 'bg-transparent text-on-surface-muted border-transparent hover:bg-surface-sunken hover:text-on-surface focus-visible:ring-accent',
    danger: 'bg-error text-on-status border-transparent hover:opacity-90 focus-visible:ring-error',
};

// Heights: sm=26px md=32px lg=40px — match the design spec.
const SIZE_CLASSES: Record<ButtonSize, string> = {
    sm: 'h-[26px] px-[9px] text-[12px] rounded gap-1.5',
    md: 'h-8 px-3 text-[13px] rounded gap-1.5',
    lg: 'h-10 px-[18px] text-sm rounded gap-1.5',
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
        style,
        ...props
    },
    ref,
) {
    const isDisabled = disabled === true || loading;

    // Primary variant uses a CSS gradient background that Tailwind can't express
    // as a single utility class, so we inject it via inline style only for primary.
    const gradientStyle = variant === 'primary' ? { background: 'var(--cta-gradient)', ...style } : style;

    return (
        <button
            ref={ref}
            type={type ?? 'button'}
            disabled={isDisabled}
            aria-busy={loading}
            style={gradientStyle}
            className={cn(
                'inline-flex cursor-pointer items-center justify-center border font-medium tracking-[-0.005em]',
                'transition-[background,color,border-color,filter]',
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
