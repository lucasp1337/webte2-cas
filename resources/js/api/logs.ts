/**
 * Typed client for the `/api/v1/logs` endpoints.
 *
 * - `listLogs` — paginated list with optional filters.
 * - `requestCsvExport` — triggers a sync or async CSV export.
 * - `pollCsvExport` — polls an async export job until done, then downloads.
 */

// ---------------------------------------------------------------------------
// Types that mirror RequestLogResource
// ---------------------------------------------------------------------------

export type LogRow = {
    request_id: string;
    method: string;
    route: string;
    path: string;
    status: number;
    duration_ms: number;
    api_key_prefix: string | null;
    created_at: string;
};

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type PaginationLinks = {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
};

export type LogsPage = {
    data: LogRow[];
    links: PaginationLinks;
    meta: PaginationMeta;
};

export type CsvJobState = {
    job_id: string;
    status: 'queued' | 'running' | 'done' | 'failed';
    poll_url?: string | undefined;
};

export type CsvExportResult = { kind: 'direct' } | { kind: 'async'; job: CsvJobState };

export type CsvPollResult = CsvJobState | { kind: 'binary'; blob: Blob };

// ---------------------------------------------------------------------------
// List filters
// ---------------------------------------------------------------------------

export type ListLogsOptions = {
    page?: number | undefined;
    perPage?: number | undefined;
    /** Raw HTTP status code, e.g. 200, 400, 500 */
    status?: number | undefined;
    /** Named route string, e.g. "v1.octave.exec" */
    route?: string | undefined;
    apiKeyPrefix?: string | undefined;
};

// ---------------------------------------------------------------------------
// Client factory
// ---------------------------------------------------------------------------

export type LogsClientOptions = {
    apiKey: string;
    /** Optional fetch override for tests. */
    fetcher?: typeof fetch;
};

export class LogsRequestError extends Error {
    constructor(
        message: string,
        public readonly httpStatus: number,
    ) {
        super(message);
        this.name = 'LogsRequestError';
    }
}

