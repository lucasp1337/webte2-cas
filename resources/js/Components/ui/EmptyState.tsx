import { type ReactNode } from 'react';

import { cn } from '@/lib/cn';

export type EmptyStateProps = {
    title: string;
    description?: string;
    icon?: ReactNode;
    action?: ReactNode;
    className?: string;
};

export default function EmptyState({ title, description, icon, action, className }: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border',
                'bg-surface-raised px-6 py-10 text-center',
                className,
            )}
        >
            {icon !== undefined && (
                <span className="flex h-10 w-10 items-center justify-center text-on-surface-faint">{icon}</span>
            )}
            <div>
                <p className="text-[14px] font-semibold leading-tight tracking-[-0.01em] text-on-surface">{title}</p>
                {description !== undefined && (
                    <p className="mt-1 text-[13px] leading-[1.5] text-on-surface-muted">{description}</p>
                )}
            </div>
            {action !== undefined && <div className="mt-1">{action}</div>}
        </div>
    );
}
