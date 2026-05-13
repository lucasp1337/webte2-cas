import { WarnIcon } from '@/Components/icons';
import Button from '@/Components/ui/Button';
import { cn } from '@/lib/cn';

export type ErrorStateProps = {
    title: string;
    message?: string;
    onRetry?: () => void;
    /** Label for the retry button. Pull from i18n at the call site. */
    retryLabel?: string;
    className?: string;
};

export default function ErrorState({ title, message, onRetry, retryLabel = 'Retry', className }: ErrorStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-3 rounded-lg border px-6 py-10 text-center',
                'border-error/40 bg-error-bg',
                className,
            )}
        >
            <span className="flex h-10 w-10 items-center justify-center text-error">
                <WarnIcon size={24} />
            </span>
            <div>
                <p className="text-[14px] font-semibold leading-tight tracking-[-0.01em] text-on-surface">{title}</p>
                {message !== undefined && (
                    <p className="mt-1 text-[13px] leading-[1.5] text-on-surface-muted">{message}</p>
                )}
            </div>
            {onRetry !== undefined && (
                <div className="mt-1">
                    <Button variant="secondary" size="sm" onClick={onRetry}>
                        {retryLabel}
                    </Button>
                </div>
            )}
        </div>
    );
}
