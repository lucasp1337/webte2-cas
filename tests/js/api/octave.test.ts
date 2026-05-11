import type { Mock } from 'vitest';

import { createOctaveClient, parseExecutionPayload, parseWhoOutput, parseWhosOutput } from '@/api/octave';

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

describe('parseWhosOutput', () => {
    it('returns an empty list for empty stdout', () => {
        expect(parseWhosOutput('')).toEqual([]);
    });

    it('returns an empty list when only the header is present', () => {
        const stdout = 'Variables visible from the current scope:\n\nvariables in scope: top scope\n';
        expect(parseWhosOutput(stdout)).toEqual([]);
    });

    it('parses a typical whos table with multiple variables', () => {
        const stdout = [
            'Variables visible from the current scope:',
            '',
            'variables in scope: top scope',
            '',
            '  Attr Name        Size                     Bytes  Class',
            '  ==== ====        ====                     =====  =====',
            '       A           3x3                         72  double',
            '       b           3x1                         24  double',
            '       x           1x1                          8  double',
            '',
            'Total is 15 elements using 120 bytes',
        ].join('\n');

        const result = parseWhosOutput(stdout);

        expect(result).toHaveLength(3);
        expect(result[0]).toMatchObject({ name: 'A', size: '3x3', class: 'double' });
        expect(result[1]).toMatchObject({ name: 'b', size: '3x1', class: 'double' });
        expect(result[2]).toMatchObject({ name: 'x', size: '1x1', class: 'double' });
    });

    it('captures attribute flags when present', () => {
        const stdout =
            '  Attr Name        Size                     Bytes  Class\n' +
            '  ==== ====        ====                     =====  =====\n' +
            '  c    z           1x1                         16  complex\n';

        const result = parseWhosOutput(stdout);

        expect(result).toHaveLength(1);
        expect(result[0]).toMatchObject({ name: 'z', size: '1x1', class: 'complex', attr: 'c' });
    });

    it('does not set attr when the attr column is empty', () => {
        const stdout =
            '  Attr Name        Size                     Bytes  Class\n' +
            '  ==== ====        ====                     =====  =====\n' +
            '       a           1x1                          8  double\n';

        const result = parseWhosOutput(stdout);

        expect(result[0]).not.toHaveProperty('attr');
    });

    it('ignores lines that do not match the row pattern', () => {
        const stdout = 'Total is 3 elements using 24 bytes\nsome garbage\n';
        expect(parseWhosOutput(stdout)).toEqual([]);
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

    it('classifies a network error as network_error with status 0', async () => {
        const fetcher = makeFetcher(() => Promise.reject(new Error('boom')));

        const client = createOctaveClient({ apiKey: 'k', fetcher });
        const outcome = await client.runCommand('1+1');

        expect(outcome.status).toBe('network_error');
        expect(outcome.httpStatus).toBe(0);
        expect(outcome.payload).toBeNull();
    });

    it('classifies 401 as unauthorized', async () => {
        const fetcher = makeFetcher(() =>
            Promise.resolve(new Response(JSON.stringify({ error: 'unauthorized' }), { status: 401 })),
        );
        const client = createOctaveClient({ apiKey: '', fetcher });
        const outcome = await client.runCommand('1+1');
        expect(outcome.status).toBe('unauthorized');
    });

    it('classifies 429 as rate_limited', async () => {
        const fetcher = makeFetcher(() =>
            Promise.resolve(new Response(JSON.stringify({ error: 'rate_limited' }), { status: 429 })),
        );
        const client = createOctaveClient({ apiKey: 'k', fetcher });
        const outcome = await client.runCommand('1+1');
        expect(outcome.status).toBe('rate_limited');
    });

    it('classifies 503 as bridge_unavailable', async () => {
        const fetcher = makeFetcher(() =>
            Promise.resolve(new Response(JSON.stringify({ error: 'bridge_unavailable' }), { status: 503 })),
        );
        const client = createOctaveClient({ apiKey: 'k', fetcher });
        const outcome = await client.runCommand('1+1');
        expect(outcome.status).toBe('bridge_unavailable');
    });

    it('clearSession returns true on 204', async () => {
        const fetcher = makeFetcher(() => Promise.resolve(new Response(null, { status: 204 })));

        const client = createOctaveClient({ apiKey: 'k', fetcher });
        await expect(client.clearSession()).resolves.toBe(true);
    });
});
