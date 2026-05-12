import { type CSSProperties } from 'react';

import Skeleton from '@/Components/ui/Skeleton';
import Spinner from '@/Components/ui/Spinner';
import { cn } from '@/lib/cn';

export type LoadingStateProps = {
    variant?: 'spinner' | 'skeleton';
    /** Only used in spinner variant. */
    label?: string;
    /** Only used in skeleton variant — number of bars to render. */
    rows?: number;
    className?: string;
};

// Widths cycle so the skeleton looks natural: 60% / 80% / 45%.
// Defined as a const tuple so noUncheckedIndexedAccess is satisfied.
const SKELETON_WIDTHS = ['60%', '80%', '45%'] as const;

function skeletonWidth(index: number): CSSProperties {
    // Modulo ensures we stay in-bounds; cast is safe because the array has
    // exactly 3 items and `index % 3` is always 0, 1, or 2.
    const w = SKELETON_WIDTHS[index % SKELETON_WIDTHS.length];

    // `w` is `string | undefined` with noUncheckedIndexedAccess even for a
    // const tuple when accessed via a computed index — hence the fallback.
    return { width: w ?? '60%' };
}

export default function LoadingState({ variant = 'spinner', label, rows = 3, className }: LoadingStateProps) {
    if (variant === 'skeleton') {
        return (
            <div className={cn('flex flex-col gap-3 rounded-lg border border-border bg-surface-raised p-6', className)}>
                {Array.from({ length: rows }, (_, i) => (
                    // Index-keyed; the list is static (derived from `rows` prop) so key-by-index is safe.
                    <Skeleton key={i} className="h-[10px] rounded" style={skeletonWidth(i)} />
                ))}
            </div>
        );
    }

    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-3 rounded-lg border border-border bg-surface-raised px-6 py-10',
                className,
            )}
        >
            <Spinner size="lg" />
            {label !== undefined && <p className="text-[13px] text-on-surface-muted">{label}</p>}
        </div>
    );
}