/** Download a Blob to the user's browser as a file attachment. */
function triggerBlobDownload(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

function isCsvContentType(contentType: string): boolean {
    return contentType.includes('text/csv') || contentType.includes('application/csv');
}

function parseLogRow(value: unknown): LogRow {
    // value is filtered to `Record<string, unknown>` at the call site before
    // being passed here; the cast is safe but TypeScript can't propagate the
    // guard through Array.filter's return type.
    const obj = value as Record<string, unknown>;
    return {
        request_id: typeof obj['request_id'] === 'string' ? obj['request_id'] : '',
        method: typeof obj['method'] === 'string' ? obj['method'] : '',
        route: typeof obj['route'] === 'string' ? obj['route'] : '',
        path: typeof obj['path'] === 'string' ? obj['path'] : '',
        status: typeof obj['status'] === 'number' ? obj['status'] : 0,
        duration_ms: typeof obj['duration_ms'] === 'number' ? obj['duration_ms'] : 0,
        api_key_prefix: typeof obj['api_key_prefix'] === 'string' ? obj['api_key_prefix'] : null,
        created_at: typeof obj['created_at'] === 'string' ? obj['created_at'] : '',
    };
}

function parseLogsPage(body: unknown): LogsPage {
    if (body === null || typeof body !== 'object') {
        return {
            data: [],
            links: { first: null, last: null, prev: null, next: null },
            meta: { current_page: 1, last_page: 1, per_page: 50, total: 0, from: null, to: null },
        };
    }
    // `typeof body !== 'object'` and `body !== null` guards are done above;
    // TypeScript still sees `object` which isn't directly indexable.
    const obj = body as Record<string, unknown>;

    const rawData = Array.isArray(obj['data']) ? obj['data'] : [];
    const data = rawData
        .filter((r): r is Record<string, unknown> => r !== null && typeof r === 'object')
        .map(parseLogRow);

    // Inner objects are narrowed via `typeof x === 'object'` before the cast.
    const rawLinks =
        obj['links'] !== null && typeof obj['links'] === 'object' ? (obj['links'] as Record<string, unknown>) : {};
    const links: PaginationLinks = {
        first: typeof rawLinks['first'] === 'string' ? rawLinks['first'] : null,
        last: typeof rawLinks['last'] === 'string' ? rawLinks['last'] : null,
        prev: typeof rawLinks['prev'] === 'string' ? rawLinks['prev'] : null,
        next: typeof rawLinks['next'] === 'string' ? rawLinks['next'] : null,
    };

    const rawMeta =
        obj['meta'] !== null && typeof obj['meta'] === 'object' ? (obj['meta'] as Record<string, unknown>) : {};
    const meta: PaginationMeta = {
        current_page: typeof rawMeta['current_page'] === 'number' ? rawMeta['current_page'] : 1,
        last_page: typeof rawMeta['last_page'] === 'number' ? rawMeta['last_page'] : 1,
        per_page: typeof rawMeta['per_page'] === 'number' ? rawMeta['per_page'] : 50,
        total: typeof rawMeta['total'] === 'number' ? rawMeta['total'] : 0,
        from: typeof rawMeta['from'] === 'number' ? rawMeta['from'] : null,
        to: typeof rawMeta['to'] === 'number' ? rawMeta['to'] : null,
    };

    return { data, links, meta };
}

function parseCsvJobState(body: unknown): CsvJobState {
    // body comes from `await resp.json()` which is typed `unknown`;
    // we need an indexable shape to probe fields.
    const obj = body as Record<string, unknown>;
    const rawStatus = obj['status'];
    const status: CsvJobState['status'] =
        rawStatus === 'queued' || rawStatus === 'running' || rawStatus === 'done' || rawStatus === 'failed'
            ? rawStatus
            : 'queued';
    return {
        job_id: typeof obj['job_id'] === 'string' ? obj['job_id'] : '',
        status,
        poll_url: typeof obj['poll_url'] === 'string' ? obj['poll_url'] : undefined,
    };
}

export function createLogsClient({ apiKey, fetcher }: LogsClientOptions) {
    const fetchImpl: typeof fetch = fetcher ?? globalThis.fetch.bind(globalThis);

    const AUTH_HEADERS = {
        Accept: 'application/json',
        'X-API-Key': apiKey,
    };

    async function listLogs(opts: ListLogsOptions = {}): Promise<LogsPage> {
        const params = new URLSearchParams();
        if (opts.page !== undefined) params.set('page', String(opts.page));
        if (opts.perPage !== undefined) params.set('per_page', String(opts.perPage));
        if (opts.status !== undefined) params.set('status', String(opts.status));
        if (opts.route !== undefined && opts.route !== '') params.set('route', opts.route);

        const qs = params.toString();
        const url = qs.length > 0 ? `/api/v1/logs?${qs}` : '/api/v1/logs';

        const resp = await fetchImpl(url, {
            method: 'GET',
            credentials: 'include',
            headers: AUTH_HEADERS,
        });

        if (!resp.ok) {
            throw new LogsRequestError(`GET /api/v1/logs returned ${resp.status.toString()}`, resp.status);
        }

        const body: unknown = await resp.json();
        return parseLogsPage(body);
    }

    async function requestCsvExport(): Promise<CsvExportResult> {
        const resp = await fetchImpl('/api/v1/logs/export.csv', {
            method: 'GET',
            credentials: 'include',
            headers: { ...AUTH_HEADERS, Accept: 'text/csv, application/csv, application/json' },
        });

        const contentType = resp.headers.get('content-type') ?? '';

        if (resp.status === 200 && isCsvContentType(contentType)) {
            const blob = await resp.blob();
            triggerBlobDownload(blob, `request-logs-${new Date().toISOString().slice(0, 10)}.csv`);
            return { kind: 'direct' };
        }

        if (resp.status === 202) {
            const body: unknown = await resp.json();
            return { kind: 'async', job: parseCsvJobState(body) };
        }

        throw new LogsRequestError(`export returned ${resp.status.toString()}`, resp.status);
    }

    async function pollCsvExport(jobId: string): Promise<CsvPollResult> {
        const resp = await fetchImpl(`/api/v1/logs/export.csv/${jobId}`, {
            method: 'GET',
            credentials: 'include',
            headers: { ...AUTH_HEADERS, Accept: 'text/csv, application/csv, application/json' },
        });

        const contentType = resp.headers.get('content-type') ?? '';

        if (resp.status === 200 && isCsvContentType(contentType)) {
            const blob = await resp.blob();
            return { kind: 'binary', blob };
        }

        if (resp.status === 202) {
            const body: unknown = await resp.json();
            return parseCsvJobState(body);
        }

        throw new LogsRequestError(`poll returned ${resp.status.toString()}`, resp.status);
    }

    return { listLogs, requestCsvExport, pollCsvExport };
}

export type LogsClient = ReturnType<typeof createLogsClient>;
