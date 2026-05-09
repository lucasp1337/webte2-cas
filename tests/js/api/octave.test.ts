import type { Mock } from 'vitest';

import { createOctaveClient, parseExecutionPayload, parseWhoOutput } from '@/api/octave';

function makeFetcher(impl: typeof fetch): Mock<typeof fetch> {
    return vi.fn<typeof fetch>(impl);
}

describe('parseExecutionPayload', () => {
    it('returns defaults for non-object input', () => {
        const result = parseExecutionPayload(null);

        expect(result.stdout).toBe('');
        expect(result.exit_code).toBe(-1);
    });

    it('reads the documented fields when present', () => {
        const result = parseExecutionPayload({
            request_id: 'rid',
            stdout: 'hello\n',
            stderr: 'warn',
            exit_code: 0,
            duration_ms: 12,
            rejection_reason: null,
        });

        expect(result.request_id).toBe('rid');
        expect(result.stdout).toBe('hello\n');
        expect(result.duration_ms).toBe(12);
    });
});

describe('parseWhoOutput', () => {
    it('returns an empty list for empty stdout', () => {
        expect(parseWhoOutput('')).toEqual([]);
    });

    it('extracts identifiers from a typical Octave who response', () => {
        const stdout = 'Variables visible from the current scope:\n\na  b  c\n';

        expect(parseWhoOutput(stdout)).toEqual(['a', 'b', 'c']);
    });

    it('deduplicates and skips invalid identifiers', () => {
        const stdout = 'a a 1bad foo\n';

        expect(parseWhoOutput(stdout)).toEqual(['a', 'foo']);
    });
});

describe('createOctaveClient', () => {
    it('posts the command and the API key header', async () => {
        const fetcher = vi.fn<typeof fetch>(() =>
            Promise.resolve(
                new Response(
                    JSON.stringify({
                        request_id: 'rid',
                        stdout: 'ok\n',
                        stderr: '',
                        exit_code: 0,
                        duration_ms: 1,
                        rejection_reason: null,
                    }),
                    { status: 200, headers: { 'Content-Type': 'application/json' } },
                ),
            ),
        );

        const client = createOctaveClient({ apiKey: 'k', fetcher });
        const outcome = await client.runCommand('1+1');

        expect(outcome.status).toBe('success');
        expect(outcome.payload?.stdout).toBe('ok\n');
        expect(fetcher).toHaveBeenCalledOnce();
        const call = fetcher.mock.calls[0]!;
        const init = call[1];
        // Cast: the client always sends headers as a plain Record<string, string>;
        // RequestInit's HeadersInit union includes other shapes we don't use here.
        const headers = init?.headers as Record<string, string>;
        expect(headers['X-API-Key']).toBe('k');
        expect(init?.credentials).toBe('include');
        expect(init?.body).toContain('1+1');
    });

    it('classifies a 422 response as rejected', async () => {
        const fetcher = makeFetcher(() =>
            Promise.resolve(
                new Response(
                    JSON.stringify({
                        request_id: '',
                        stdout: '',
                        stderr: "Forbidden token: 'system'",
                        exit_code: 422,
                        duration_ms: 0,
                        rejection_reason: "Forbidden token: 'system'",
                    }),
                    { status: 422 },
                ),
            ),
        );

        const client = createOctaveClient({ apiKey: 'k', fetcher });
        const outcome = await client.runCommand("system('ls')");

        expect(outcome.status).toBe('rejected');
        expect(outcome.payload?.rejection_reason).toBe("Forbidden token: 'system'");
    });

    it('classifies a 504 response as timeout', async () => {
        const fetcher = makeFetcher(() => Promise.resolve(new Response('{}', { status: 504 })));

        const client = createOctaveClient({ apiKey: 'k', fetcher });
        const outcome = await client.runCommand('while true; end');

        expect(outcome.status).toBe('timeout');
    });

    it('classifies a network error as error with status 0', async () => {
        const fetcher = makeFetcher(() => Promise.reject(new Error('boom')));

        const client = createOctaveClient({ apiKey: 'k', fetcher });
        const outcome = await client.runCommand('1+1');

        expect(outcome.status).toBe('error');
        expect(outcome.httpStatus).toBe(0);
        expect(outcome.payload).toBeNull();
    });

    it('clearSession returns true on 204', async () => {
        const fetcher = makeFetcher(() => Promise.resolve(new Response(null, { status: 204 })));

        const client = createOctaveClient({ apiKey: 'k', fetcher });
        await expect(client.clearSession()).resolves.toBe(true);
    });
});
