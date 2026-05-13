import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import {
    createLogsClient,
    type CsvJobState,
    type ListLogsOptions,
    type LogRow,
    type LogsClient,
    type LogsPage as LogsPageData,
} from '@/api/logs';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Modal from '@/Components/ui/Modal';
import Skeleton from '@/Components/ui/Skeleton';
import { useT } from '@/hooks/useT';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/cn';

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

export type LogsProps = {
    apiKey: string;
    /** Test-only injection point for the API client. */
    client?: LogsClient;
};

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const PER_PAGE = 20;

type FetchState = 'idle' | 'loading' | 'success' | 'error';
type ExportState = 'idle' | 'requesting' | 'polling' | 'done' | 'failed';
const POLL_INTERVAL_MS = 2000;
const TEXT_DEBOUNCE_MS = 400;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

type StatusFilter = 'all' | '2xx' | '4xx' | '5xx';

function statusFilterToCode(f: StatusFilter): number | undefined {
    if (f === '2xx') return 200;
    if (f === '4xx') return 400;
    if (f === '5xx') return 500;
    return undefined;
}

function statusBadgeVariant(status: number) {
    if (status >= 200 && status < 300) return 'success' as const;
    if (status >= 400 && status < 500) return 'warning' as const;
    if (status >= 500) return 'error' as const;
    return 'neutral' as const;
}

function methodBadgeVariant(method: string) {
    if (method === 'GET') return 'accent' as const;
    if (method === 'POST') return 'success' as const;
    if (method === 'DELETE') return 'error' as const;
    return 'neutral' as const;
}

function formatRelativeTime(isoString: string): string {
    const date = new Date(isoString);
    const diffMs = Date.now() - date.getTime();
    const diffSec = Math.floor(diffMs / 1000);
    if (diffSec < 60) return `${diffSec.toString()}s ago`;
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `${diffMin.toString()}m ago`;
    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return `${diffHr.toString()}h ago`;
    return date.toLocaleDateString();
}

function formatAbsoluteTime(isoString: string): string {
    return new Date(isoString).toLocaleString();
}

function interpolate(template: string, vars: Record<string, string | number>): string {
    return template.replace(/\{(\w+)\}/g, (_, key) => {
        const val = vars[key as string];
        return val !== undefined ? String(val) : `{${key as string}}`;
    });
}

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

type SkeletonRowsProps = { columns: number; rows?: number };

function SkeletonRows({ columns, rows = 8 }: SkeletonRowsProps) {
    const WIDTHS = [80, 36, 180, 50, 50, 80];
    return (
        <>
            {Array.from({ length: rows }).map((_, i) => (
                <tr key={i} aria-hidden="true">
                    {Array.from({ length: columns }).map((__, j) => (
                        <td key={j} className="px-4 py-3">
                            <Skeleton
                                className="h-[10px] rounded-sm bg-surface-sunken"
                                style={{ width: WIDTHS[j] ?? 100 }}
                            />
                        </td>
                    ))}
                </tr>
            ))}
        </>
    );
}

type LogRowProps = { row: LogRow };

