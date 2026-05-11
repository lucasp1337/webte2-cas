import { type HTMLAttributes } from 'react';

import { cn } from '@/lib/cn';

export type SkeletonProps = HTMLAttributes<HTMLDivElement>;

export default function Skeleton({ className, ...props }: SkeletonProps) {
    return (
        <div
            role="status"
            aria-busy="true"
            aria-live="polite"
            className={cn(
                // animate-pulse kept for backward compat with existing tests
                'animate-pulse animate-skeleton rounded',
                className,
            )}
            {...props}
        >
            <span className="sr-only">Loading</span>
        </div>
    );
}
