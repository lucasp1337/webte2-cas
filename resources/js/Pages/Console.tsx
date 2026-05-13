import { useCallback, useMemo, useState } from 'react';

import { createOctaveClient, type OctaveClient, type WorkspaceVariable } from '@/api/octave';
import OctaveEditor from '@/Components/console/OctaveEditor';
import OutputPanel, { type ConsoleEntry } from '@/Components/console/OutputPanel';
import VariableSidebar from '@/Components/console/VariableSidebar';
import { PlayIcon } from '@/Components/icons';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import { useRunHotkey } from '@/hooks/useRunHotkey';
import { useT } from '@/hooks/useT';
import AppLayout from '@/Layouts/AppLayout';

export type ConsoleProps = {
    apiKey: string;
    /** Test-only injection point for the API client. */
    client?: OctaveClient;
};

const EXAMPLE_SNIPPET = 'a = 1+1; a+2';

function makeEntryId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return `entry-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
}

export default function Console({ apiKey, client }: ConsoleProps) {
    const t = useT();
    const [code, setCode] = useState<string>('');
    const [entries, setEntries] = useState<ConsoleEntry[]>([]);
    const [variables, setVariables] = useState<WorkspaceVariable[]>([]);
    const [isRunning, setIsRunning] = useState<boolean>(false);
    const [isClearing, setIsClearing] = useState<boolean>(false);
    const [bridgeError, setBridgeError] = useState<boolean>(false);

    const octaveClient = useMemo<OctaveClient>(() => client ?? createOctaveClient({ apiKey }), [client, apiKey]);

    const submit = useCallback(async (): Promise<void> => {
        const trimmed = code.trim();
        if (trimmed === '' || isRunning) return;

        setIsRunning(true);
        setBridgeError(false);
        try {
            const outcome = await octaveClient.runCommand(trimmed);
            const entry: ConsoleEntry = {
                id: makeEntryId(),
                command: trimmed,
                status: outcome.status,
                payload: outcome.payload,
            };
            setEntries((prev) => [...prev, entry]);
            if (outcome.status === 'success') {
                const names = await octaveClient.inspectWorkspace();
                setVariables(names);
                setCode('');
            }
            if (outcome.status === 'bridge_unavailable') {
                setBridgeError(true);
            }
        } catch {
            setBridgeError(true);
        } finally {
            setIsRunning(false);
        }
    }, [code, isRunning, octaveClient]);

    const clearSession = useCallback(async (): Promise<void> => {
        if (isClearing) return;
        setIsClearing(true);
        try {
            const ok = await octaveClient.clearSession();
            if (ok) {
                setEntries([]);
                setVariables([]);
                setCode('');
                setBridgeError(false);
            }
        } finally {
            setIsClearing(false);
        }
    }, [isClearing, octaveClient]);

    const insertExample = useCallback((): void => {
        setCode(EXAMPLE_SNIPPET);
    }, []);

    useRunHotkey(() => {
        void submit();
    }, !isRunning);

    return (
        <AppLayout title={t.console.title}>
            <div className="flex flex-col gap-2">
                <h1 className="text-3xl font-bold text-on-surface">{t.console.title}</h1>
                <p className="text-on-surface-muted">{t.console.subtitle}</p>
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
                {/* ── Left column ────────────────────────────────────────── */}
                <div className="min-w-0 flex flex-col gap-4">
                    {/* Editor eyebrow strip */}
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <span className="font-mono text-[11px] uppercase tracking-[0.08em] text-on-surface-muted">
                                {t.console.editorLabel}
                            </span>
                            <Badge variant="accent" square>
                                <span className="font-mono">octave</span>
                            </Badge>
                        </div>
                        <span className="font-mono text-[11px] text-on-surface-faint">
                            {t.console.sessionLabel}
                            <span className="ml-1 text-on-surface-muted">·</span>
                            <span className="ml-1 text-on-surface-muted">—</span>
                        </span>
                    </div>

                    <OctaveEditor
                        value={code}
                        onChange={setCode}
                        onRun={() => void submit()}
                        placeholder={t.console.editorPlaceholder}
                        ariaLabel={t.console.editorLabel}
                    />

                    {/* Run / Clear row */}
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex gap-2">
                            <Button
                                onClick={() => void submit()}
                                loading={isRunning}
                                disabled={code.trim() === ''}
                                leadingIcon={!isRunning ? <PlayIcon size={11} /> : undefined}
                                data-testid="run-button"
                            >
                                {isRunning ? t.console.running : t.console.run}
                            </Button>

                            <Button
                                variant="ghost"
                                onClick={() => void clearSession()}
                                loading={isClearing}
                                data-testid="clear-button"
                            >
                                {isClearing ? t.console.clearing : t.console.clear}
                            </Button>
                        </div>

                        <span className="text-[12px] text-on-surface-muted">
                            <KbdHint keys={['⌃', '↵']} /> {t.console.runShortcutHint}
                        </span>
                    </div>

                    <OutputPanel
                        entries={entries}
                        isRunning={isRunning}
                        bridgeError={bridgeError}
                        onRetry={() => void submit()}
                        onInsertExample={insertExample}
                    />
                </div>

                {/* ── Right column — workspace sidebar ────────────────── */}
                <VariableSidebar
                    variables={variables}
                    isLoading={isRunning && variables.length === 0}
                    bridgeError={bridgeError}
                />
            </div>
        </AppLayout>
    );
}

type KbdHintProps = {
    keys: string[];
};

function KbdHint({ keys }: KbdHintProps) {
    return (
        <>
            {keys.map((k, i) => (
                <span
                    key={i}
                    className="rounded-[3px] border border-b-2 border-border bg-surface-sunken px-[5px] py-px font-mono text-[11px] text-on-surface-muted"
                >
                    {k}
                </span>
            ))}
        </>
    );
}
