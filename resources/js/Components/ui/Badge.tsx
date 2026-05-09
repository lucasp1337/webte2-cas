import { type HTMLAttributes, type ReactNode } from 'react';

import { cn } from '@/lib/cn';

export type BadgeVariant = 'success' | 'warning' | 'error' | 'info' | 'neutral';

export type BadgeProps = HTMLAttributes<HTMLSpanElement> & {
    variant?: BadgeVariant;
    children: ReactNode;
};

const VARIANT_CLASSES: Record<BadgeVariant, string> = {
    success: 'bg-success text-on-status',
    warning: 'bg-warning text-on-status',
    error: 'bg-error text-on-status',
    info: 'bg-info text-on-status',
    neutral: 'bg-secondary text-on-secondary',
};

export default function Badge({ variant = 'neutral', className, children, ...props }: BadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium',
                VARIANT_CLASSES[variant],
                className,
            )}
            {...props}
        >
            {children}
        </span>
    );
}
