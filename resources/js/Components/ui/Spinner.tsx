import { cn } from '@/lib/cn';

export type SpinnerSize = 'sm' | 'md' | 'lg';

export type SpinnerProps = {
    size?: SpinnerSize;
    label?: string;
    className?: string;
};

const SIZE_CLASSES: Record<SpinnerSize, string> = {
    sm: 'h-4 w-4 border-2',
    md: 'h-6 w-6 border-2',
    lg: 'h-10 w-10 border-[3px]',
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
                    'inline-block animate-spin rounded-full border-current border-t-transparent text-primary',
                    SIZE_CLASSES[size],
                )}
            />
            <span className="sr-only">{label ?? 'Loading'}</span>
        </span>
    );
}