function LogTableRow({ row }: LogRowProps) {
    return (
        <tr className="border-b border-border-subtle transition-colors hover:bg-surface-sunken">
            <td className="px-4 py-2.5">
                <span
                    className="cursor-default font-mono text-[12px] text-on-surface-muted"
                    title={formatAbsoluteTime(row.created_at)}
                >
                    {formatRelativeTime(row.created_at)}
                </span>
            </td>
            <td className="px-4 py-2.5">
                <Badge variant={methodBadgeVariant(row.method)} square>
                    {row.method}
                </Badge>
            </td>
            <td className="max-w-[280px] overflow-hidden px-4 py-2.5">
                <span className="block truncate font-mono text-[12.5px]">{row.path}</span>
            </td>
            <td className="px-4 py-2.5">
                <Badge variant={statusBadgeVariant(row.status)} dot square>
                    {row.status}
                </Badge>
            </td>
            <td className="px-4 py-2.5">
                <span className="font-mono text-[12px] text-on-surface-muted">{row.duration_ms} ms</span>
            </td>
            <td className="px-4 py-2.5">
                {row.api_key_prefix !== null ? (
                    <span className="font-mono text-[12px] text-on-surface-muted">{row.api_key_prefix}</span>
                ) : (
                    <span className="text-[12px] text-on-surface-faint">—</span>
                )}
            </td>
        </tr>
    );
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function Logs({ apiKey, client }: LogsProps) {
    const t = useT();

    // useMemo is required here for correctness: logsClient sits in the
    // dependency array of fetchLogs which drives the useEffect fetch loop.
    // Without a stable reference the effect would re-run on every render.
    const logsClient = useMemo<LogsClient>(() => client ?? createLogsClient({ apiKey }), [client, apiKey]);

    // ---------------------------------------------------------------------------
    // Filter state
    // ---------------------------------------------------------------------------
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
    const [routeFilter, setRouteFilter] = useState<string>('');
    const [routeInput, setRouteInput] = useState<string>('');
    const debounceRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    // Debounce text input → committed filter
    const handleRouteInputChange = useCallback((value: string) => {
        setRouteInput(value);
        if (debounceRef.current !== undefined) {
            clearTimeout(debounceRef.current);
        }
        debounceRef.current = setTimeout(() => {
            setRouteFilter(value);
            setPage(1);
        }, TEXT_DEBOUNCE_MS);
    }, []);

    // ---------------------------------------------------------------------------
    // Pagination
    // ---------------------------------------------------------------------------
    const [page, setPage] = useState<number>(1);

    // ---------------------------------------------------------------------------
    // Data fetching
    // ---------------------------------------------------------------------------
    const [fetchState, setFetchState] = useState<FetchState>('loading');
    const [logsData, setLogsData] = useState<LogsPageData | null>(null);
    const [errorMsg, setErrorMsg] = useState<string>('');

    const fetchLogs = useCallback(async () => {
        setFetchState('loading');
        try {
            const opts: ListLogsOptions = {
                page,
                perPage: PER_PAGE,
                status: statusFilterToCode(statusFilter),
                route: routeFilter !== '' ? routeFilter : undefined,
            };
            const data = await logsClient.listLogs(opts);
            setLogsData(data);
            setFetchState('success');
        } catch (err) {
            setErrorMsg(err instanceof Error ? err.message : String(err));
            setFetchState('error');
        }
    }, [logsClient, page, statusFilter, routeFilter]);

    useEffect(() => {
        void fetchLogs();
    }, [fetchLogs]);

    // ---------------------------------------------------------------------------
    // CSV export state
    // ---------------------------------------------------------------------------
    const [exportState, setExportState] = useState<ExportState>('idle');
    const [exportJob, setExportJob] = useState<CsvJobState | null>(null);
    const [exportModalOpen, setExportModalOpen] = useState<boolean>(false);
    const pollTimerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    const cancelExport = useCallback(() => {
        if (pollTimerRef.current !== undefined) {
            clearTimeout(pollTimerRef.current);
        }
        setExportState('idle');
        setExportJob(null);
        setExportModalOpen(false);
    }, []);

    const doPoll = useCallback(
        async (jobId: string) => {
            try {
                const result = await logsClient.pollCsvExport(jobId);
                if ('kind' in result && result.kind === 'binary') {
                    // Trigger download
                    const url = URL.createObjectURL(result.blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `request-logs-${new Date().toISOString().slice(0, 10)}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                    setExportState('done');
                    setExportModalOpen(false);
                } else {
                    // The `else` branch excludes `{ kind: 'binary' }` — the
                    // remaining union member is CsvJobState. TypeScript doesn't
                    // narrow discriminated unions across `'kind' in result`
                    // guards when the other member lacks the key, so we assert.
                    const job = result as CsvJobState;
                    setExportJob(job);
                    if (job.status === 'failed') {
                        setExportState('failed');
                    } else if (job.status === 'done') {
                        // Next poll will get the binary
                        pollTimerRef.current = setTimeout(() => {
                            void doPoll(jobId);
                        }, POLL_INTERVAL_MS);
                    } else {
                        pollTimerRef.current = setTimeout(() => {
                            void doPoll(jobId);
                        }, POLL_INTERVAL_MS);
                    }
                }
            } catch {
                setExportState('failed');
            }
        },
        [logsClient],
    );

    const handleExportCsv = useCallback(async () => {
        if (exportState === 'requesting' || exportState === 'polling') return;
        setExportState('requesting');
        try {
            const result = await logsClient.requestCsvExport();
            if (result.kind === 'direct') {
                setExportState('done');
                setTimeout(() => setExportState('idle'), 2000);
            } else {
                setExportJob(result.job);
                setExportState('polling');
                setExportModalOpen(true);
                pollTimerRef.current = setTimeout(() => {
                    void doPoll(result.job.job_id);
                }, POLL_INTERVAL_MS);
            }
        } catch {
            setExportState('failed');
            setTimeout(() => setExportState('idle'), 3000);
        }
    }, [exportState, logsClient, doPoll]);

    // ---------------------------------------------------------------------------
    // Pagination helpers
    // ---------------------------------------------------------------------------
    const meta = logsData?.meta;
    const totalPages = meta?.last_page ?? 1;
    const currentPage = meta?.current_page ?? page;

    const handlePrevPage = useCallback(() => {
        if (currentPage > 1) setPage(currentPage - 1);
    }, [currentPage]);

    const handleNextPage = useCallback(() => {
        if (currentPage < totalPages) setPage(currentPage + 1);
    }, [currentPage, totalPages]);

    // ---------------------------------------------------------------------------
    // Render helpers
    // ---------------------------------------------------------------------------
    const isLoading = fetchState === 'loading';
    const isError = fetchState === 'error';
    const isEmpty = fetchState === 'success' && (logsData?.data.length ?? 0) === 0;
    const hasRows = fetchState === 'success' && (logsData?.data.length ?? 0) > 0;

    const exportButtonLabel =
        exportState === 'requesting' || exportState === 'polling'
            ? t.logs.exporting
            : exportState === 'done'
              ? t.logs.exportCsv
              : t.logs.exportCsv;

    const columns = [
        t.logs.columns.time,
        t.logs.columns.method,
        t.logs.columns.endpoint,
        t.logs.columns.status,
        t.logs.columns.duration,
        t.logs.columns.key,
    ];

    return (
        <AppLayout title={t.logs.title}>
            {/* Page header */}
            <div className="flex items-end justify-between gap-3">
                <div>
                    <h1 className="text-3xl font-bold text-on-surface">{t.logs.title}</h1>
                    <p className="mt-1 text-on-surface-muted">{t.logs.subtitle}</p>
                </div>
            </div>

            {/* Filter bar */}
            <Card className="mt-6 p-4">
                <div className="flex flex-wrap items-end gap-3">
                    {/* Status filter */}
                    <div className="flex flex-col gap-1">
                        <label className="text-xs font-medium text-on-surface-muted" htmlFor="status-filter">
                            {t.logs.statusLabel}
                        </label>
                        <select
                            id="status-filter"
                            data-testid="status-filter"
                            value={statusFilter}
                            onChange={(e) => {
                                // The value is typed by the option values below
                                setStatusFilter(e.target.value as StatusFilter);
                                setPage(1);
                            }}
                            className="h-8 rounded-md border border-border bg-surface px-2 text-sm text-on-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option value="all">{t.logs.statusAll}</option>
                            <option value="2xx">{t.logs.statusSuccess}</option>
                            <option value="4xx">{t.logs.statusClientError}</option>
                            <option value="5xx">{t.logs.statusServerError}</option>
                        </select>
                    </div>

                    {/* Route filter */}
                    <div className="flex flex-col gap-1">
                        <label className="text-xs font-medium text-on-surface-muted" htmlFor="route-filter">
                            {t.logs.routeLabel}
                        </label>
                        <input
                            id="route-filter"
                            data-testid="route-filter"
                            type="text"
                            value={routeInput}
                            onChange={(e) => handleRouteInputChange(e.target.value)}
                            placeholder={t.logs.routePlaceholder}
                            className="h-8 w-56 rounded-md border border-border bg-surface px-2 text-sm text-on-surface placeholder:text-on-surface-faint focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />
                    </div>

                    {/* Clear filters */}
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setStatusFilter('all');
                            setRouteFilter('');
                            setRouteInput('');
                            setPage(1);
                        }}
                        data-testid="clear-filters-button"
                    >
                        {t.logs.clearFilters}
                    </Button>
                </div>
            </Card>

            {/* Action row */}
            <div className="mt-4 flex items-center justify-between">
                <p className="font-mono text-xs text-on-surface-muted">
                    {meta !== undefined && meta.total > 0
                        ? interpolate(t.logs.pagination.showing, {
                              from: meta.from ?? 1,
                              to: meta.to ?? meta.total,
                              total: meta.total,
                          })
                        : null}
                    {meta !== undefined && meta.total > 0 && (
                        <span className="ml-2 text-on-surface-faint">
                            · {interpolate(t.logs.pagination.pageOf, { current: currentPage, total: totalPages })}
                        </span>
                    )}
                </p>

                <Button
                    variant="secondary"
                    size="sm"
                    loading={exportState === 'requesting' || exportState === 'polling'}
                    onClick={() => void handleExportCsv()}
                    data-testid="export-csv-button"
                >
                    {exportButtonLabel}
                </Button>
            </div>

            {/* Table */}
            <div className="mt-3">
                {isError && (
                    <div
                        data-testid="error-state"
                        className="flex items-start gap-4 rounded-lg border border-error/40 bg-error-bg p-6"
                    >
                        <svg
                            aria-hidden="true"
                            className="mt-0.5 h-5 w-5 shrink-0 text-error"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                fillRule="evenodd"
                                d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                                clipRule="evenodd"
                            />
                        </svg>
                        <div className="flex-1">
                            <p className="font-semibold text-on-surface">{t.logs.loadError}</p>
                            <p className="mt-1 font-mono text-xs text-on-surface-muted">{errorMsg}</p>
                            <Button
                                variant="secondary"
                                size="sm"
                                className="mt-4"
                                onClick={() => void fetchLogs()}
                                data-testid="retry-button"
                            >
                                {t.logs.retry}
                            </Button>
                        </div>
                    </div>
                )}

                {isEmpty && (
                    <Card
                        className="flex flex-col items-center border-dashed bg-surface px-6 py-16 text-center"
                        data-testid="empty-state"
                    >
                        <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-lg border border-border text-on-surface-muted">
                            <svg
                                aria-hidden="true"
                                className="h-5 w-5"
                                viewBox="0 0 20 20"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.5"
                            >
                                <circle cx="8.5" cy="8.5" r="5.5" />
                                <path strokeLinecap="round" d="M15.5 15.5l3 3" />
                            </svg>
                        </div>
                        <p className="text-base font-semibold text-on-surface">{t.logs.empty}</p>
                        <p className="mt-1 text-sm text-on-surface-muted">{t.logs.emptyHint}</p>
                    </Card>
                )}

                {(isLoading || hasRows) && (
                    <Card className="overflow-hidden p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-sm">
                                <thead className="sticky top-0 z-10 bg-surface-raised">
                                    <tr className="border-b border-border">
                                        {columns.map((col) => (
                                            <th
                                                key={col}
                                                className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-on-surface-muted"
                                            >
                                                {col}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {isLoading ? (
                                        <SkeletonRows columns={columns.length} />
                                    ) : (
                                        logsData?.data.map((row) => <LogTableRow key={row.request_id} row={row} />)
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination footer */}
                        <div
                            className={cn(
                                'flex items-center justify-between border-t border-border px-4 py-3',
                                'font-mono text-xs text-on-surface-muted',
                            )}
                        >
                            <span>
                                {meta !== undefined && meta.total > 0
                                    ? `${String(meta.total)} ${t.logs.records}`
                                    : null}
                            </span>

                            <div className="flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={handlePrevPage}
                                    disabled={currentPage <= 1}
                                    data-testid="prev-page-button"
                                >
                                    {t.logs.pagination.prev}
                                </Button>

                                <span className="px-2 text-on-surface-faint">
                                    {currentPage} / {totalPages}
                                </span>

                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleNextPage}
                                    disabled={currentPage >= totalPages}
                                    data-testid="next-page-button"
                                >
                                    {t.logs.pagination.next}
                                </Button>
                            </div>
                        </div>
                    </Card>
                )}
            </div>

            {/* Large-export progress modal */}
            <Modal
                open={exportModalOpen}
                onClose={cancelExport}
                title={t.logs.exportModal.title}
                closeLabel={t.common.cancel}
            >
                <p className="mb-4 text-sm text-on-surface-muted">{t.logs.exportModal.hint}</p>

                {exportJob !== null && (
                    <p className="mb-4 font-mono text-xs text-on-surface-faint">status: {exportJob.status}</p>
                )}

                {/* Indeterminate progress bar */}
                <div className="mb-4 h-1 overflow-hidden rounded bg-surface-sunken">
                    <div className="h-full w-1/2 animate-pulse rounded bg-accent" aria-hidden="true" />
                </div>

                <div className="flex justify-end gap-2">
                    <Button variant="ghost" size="sm" onClick={cancelExport}>
                        {t.logs.exportModal.cancel}
                    </Button>
                    <Button variant="secondary" size="sm" onClick={() => setExportModalOpen(false)}>
                        {t.logs.exportModal.background}
                    </Button>
                </div>
            </Modal>
        </AppLayout>
    );
}
