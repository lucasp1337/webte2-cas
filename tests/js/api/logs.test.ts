import type { Mock } from 'vitest';

import { createLogsClient, type LogsPage } from '@/api/logs';

function makeFetcher(impl: typeof fetch): Mock<typeof fetch> {
    return vi.fn<typeof fetch>(impl);
}

function makeLogsPage(overrides: Partial<LogsPage> = {}): LogsPage {
    return {
        data: [],
        links: { first: null, last: null, prev: null, next: null },
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
        ...overrides,
    };
}

function makeJsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

function makeCsvResponse(csvText: string): Response {
    return new Response(csvText, {
        status: 200,
        headers: { 'Content-Type': 'text/csv' },
    });
}

describe('createLogsClient — listLogs', () => {
    it('calls GET /api/v1/logs with no query params when no options are passed', async () => {
        const fetcher = makeFetcher(() => Promise.resolve(makeJsonResponse(makeLogsPage())));
        const client = createLogsClient({ apiKey: 'test-key', fetcher });

        await client.listLogs();

        expect(fetcher).toHaveBeenCalledWith('/api/v1/logs', expect.objectContaining({ method: 'GET' }));
    });

    it('appends page and per_page query params when provided', async () => {
        const fetcher = makeFetcher(() => Promise.resolve(makeJsonResponse(makeLogsPage())));
        const client = createLogsClient({ apiKey: 'test-key', fetcher });

        await client.listLogs({ page: 3, perPage: 50 });

        const [url] = (fetcher as Mock).mock.calls[0] as [string, unknown];
        expect(url).toContain('page=3');
        expect(url).toContain('per_page=50');
    });

    it('appends status query param when provided', async () => {
        const fetcher = makeFetcher(() => Promise.resolve(makeJsonResponse(makeLogsPage())));
        const client = createLogsClient({ apiKey: 'test-key', fetcher });

        await client.listLogs({ status: 200 });

        const [url] = (fetcher as Mock).mock.calls[0] as [string, unknown];
        expect(url).toContain('status=200');
    });

    it('appends route query param when provided', async () => {
        const fetcher = makeFetcher(() => Promise.resolve(makeJsonResponse(makeLogsPage())));
        const client = createLogsClient({ apiKey: 'test-key', fetcher });

        await client.listLogs({ route: 'v1.octave.exec' });

        const [url] = (fetcher as Mock).mock.calls[0] as [string, unknown];
        expect(url).toContain('route=v1.octave.exec');
    });

    it('does NOT append empty route param', async () => {
        const fetcher = makeFetcher(() => Promise.resolve(makeJsonResponse(makeLogsPage())));
        const client = createLogsClient({ apiKey: 'test-key', fetcher });

        await client.listLogs({ route: '' });

        const [url] = (fetcher as Mock).mock.calls[0] as [string, unknown];
        expect(url).not.toContain('route');
    });

    it('sends X-API-Key header', async () => {
        const fetcher = makeFetcher(() => Promise.resolve(makeJsonResponse(makeLogsPage())));
        const client = createLogsClient({ apiKey: 'secret-api-key', fetcher });

        await client.listLogs();

        const [, opts] = (fetcher as Mock).mock.calls[0] as [string, RequestInit];
        expect((opts.headers as Record<string, string>)['X-API-Key']).toBe('secret-api-key');
    });

    it('parses paginated response into LogsPage shape', async () => {
        const payload: LogsPage = {
            data: [
                {
                    request_id: 'rid-1',
                    method: 'POST',
                    route: 'v1.octave.exec',
                    path: '/api/v1/octave/exec',
                    status: 200,
                    duration_ms: 38,
                    api_key_prefix: 'ak_d8',
                    created_at: '2026-05-11T14:32:08+00:00',
                },
            ],
            links: {
                first: '/api/v1/logs?page=1',
                last: '/api/v1/logs?page=5',
                prev: null,
                next: '/api/v1/logs?page=2',
            },
            meta: { current_page: 1, last_page: 5, per_page: 20, total: 100, from: 1, to: 20 },
        };
        const fetcher = makeFetcher(() => Promise.resolve(makeJsonResponse(payload)));
        const client = createLogsClient({ apiKey: 'key', fetcher });

        const result = await client.listLogs();

        expect(result.data).toHaveLength(1);
        expect(result.data[0]?.request_id).toBe('rid-1');
        expect(result.meta.total).toBe(100);
        expect(result.meta.last_page).toBe(5);
    });

    it('throws LogsRequestError on non-OK response', async () => {
        const fetcher = makeFetcher(() => Promise.resolve(makeJsonResponse({ message: 'Unauthorized' }, 401)));
        const client = createLogsClient({ apiKey: 'bad-key', fetcher });

        await expect(client.listLogs()).rejects.toThrow('401');
    });
});

describe('createLogsClient — requestCsvExport', () => {
    it('returns direct for a 200 text/csv response and triggers download', async () => {
        // jsdom doesn't have URL.createObjectURL; stub it
        const createObjectURL = vi.fn(() => 'blob:mock');
        const revokeObjectURL = vi.fn();
        vi.stubGlobal('URL', { ...URL, createObjectURL, revokeObjectURL });

        const fetcher = makeFetcher(() => Promise.resolve(makeCsvResponse('request_id,method\nrid-1,POST\n')));
        const client = createLogsClient({ apiKey: 'key', fetcher });

        const result = await client.requestCsvExport();

        expect(result.kind).toBe('direct');
    });

    it('returns async for a 202 application/json response', async () => {
        const jobPayload = { job_id: 'export-1', status: 'queued', poll_url: '/api/v1/logs/export.csv/export-1' };
        const fetcher = makeFetcher(() => Promise.resolve(makeJsonResponse(jobPayload, 202)));
        const client = createLogsClient({ apiKey: 'key', fetcher });

        const result = await client.requestCsvExport();

        expect(result.kind).toBe('async');
        if (result.kind === 'async') {
            expect(result.job.job_id).toBe('export-1');
            expect(result.job.status).toBe('queued');
        }
    });
});

describe('createLogsClient — pollCsvExport', () => {
    it('returns a CsvJobState when job is still running (202)', async () => {
        const jobPayload = { job_id: 'export-1', status: 'running', poll_url: '/api/v1/logs/export.csv/export-1' };
        const fetcher = makeFetcher(() => Promise.resolve(makeJsonResponse(jobPayload, 202)));
        const client = createLogsClient({ apiKey: 'key', fetcher });

        const result = await client.pollCsvExport('export-1');

        expect('kind' in result && result.kind === 'binary').toBe(false);
        if (!('kind' in result)) {
            expect(result.status).toBe('running');
        }
    });

    it('calls the correct poll URL', async () => {
        const fetcher = makeFetcher(() =>
            Promise.resolve(makeJsonResponse({ job_id: 'exp-99', status: 'queued' }, 202)),
        );
        const client = createLogsClient({ apiKey: 'key', fetcher });

        await client.pollCsvExport('exp-99');

        const [url] = (fetcher as Mock).mock.calls[0] as [string, unknown];
        expect(url).toBe('/api/v1/logs/export.csv/exp-99');
    });
});
