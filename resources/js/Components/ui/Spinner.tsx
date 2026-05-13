import { cn } from '@/lib/cn';

export type SpinnerSize = 'sm' | 'md' | 'lg';

export type SpinnerProps = {
    size?: SpinnerSize;
    label?: string;
    className?: string;
};

// Dimensions mirror the design: sm≈12px md≈16px lg≈20px
const SIZE_CLASSES: Record<SpinnerSize, string> = {
    sm: 'h-3 w-3',
    md: 'h-4 w-4',
    lg: 'h-5 w-5',
};

export default function Spinner({ size = 'md', label, className }: SpinnerProps) {
    return (
        <span
            role="status"
            aria-live="polite"
            aria-busy="true"
            className={cn('inline-flex items-center justify-center', className)}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'inline-block animate-spin rounded-full border-current',
                    // border-r-transparent creates the open arc; border-[1.5px] matches design
                    'border-[1.5px] border-r-transparent',
                    // 0.7s linear is set via the Tailwind animate-spin duration override below;
                    // we override the default 1s with a style prop to match design exactly.
                    SIZE_CLASSES[size],
                )}
                style={{ animationDuration: '0.7s', animationTimingFunction: 'linear' }}
            />
            <span className="sr-only">{label ?? 'Loading'}</span>
        </span>
    );
}
