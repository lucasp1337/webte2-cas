import { StreamLanguage } from '@codemirror/language';
import { octave } from '@codemirror/legacy-modes/mode/octave';
import { oneDark } from '@codemirror/theme-one-dark';
import CodeMirror, { type Extension } from '@uiw/react-codemirror';
import { type KeyboardEvent, useMemo } from 'react';

import { useTheme } from '@/hooks/useTheme';

export type OctaveEditorProps = {
    value: string;
    onChange: (next: string) => void;
    onRun: () => void;
    placeholder?: string;
    ariaLabel: string;
    /** When true, editor accepts focus but disallows edits. Defaults to false. */
    readOnly?: boolean;
};

const EDITOR_HEIGHT = '240px';

export default function OctaveEditor({
    value,
    onChange,
    onRun,
    placeholder,
    ariaLabel,
    readOnly = false,
}: OctaveEditorProps) {
    const theme = useTheme();

    const extensions = useMemo<Extension[]>(() => [StreamLanguage.define(octave)], []);

    // The Run hotkey is bound at the document level by `useRunHotkey` in the
    // page, so we don't have to thread CodeMirror keymaps here. We still
    // intercept Ctrl/Cmd+Enter inside the editor to prevent the default
    // newline insertion when the editor itself owns focus.
    function onKeyDown(event: KeyboardEvent<HTMLDivElement>): void {
        if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
            event.preventDefault();
            onRun();
        }
    }

    return (
        <div
            onKeyDown={onKeyDown}
            data-testid="octave-editor"
            className="overflow-hidden rounded-md border border-border bg-surface-raised"
        >
            <CodeMirror
                value={value}
                onChange={onChange}
                theme={theme === 'dark' ? oneDark : 'light'}
                extensions={extensions}
                height={EDITOR_HEIGHT}
                readOnly={readOnly}
                {...(placeholder !== undefined ? { placeholder } : {})}
                aria-label={ariaLabel}
                basicSetup={{
                    lineNumbers: true,
                    highlightActiveLine: true,
                    foldGutter: false,
                }}
            />
        </div>
    );
}
