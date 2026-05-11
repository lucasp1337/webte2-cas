import { type HTMLAttributes, type ReactNode } from 'react';

import { cn } from '@/lib/cn';

export type BadgeVariant = 'success' | 'warning' | 'error' | 'info' | 'neutral' | 'accent';

export type BadgeProps = HTMLAttributes<HTMLSpanElement> & {
    variant?: BadgeVariant;
    /** Use 3 px radius instead of pill shape */
    square?: boolean;
    /** Show a small filled dot prefix matching the badge colour */
    dot?: boolean;
    children: ReactNode;
};

const VARIANT_CLASSES: Record<BadgeVariant, string> = {
    success: 'bg-success text-on-status border-success/30',
    warning: 'bg-warning text-on-status border-warning/30',
    error: 'bg-error text-on-status border-error/30',
    info: 'bg-info text-on-status border-info/30',
    neutral: 'bg-surface-raised text-on-surface-muted border-border',
    accent: 'bg-accent-soft text-accent border-accent/30',
};

export default function Badge({
    variant = 'neutral',
    square = false,
    dot = false,
    className,
    children,
    ...props
}: BadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-[5px] border px-[7px] py-px',
                'font-mono text-[11px] font-medium leading-[1.5] tracking-[0.02em]',
                square ? 'rounded-[3px]' : 'rounded-full',
                VARIANT_CLASSES[variant],
                className,
            )}
            {...props}
        >
            {dot && <span aria-hidden="true" className="h-[6px] w-[6px] shrink-0 rounded-full bg-current" />}
            {children}
        </span>
    );
}
