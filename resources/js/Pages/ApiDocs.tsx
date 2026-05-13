import 'swagger-ui-react/swagger-ui.css';
import { type ReactElement, useState } from 'react';
import SwaggerUI from 'swagger-ui-react';

import { ApiDocsRequestError, createApiDocsClient } from '@/api/apiDocs';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import { DownloadIcon, ExternalIcon } from '@/Components/icons';
import { useLocale, useT } from '@/hooks/useT';
import AppLayout from '@/Layouts/AppLayout';

type DownloadState =
    | { kind: 'idle' }
    | { kind: 'requesting' }
    | { kind: 'polling'; attempts: number }
    | { kind: 'failed'; message: string };

const POLL_INTERVAL_MS = 1000;
const POLL_MAX_ATTEMPTS = 30;

export default function ApiDocs(): ReactElement {
    const t = useT();
    const locale = useLocale();
    const [downloadState, setDownloadState] = useState<DownloadState>({ kind: 'idle' });

    async function handleDownload(): Promise<void> {
        setDownloadState({ kind: 'requesting' });
        const client = createApiDocsClient();

        try {
            const job = await client.requestPdfRender(locale);
            setDownloadState({ kind: 'polling', attempts: 0 });

            for (let i = 0; i < POLL_MAX_ATTEMPTS; i++) {
                await new Promise<void>((resolve) => {
                    window.setTimeout(resolve, POLL_INTERVAL_MS);
                });

                const result = await client.pollPdfStatus(job.export_id);

                if ('kind' in result && result.kind === 'binary') {
                    triggerBrowserDownload(result.blob, 'webte2-api-docs.pdf');
                    setDownloadState({ kind: 'idle' });
                    return;
                }
                if ('status' in result && result.status === 'failed') {
                    setDownloadState({ kind: 'failed', message: t.apiDocs.renderFailed });
                    return;
                }
                setDownloadState({ kind: 'polling', attempts: i + 1 });
            }

            setDownloadState({ kind: 'failed', message: t.apiDocs.timeout });
        } catch (e) {
            const message =
                e instanceof ApiDocsRequestError
                    ? `${t.apiDocs.renderFailed} (${e.httpStatus.toString()})`
                    : e instanceof Error
                      ? e.message
                      : t.apiDocs.unknownError;
            setDownloadState({ kind: 'failed', message });
        }
    }

    const isBusy = downloadState.kind === 'requesting' || downloadState.kind === 'polling';

    function downloadLabel(): string {
        if (downloadState.kind === 'requesting') return t.apiDocs.queueing;
        if (downloadState.kind === 'polling') return t.apiDocs.generating;
        return t.apiDocs.downloadPdf;
    }

    return (
        <AppLayout title={t.apiDocs.title}>
            <div className="flex flex-col gap-6">
                {/* Page title strip */}
                <div className="flex items-start justify-between gap-6">
                    <div className="min-w-0">
                        <h1 className="text-[28px] font-semibold leading-tight tracking-[-0.025em] text-on-surface">
                            {t.apiDocs.title}
                        </h1>
                        <p className="mt-1 text-[15px] text-on-surface-muted">{t.apiDocs.subtitle}</p>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        <Badge variant="accent" square>
                            {t.apiDocs.versionBadge}
                        </Badge>
                        <Button
                            variant="primary"
                            size="lg"
                            loading={isBusy}
                            leadingIcon={!isBusy ? <DownloadIcon /> : undefined}
                            onClick={() => {
                                void handleDownload();
                            }}
                            disabled={isBusy}
                        >
                            {downloadLabel()}
                        </Button>
                    </div>
                </div>

                {/* Info strip */}
                <div className="flex flex-wrap items-center gap-x-5 gap-y-1.5">
                    <a
                        href="/api/openapi.json"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 font-mono text-[12px] text-on-surface-muted transition-colors hover:text-on-surface"
                    >
                        <ExternalIcon />
                        {t.apiDocs.specLink}
                    </a>
                    <span className="font-mono text-[12px] text-on-surface-muted">{t.apiDocs.tryItHint}</span>
                    <span className="font-mono text-[12px] text-on-surface-muted">{t.apiDocs.apiKeyNotice}</span>
                </div>

                {/* Error alert */}
                {downloadState.kind === 'failed' && (
                    <div
                        role="alert"
                        className="rounded-md border border-error bg-error/10 px-4 py-3 text-sm text-error"
                    >
                        {downloadState.message}
                    </div>
                )}

                {/* Swagger UI — wrapped in a card so its white background respects the surrounding design */}
                <div className="overflow-hidden rounded-md border border-border bg-surface-raised">
                    <SwaggerUI url="/api/openapi.json" />
                </div>
            </div>
        </AppLayout>
    );
}

function triggerBrowserDownload(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}
