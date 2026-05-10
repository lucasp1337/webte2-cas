import 'swagger-ui-react/swagger-ui.css';
import { type ReactElement, useState } from 'react';
import SwaggerUI from 'swagger-ui-react';

import { ApiDocsRequestError, createApiDocsClient } from '@/api/apiDocs';
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

    return (
        <AppLayout title={t.apiDocs.title}>
            <div className="flex flex-col gap-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-on-surface">{t.apiDocs.title}</h1>
                        <p className="mt-1 text-on-surface-muted">{t.apiDocs.subtitle}</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => {
                            void handleDownload();
                        }}
                        disabled={isBusy}
                        className="shrink-0 rounded-md bg-primary px-4 py-2 text-sm font-medium text-on-primary disabled:opacity-50"
                    >
                        {downloadState.kind === 'requesting' && t.apiDocs.queueing}
                        {downloadState.kind === 'polling' && t.apiDocs.generating}
                        {downloadState.kind !== 'requesting' &&
                            downloadState.kind !== 'polling' &&
                            t.apiDocs.downloadPdf}
                    </button>
                </div>

                {downloadState.kind === 'failed' && (
                    <div
                        role="alert"
                        className="rounded-md border border-error bg-error/10 px-4 py-3 text-sm text-error"
                    >
                        {downloadState.message}
                    </div>
                )}

                <div className="rounded-md border border-border bg-surface-raised">
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
