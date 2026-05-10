import type { OctaveExecutionPayload, OctaveExecutionStatus } from '@/api/octave';
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
};

export default function OutputPanel({ entries }: OutputPanelProps) {
    const t = useT();

    if (entries.length === 0) {
        return (
            <div
                role="region"
                aria-label={t.console.outputTitle}
                className="rounded-md border border-border bg-surface-muted px-4 py-6 text-sm text-on-surface-muted"
            >
                {t.console.outputEmpty}
            </div>
        );
    }

    // Latest entry first.
    const ordered = [...entries].reverse();

    return (
        <div
            role="region"
            aria-label={t.console.outputTitle}
            data-testid="output-panel"
            className="flex flex-col gap-3"
        >
            {ordered.map((entry) => (
                <OutputEntry key={entry.id} entry={entry} />
            ))}
        </div>
    );
}

type OutputEntryProps = {
    entry: ConsoleEntry;
};

function OutputEntry({ entry }: OutputEntryProps) {
    const t = useT();
    const { status, payload, command } = entry;

    const wrapperClasses = cn(
        'rounded-md border bg-surface-raised',
        status === 'success' ? 'border-border' : 'border-error',
    );

    return (
        <article className={wrapperClasses} data-status={status}>
            <header className="border-b border-border px-4 py-2 font-mono text-xs text-on-surface-muted">
                <span aria-hidden="true">{'> '}</span>
                <span className="break-all">{command}</span>
            </header>
            <div className="px-4 py-3 font-mono text-sm">
                {status === 'success' && payload !== null && <SuccessOutput payload={payload} />}
                {status === 'rejected' && (
                    <RejectionOutput
                        title={t.console.rejectedTitle}
                        message={payload?.rejection_reason ?? payload?.stderr ?? ''}
                    />
                )}
                {status === 'timeout' && (
                    <RejectionOutput title={t.console.timeoutTitle} message={payload?.stderr ?? ''} />
                )}
                {status === 'unauthorized' && <RejectionOutput title={t.console.unauthorized} message="" />}
                {status === 'rate_limited' && <RejectionOutput title={t.console.rateLimited} message="" />}
                {status === 'bridge_unavailable' && <RejectionOutput title={t.console.bridgeUnavailable} message="" />}
                {status === 'network_error' && <RejectionOutput title={t.console.networkError} message="" />}
                {status === 'error' && <RejectionOutput title={t.console.genericError} message="" />}
            </div>
            {payload !== null && status === 'success' && (
                <footer className="border-t border-border px-4 py-2 text-xs text-on-surface-muted">
                    {t.console.durationLabel}: {payload.duration_ms} ms
                </footer>
            )}
        </article>
    );
}

type SuccessOutputProps = {
    payload: OctaveExecutionPayload;
};

function SuccessOutput({ payload }: SuccessOutputProps) {
    const hasStdout = payload.stdout.length > 0;
    const hasStderr = payload.stderr.length > 0;

    if (!hasStdout && !hasStderr) {
        return <span className="text-on-surface-muted">—</span>;
    }

    return (
        <div className="flex flex-col gap-2">
            {hasStdout && (
                <pre className="whitespace-pre-wrap break-words text-on-surface" data-testid="output-stdout">
                    {payload.stdout}
                </pre>
            )}
            {hasStderr && (
                <pre className="whitespace-pre-wrap break-words text-error" data-testid="output-stderr">
                    {payload.stderr}
                </pre>
            )}
        </div>
    );
}

type RejectionOutputProps = {
    title: string;
    message: string;
};

function RejectionOutput({ title, message }: RejectionOutputProps) {
    return (
        <div className="flex flex-col gap-1 text-error" data-testid="output-error">
            <strong className="font-semibold">{title}</strong>
            {message !== '' && <pre className="whitespace-pre-wrap break-words">{message}</pre>}
        </div>
    );
}
