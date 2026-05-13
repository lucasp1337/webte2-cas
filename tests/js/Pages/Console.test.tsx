import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { AnchorHTMLAttributes, ReactNode } from 'react';

import type { OctaveClient, OctaveExecutionOutcome, OctaveExecutionPayload, WorkspaceVariable } from '@/api/octave';

// Inertia is not running inside jsdom; supply minimal stubs for the bits the
// page (and its layout) reach for.
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'en' }, url: '/en/console' }),
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

// CodeMirror is heavy and pulls a real DOM-driven editor that fights jsdom.
// Replace with a textarea so the tests can drive `onChange` directly.
vi.mock('@/Components/console/OctaveEditor', () => ({
    default: ({
        value,
        onChange,
        ariaLabel,
    }: {
        value: string;
        onChange: (next: string) => void;
        ariaLabel: string;
    }) => (
        <textarea
            aria-label={ariaLabel}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            data-testid="octave-editor"
        />
    ),
}));

import Console from '@/Pages/Console';

function makePayload(overrides: Partial<OctaveExecutionPayload> = {}): OctaveExecutionPayload {
    return {
        request_id: 'req-1',
        stdout: '',
        stderr: '',
        exit_code: 0,
        duration_ms: 5,
        rejection_reason: null,
        ...overrides,
    };
}

function makeClient(overrides: Partial<OctaveClient> = {}): OctaveClient {
    const defaultClient: OctaveClient = {
        runCommand: vi.fn(
            (): Promise<OctaveExecutionOutcome> =>
                Promise.resolve({
                    status: 'success',
                    payload: makePayload({ stdout: 'ans = 2\n' }),
                    httpStatus: 200,
                }),
        ),
        clearSession: vi.fn((): Promise<boolean> => Promise.resolve(true)),
        inspectWorkspace: vi.fn((): Promise<WorkspaceVariable[]> => Promise.resolve([])),
    };

    return { ...defaultClient, ...overrides };
}

describe('Console', () => {
    it('renders the editor and the run button', () => {
        render(<Console apiKey="key" client={makeClient()} />);

        expect(screen.getByTestId('octave-editor')).toBeInTheDocument();
        expect(screen.getByTestId('run-button')).toBeInTheDocument();
    });

    it('runs the command on click and shows stdout in the output panel', async () => {
        const user = userEvent.setup();
        const client = makeClient({
            runCommand: vi.fn(
                (): Promise<OctaveExecutionOutcome> =>
                    Promise.resolve({
                        status: 'success',
                        payload: makePayload({ stdout: 'ans = 2\n', duration_ms: 12 }),
                        httpStatus: 200,
                    }),
            ),
            inspectWorkspace: vi.fn(
                (): Promise<WorkspaceVariable[]> =>
                    Promise.resolve([{ name: 'ans', size: '1x1', class: 'double', value: '2' }]),
            ),
        });

        render(<Console apiKey="key" client={client} />);

        await user.type(screen.getByTestId('octave-editor'), '1+1');
        await user.click(screen.getByTestId('run-button'));

        expect(client.runCommand).toHaveBeenCalledWith('1+1');
        await waitFor(() => {
            expect(screen.getByTestId('output-stdout')).toHaveTextContent('ans = 2');
        });
        // Variable sidebar refreshes after a successful run.
        await waitFor(() => {
            expect(client.inspectWorkspace).toHaveBeenCalled();
        });
        expect(screen.getByTestId('variable-sidebar')).toHaveTextContent('ans');
    });

    it('runs the command on Ctrl+Enter from the editor', async () => {
        const user = userEvent.setup();
        const client = makeClient();

        render(<Console apiKey="key" client={client} />);

        const editor = screen.getByTestId('octave-editor');
        await user.type(editor, 'disp(1)');
        // Editor stays focused; the document-level hotkey listener catches
        // Ctrl+Enter via bubbling.
        await user.keyboard('{Control>}{Enter}{/Control}');

        await waitFor(() => {
            expect(client.runCommand).toHaveBeenCalledWith('disp(1)');
        });
    });

    it('renders the rejection panel for a 422 response', async () => {
        const user = userEvent.setup();
        const client = makeClient({
            runCommand: vi.fn(
                (): Promise<OctaveExecutionOutcome> =>
                    Promise.resolve({
                        status: 'rejected',
                        payload: makePayload({
                            stderr: "Forbidden token: 'system'",
                            rejection_reason: "Forbidden token: 'system'",
                            exit_code: 422,
                        }),
                        httpStatus: 422,
                    }),
            ),
        });

        render(<Console apiKey="key" client={client} />);

        await user.type(screen.getByTestId('octave-editor'), "system('ls')");
        await user.click(screen.getByTestId('run-button'));

        await waitFor(() => {
            expect(screen.getByTestId('output-error')).toHaveTextContent("Forbidden token: 'system'");
        });
    });

    it('clears the workspace when the clear button is pressed', async () => {
        const user = userEvent.setup();
        const client = makeClient();

        render(<Console apiKey="key" client={client} />);

        await user.type(screen.getByTestId('octave-editor'), '1+1');
        await user.click(screen.getByTestId('run-button'));

        await waitFor(() => {
            expect(screen.getByTestId('output-stdout')).toBeInTheDocument();
        });

        await user.click(screen.getByTestId('clear-button'));

        await waitFor(() => {
            expect(client.clearSession).toHaveBeenCalled();
        });
        // Output panel collapses back to the empty state.
        expect(screen.queryByTestId('output-stdout')).not.toBeInTheDocument();
    });
});
