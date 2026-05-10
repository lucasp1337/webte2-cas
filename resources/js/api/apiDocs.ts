import { z } from 'zod';

const queuedJobSchema = z.object({
    export_id: z.string(),
    status: z.union([z.literal('queued'), z.literal('running'), z.literal('done'), z.literal('failed')]),
    poll_url: z.string().optional(),
});

export type ApiDocsJobState = z.infer<typeof queuedJobSchema>;

export type ApiDocsClientOptions = {
    apiKey: string;
    /** Optional fetch override for tests. */
    fetcher?: typeof fetch;
};

export class ApiDocsRequestError extends Error {
    constructor(
        message: string,
        public readonly httpStatus: number,
    ) {
        super(message);
        this.name = 'ApiDocsRequestError';
    }
}

export function createApiDocsClient({ apiKey, fetcher }: ApiDocsClientOptions) {
    const fetchImpl: typeof fetch = fetcher ?? globalThis.fetch.bind(globalThis);

    async function requestPdfRender(locale: 'sk' | 'en'): Promise<ApiDocsJobState> {
        const resp = await fetchImpl(`/api/v1/api-docs/pdf?locale=${locale}`, {
            method: 'POST',
            credentials: 'include',
            headers: { Accept: 'application/json', 'X-API-Key': apiKey, 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
        });
        if (resp.status !== 202)
            throw new ApiDocsRequestError(`unexpected status ${resp.status.toString()}`, resp.status);
        return queuedJobSchema.parse(await resp.json());
    }

    async function pollPdfStatus(exportId: string): Promise<ApiDocsJobState | { kind: 'binary'; blob: Blob }> {
        const resp = await fetchImpl(`/api/v1/api-docs/pdf/${exportId}`, {
            method: 'GET',
            credentials: 'include',
            headers: { 'X-API-Key': apiKey },
        });
        const contentType = resp.headers.get('content-type') ?? '';
        if (resp.status === 200 && contentType.includes('application/pdf')) {
            return { kind: 'binary', blob: await resp.blob() };
        }
        if (resp.status === 202) {
            return queuedJobSchema.parse(await resp.json());
        }
        throw new ApiDocsRequestError(`poll returned ${resp.status.toString()}`, resp.status);
    }

    return { requestPdfRender, pollPdfStatus };
}

export type ApiDocsClient = ReturnType<typeof createApiDocsClient>;
