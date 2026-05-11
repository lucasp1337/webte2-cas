import { useCallback, useMemo, useState } from 'react';

import { createOctaveClient, type OctaveClient, type WorkspaceVariable } from '@/api/octave';
import OctaveEditor from '@/Components/console/OctaveEditor';
import OutputPanel, { type ConsoleEntry } from '@/Components/console/OutputPanel';
import VariableSidebar from '@/Components/console/VariableSidebar';
import Button from '@/Components/ui/Button';
import { useRunHotkey } from '@/hooks/useRunHotkey';
import { useT } from '@/hooks/useT';
import AppLayout from '@/Layouts/AppLayout';

export type ConsoleProps = {
    apiKey: string;
    /** Test-only injection point for the API client. */
    client?: OctaveClient;
};

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

    const octaveClient = useMemo<OctaveClient>(() => client ?? createOctaveClient({ apiKey }), [client, apiKey]);

    const submit = useCallback(async (): Promise<void> => {
        const trimmed = code.trim();
        if (trimmed === '' || isRunning) return;

        setIsRunning(true);
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
            }
        } finally {
            setIsClearing(false);
        }
    }, [isClearing, octaveClient]);

    useRunHotkey(() => {
        void submit();
    }, !isRunning);

    return (
        <AppLayout title={t.console.title}>
            <div className="flex flex-col gap-2">
                <h1 className="text-3xl font-bold text-on-surface">{t.console.title}</h1>
                <p className="text-on-surface-muted">{t.console.subtitle}</p>
            </div>

            <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-[1fr_240px]">
                <div className="flex flex-col gap-4">
                    <OctaveEditor
                        value={code}
                        onChange={setCode}
                        onRun={() => void submit()}
                        placeholder={t.console.editorPlaceholder}
                        ariaLabel={t.console.editorLabel}
                    />

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            onClick={() => void submit()}
                            loading={isRunning}
                            disabled={code.trim() === ''}
                            data-testid="run-button"
                        >
                            {isRunning ? t.console.running : t.console.run}
                        </Button>

                        <Button
                            variant="secondary"
                            onClick={() => void clearSession()}
                            loading={isClearing}
                            data-testid="clear-button"
                        >
                            {isClearing ? t.console.clearing : t.console.clear}
                        </Button>

                        <span className="ml-auto text-xs text-on-surface-muted">{t.console.runShortcutHint}</span>
                    </div>

                    <OutputPanel entries={entries} />
                </div>

                <VariableSidebar variables={variables} />
            </div>
        </AppLayout>
    );
}
