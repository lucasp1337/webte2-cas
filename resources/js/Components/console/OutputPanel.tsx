import type { OctaveExecutionPayload, OctaveExecutionStatus } from '@/api/octave';
import Button from '@/Components/ui/Button';
import { WarnIcon, RefreshIcon } from '@/Components/icons';
import Skeleton from '@/Components/ui/Skeleton';
import { useT } from '@/hooks/useT';
import { cn } from '@/lib/cn';

export type ConsoleEntry = {
    id: string;
    command: string;
    status: OctaveExecutionStatus;
    payload: OctaveExecutionPayload | null;
};

export type OutputPanelProps = {
    entries: ConsoleEntry[];
    isRunning?: boolean;
    /** When true, renders the bridge-unavailable error state instead of entries. */
    bridgeError?: boolean;
    onRetry?: () => void;
    onInsertExample?: () => void;
};

export default function OutputPanel({
    entries,
    isRunning = false,
    bridgeError = false,
    onRetry,
    onInsertExample,
}: OutputPanelProps) {
    const t = useT();

    // Latest entry first — entries are appended chronologically by Console.tsx.
    const ordered = [...entries].reverse();
    const count = entries.length;

    return (
        <section aria-label={t.console.outputTitle}>
            {/* Eyebrow row */}
            <div className="mb-2.5 flex items-center justify-between">
                <span className="font-mono text-[11px] uppercase tracking-[0.08em] text-on-surface-muted">
                    {t.console.outputLabel}
                    <span className="ml-1 normal-case tracking-normal text-on-surface-faint">·</span>
                    <span className="ml-1 normal-case tracking-normal">{bridgeError ? '—' : count.toString()}</span>
                </span>
                {!bridgeError && (
                    <span className="font-mono text-[11px] text-on-surface-faint">{t.console.newestFirst}</span>
                )}
            </div>

            {/* Loading skeleton — a command is in-flight */}
            {isRunning && (
                <div className="mb-2.5 rounded-md border border-border p-3.5">
                    <Skeleton className="mb-2.5 h-[11px] w-[55%]" />
                    <Skeleton className="mb-2 h-[11px] w-[80%]" />
                    <Skeleton className="h-[11px] w-[40%]" />
                </div>
            )}

            {/* Bridge-error state */}
            {bridgeError && (
                <div
                    className="rounded-md border p-[18px]"
                    style={{
                        borderColor: 'color-mix(in oklab, var(--error) 40%, transparent)',
                        background: 'var(--error-bg)',
                    }}
                >
                    <div className="flex items-start gap-3">
                        <span className="mt-px shrink-0 text-error">
                            <WarnIcon size={14} />
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="mb-1 font-semibold text-on-surface">{t.console.bridgeUnavailable}</div>
                            <div className="mb-1 text-[13px] text-on-surface-muted">
                                {t.console.bridgeUnavailableDetail1}
                            </div>
                            <div className="mb-3.5 text-[13px] text-on-surface-muted">
                                {t.console.bridgeUnavailableDetail2}
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    leadingIcon={<RefreshIcon size={11} />}
                                    onClick={onRetry}
                                >
                                    {t.console.retry}
                                </Button>
                                <Button variant="ghost" size="sm">
                                    {t.console.serviceStatus}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Empty state — no entries and no error */}
            {!bridgeError && count === 0 && !isRunning && (
                <div
                    role="region"
                    aria-label={t.console.outputTitle}
                    className="rounded-md border border-dashed border-border bg-surface p-5 text-left"
                    data-testid="output-panel"
                >
                    <div className="mb-1 font-mono text-[11px] uppercase tracking-[0.08em] text-on-surface-muted">
                        {t.console.tryHint}
                    </div>
                    <div className="mb-3.5 mt-1 inline-block rounded-md border border-border bg-code-bg px-2.5 py-1.5 font-mono text-[12.5px]">
                        <span className="text-on-surface">a</span>
                        <span className="text-on-surface-muted"> = </span>
                        <span className="text-code-number">1</span>
                        <span className="text-on-surface-muted">+</span>
                        <span className="text-code-number">1</span>
                        <span className="text-on-surface-muted">; </span>
                        <span className="text-on-surface">a</span>
                        <span className="text-on-surface-muted">+</span>
                        <span className="text-code-number">2</span>
                    </div>
                    <div>
                        <Button variant="secondary" size="sm" onClick={onInsertExample}>
                            {t.console.insertExample}
                        </Button>
                    </div>
                </div>
            )}

            {/* Entry list */}
            {!bridgeError && count > 0 && (
                <div
                    role="region"
                    aria-label={t.console.outputTitle}
                    data-testid="output-panel"
                    className="flex flex-col"
                >
                    {ordered.map((entry) => (
                        <OutputEntry key={entry.id} entry={entry} />
                    ))}
                </div>
            )}
        </section>
    );
}

type OutputEntryProps = {
    entry: ConsoleEntry;
};

function OutputEntry({ entry }: OutputEntryProps) {
    const { status, payload, command } = entry;
    const isRejection = status !== 'success';

    if (isRejection) {
        return (
            <article
                className="relative mb-2.5 rounded-md border font-mono text-[12.5px]"
                style={{
                    borderColor: 'color-mix(in oklab, var(--error) 40%, transparent)',
                    background: 'var(--error-bg)',
                    padding: '12px 14px',
                }}
                data-status={status}
            >
                <div className="mb-1.5 flex items-center justify-between">
                    <span className="font-semibold tracking-[0.02em] text-error">
                        <RejectionLabel status={status} payload={payload} />
                    </span>
                    <span className="text-on-surface-muted">—</span>
                </div>
                <div className="text-on-surface-muted">
                    <span aria-hidden="true">{'> '}</span>
                    {command}
                </div>
            </article>
        );
    }

    const hasStdout = (payload?.stdout ?? '').length > 0;
    const hasStderr = (payload?.stderr ?? '').length > 0;
    const ms = payload?.duration_ms;

    return (
        <article
            className="relative mb-2.5 rounded-md border border-border bg-surface-raised font-mono text-[12.5px]"
            style={{ padding: '12px 14px' }}
            data-status={status}
        >
            <div className={cn('text-on-surface-muted', hasStdout || hasStderr ? 'mb-2' : '')}>
                <span aria-hidden="true">{'> '}</span>
                {command}
            </div>

            {hasStdout && (
                <pre
                    className="m-0 whitespace-pre-wrap break-words leading-[1.55] text-on-surface"
                    data-testid="output-stdout"
                >
                    {payload?.stdout}
                </pre>
            )}

            {hasStderr && (
                <pre
                    className={cn(
                        'm-0 whitespace-pre-wrap break-words leading-[1.55] text-error',
                        hasStdout ? 'mt-1.5' : '',
                    )}
                    data-testid="output-stderr"
                >
                    {payload?.stderr}
                </pre>
            )}

            {ms !== undefined && (
                <span className="absolute bottom-2 right-3 text-[10.5px] tracking-[0.04em] text-on-surface-faint">
                    {ms} ms
                </span>
            )}
        </article>
    );
}

type RejectionLabelProps = {
    status: OctaveExecutionStatus;
    payload: OctaveExecutionPayload | null;
};

function RejectionLabel({ status, payload }: RejectionLabelProps) {
    const t = useT();

    const reason = payload?.rejection_reason ?? payload?.stderr ?? '';

    switch (status) {
        case 'rejected':
            return (
                <span data-testid="output-error">
                    REJECTED
                    {reason !== '' && (
                        <>
                            {' · '}
                            {reason}
                        </>
                    )}
                </span>
            );
        case 'timeout':
            return <span data-testid="output-error">{t.console.timeoutTitle.toUpperCase()}</span>;
        case 'unauthorized':
            return <span data-testid="output-error">{t.console.unauthorized}</span>;
        case 'rate_limited':
            return <span data-testid="output-error">{t.console.rateLimited}</span>;
        case 'bridge_unavailable':
            return <span data-testid="output-error">{t.console.bridgeUnavailable}</span>;
        case 'network_error':
            return <span data-testid="output-error">{t.console.networkError}</span>;
        default:
            return <span data-testid="output-error">{t.console.genericError}</span>;
    }
}
