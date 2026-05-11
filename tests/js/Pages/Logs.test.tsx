import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { AnchorHTMLAttributes, ReactNode } from 'react';

import type { CsvPollResult, CsvExportResult, LogsClient, LogsPage as LogsPageData } from '@/api/logs';

// Inertia stubs — not running inside the real app router.
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'en' }, url: '/en/logs' }),
    router: { visit: () => undefined },
    Head: ({ title }: { title?: string }) => (title === undefined ? null : <title>{title}</title>),
    Link: ({
        href,
        children,
        ...rest
    }: {
        href: string;
        children: ReactNode;
    } & AnchorHTMLAttributes<HTMLAnchorElement>) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

import Logs from '@/Pages/Logs';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeLogsPage(overrides: Partial<LogsPageData> = {}): LogsPageData {
    return {
        data: [],
        links: { first: null, last: null, prev: null, next: null },
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
        ...overrides,
    };
}

function makeLogRow(id: string, status = 200) {
    return {
        request_id: id,
        method: 'POST',
        route: 'v1.octave.exec',
        path: '/api/v1/octave/exec',
        status,
        duration_ms: 38,
        api_key_prefix: 'ak_d8',
        created_at: new Date().toISOString(),
    };
}

function makeClient(overrides: Partial<LogsClient> = {}): LogsClient {
    const defaultClient: LogsClient = {
        listLogs: vi.fn((): Promise<LogsPageData> => Promise.resolve(makeLogsPage())),
        requestCsvExport: vi.fn((): Promise<CsvExportResult> => Promise.resolve({ kind: 'direct' })),
        pollCsvExport: vi.fn((): Promise<CsvPollResult> => Promise.resolve({ job_id: 'exp-1', status: 'queued' })),
    };
    return { ...defaultClient, ...overrides };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('Logs page', () => {
    it('renders the empty state when the API returns zero rows', async () => {
        const client = makeClient({
            listLogs: vi.fn((): Promise<LogsPageData> => Promise.resolve(makeLogsPage())),
        });

        render(<Logs apiKey="key" client={client} />);

        await waitFor(() => {
            expect(screen.getByTestId('empty-state')).toBeInTheDocument();
        });
    });

    it('renders rows from the API', async () => {
        const client = makeClient({
            listLogs: vi.fn(
                (): Promise<LogsPageData> =>
                    Promise.resolve(
                        makeLogsPage({
                            data: [makeLogRow('rid-1'), makeLogRow('rid-2', 404)],
                            meta: { current_page: 1, last_page: 1, per_page: 20, total: 2, from: 1, to: 2 },
                        }),
                    ),
            ),
        });

        render(<Logs apiKey="key" client={client} />);

        // Both status codes should appear somewhere in the table
        await waitFor(() => {
            expect(screen.getAllByText('200').length).toBeGreaterThan(0);
            expect(screen.getAllByText('404').length).toBeGreaterThan(0);
        });
    });

    it('filter changes trigger a refetch', async () => {
        const user = userEvent.setup();
        const listLogs = vi.fn((): Promise<LogsPageData> => Promise.resolve(makeLogsPage()));
        const client = makeClient({ listLogs });

        render(<Logs apiKey="key" client={client} />);

        // Wait for initial fetch
        await waitFor(() => expect(listLogs).toHaveBeenCalledTimes(1));

        // Change the status filter
        await user.selectOptions(screen.getByTestId('status-filter'), '2xx');

        await waitFor(() => expect(listLogs).toHaveBeenCalledTimes(2));
    });

    it('pagination next button advances the page and triggers a refetch', async () => {
        const user = userEvent.setup();
        const listLogs = vi.fn(
            (): Promise<LogsPageData> =>
                Promise.resolve(
                    makeLogsPage({
                        data: [makeLogRow('rid-1')],
                        meta: { current_page: 1, last_page: 3, per_page: 20, total: 60, from: 1, to: 20 },
                    }),
                ),
        );
        const client = makeClient({ listLogs });

        render(<Logs apiKey="key" client={client} />);

        await waitFor(() => expect(listLogs).toHaveBeenCalledTimes(1));

        const nextButton = screen.getByTestId('next-page-button');
        await user.click(nextButton);

        await waitFor(() => expect(listLogs).toHaveBeenCalledTimes(2));
        // Second call should request page 2
        const secondCallArgs = listLogs.mock.calls.at(1) as unknown[] | undefined;
        const firstArg = secondCallArgs?.[0] as { page?: number } | undefined;
        expect(firstArg?.page).toBe(2);
    });

    it('Export CSV button calls requestCsvExport', async () => {
        const user = userEvent.setup();
        const requestCsvExport = vi.fn((): Promise<CsvExportResult> => Promise.resolve({ kind: 'direct' }));
        const client = makeClient({ requestCsvExport });

        render(<Logs apiKey="key" client={client} />);

        const exportBtn = screen.getByTestId('export-csv-button');
        await user.click(exportBtn);

        expect(requestCsvExport).toHaveBeenCalledTimes(1);
    });

    it('shows the error state and retry button on fetch failure', async () => {
        const user = userEvent.setup();
        let callCount = 0;
        const listLogs = vi.fn((): Promise<LogsPageData> => {
            callCount += 1;
            if (callCount === 1) {
                return Promise.reject(new Error('503 Service Unavailable'));
            }
            return Promise.resolve(makeLogsPage());
        });
        const client = makeClient({ listLogs });

        render(<Logs apiKey="key" client={client} />);

        await waitFor(() => {
            expect(screen.getByTestId('error-state')).toBeInTheDocument();
        });

        const retryButton = within(screen.getByTestId('error-state')).getByTestId('retry-button');
        await user.click(retryButton);

        await waitFor(() => expect(listLogs).toHaveBeenCalledTimes(2));
    });
});
