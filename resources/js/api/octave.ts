/**
 * Thin fetch wrapper for the `/api/v1/octave/*` endpoints. Adds the
 * `X-API-Key` header for external auth and `credentials: 'include'` so the
 * Laravel session cookie persists across submissions for the per-browser
 * console session id.
 */

export type OctaveExecutionPayload = {
    request_id: string;
    stdout: string;
    stderr: string;
    exit_code: number;
    duration_ms: number;
    rejection_reason: string | null;
};

export type OctaveExecutionStatus = 'success' | 'rejected' | 'timeout' | 'error';

export type OctaveExecutionOutcome = {
    status: OctaveExecutionStatus;
    payload: OctaveExecutionPayload | null;
    httpStatus: number;
};

const EXEC_PATH = '/api/v1/octave/exec';
const SESSION_PATH = '/api/v1/octave/session';

/** Status code helpers — kept close to the resource for self-documentation. */
const HTTP_OK = 200;
const HTTP_UNPROCESSABLE = 422;
const HTTP_GATEWAY_TIMEOUT = 504;

export type OctaveClientOptions = {
    apiKey: string;
    /** Optional fetch override for tests. */
    fetcher?: typeof fetch;
};

function defaultPayload(): OctaveExecutionPayload {
    return {
        request_id: '',
        stdout: '',
        stderr: '',
        exit_code: -1,
        duration_ms: 0,
        rejection_reason: null,
    };
}

function classifyStatus(httpStatus: number): OctaveExecutionStatus {
    if (httpStatus === HTTP_OK) return 'success';
    if (httpStatus === HTTP_UNPROCESSABLE) return 'rejected';
    if (httpStatus === HTTP_GATEWAY_TIMEOUT) return 'timeout';
    return 'error';
}

export function createOctaveClient({ apiKey, fetcher }: OctaveClientOptions) {
    const fetchImpl: typeof fetch = fetcher ?? globalThis.fetch.bind(globalThis);

    async function runCommand(command: string): Promise<OctaveExecutionOutcome> {
        let response: Response;
        try {
            response = await fetchImpl(EXEC_PATH, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-API-Key': apiKey,
                },
                body: JSON.stringify({ command }),
            });
        } catch {
            return { status: 'error', payload: null, httpStatus: 0 };
        }

        let body: OctaveExecutionPayload | null;
        try {
            const json: unknown = await response.json();
            body = parseExecutionPayload(json);
        } catch {
            body = null;
        }

        return {
            status: classifyStatus(response.status),
            payload: body,
            httpStatus: response.status,
        };
    }

    async function clearSession(): Promise<boolean> {
        try {
            const response = await fetchImpl(SESSION_PATH, {
                method: 'DELETE',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'X-API-Key': apiKey,
                },
            });
            return response.status === 204;
        } catch {
            return false;
        }
    }

    async function listVariables(): Promise<string[]> {
        const outcome = await runCommand('who');
        if (outcome.status !== 'success' || outcome.payload === null) {
            return [];
        }
        return parseWhoOutput(outcome.payload.stdout);
    }

    return { runCommand, clearSession, listVariables };
}

export type OctaveClient = ReturnType<typeof createOctaveClient>;

/**
 * Parses a JSON value into our payload shape. Returns the default shape if
 * the body is malformed so the caller can still render a meaningful row.
 */
export function parseExecutionPayload(value: unknown): OctaveExecutionPayload {
    if (value === null || typeof value !== 'object') {
        return defaultPayload();
    }
    // value passed the object guard above; widen to indexable shape so we
    // can probe individual fields without sprinkling `in` checks.
    const obj = value as Record<string, unknown>;
    return {
        request_id: typeof obj.request_id === 'string' ? obj.request_id : '',
        stdout: typeof obj.stdout === 'string' ? obj.stdout : '',
        stderr: typeof obj.stderr === 'string' ? obj.stderr : '',
        exit_code: typeof obj.exit_code === 'number' ? obj.exit_code : -1,
        duration_ms: typeof obj.duration_ms === 'number' ? obj.duration_ms : 0,
        rejection_reason: typeof obj.rejection_reason === 'string' ? obj.rejection_reason : null,
    };
}

/**
 * Parses the stdout from `who` into a list of variable names. Octave's `who`
 * prints either nothing (empty workspace) or a `Variables visible from the current scope:`
 * header followed by space/tab-separated names. The implementation tolerates
 * either format and unknown extra whitespace.
 */
export function parseWhoOutput(stdout: string): string[] {
    const lines = stdout.split(/\r?\n/);
    const names: string[] = [];
    for (const line of lines) {
        const trimmed = line.trim();
        if (trimmed === '' || /[:=]/.test(trimmed)) {
            // header line ("Variables ...:") or assignment echo — ignore
            continue;
        }
        for (const name of trimmed.split(/\s+/)) {
            if (/^[A-Za-z_][A-Za-z0-9_]*$/.test(name)) {
                names.push(name);
            }
        }
    }
    // de-duplicate while preserving order
    return Array.from(new Set(names));
}
