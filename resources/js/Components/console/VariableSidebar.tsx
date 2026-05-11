import type { WorkspaceVariable } from '@/api/octave';
import { useT } from '@/hooks/useT';

export type VariableSidebarProps = {
    variables: WorkspaceVariable[];
};

/**
 * Renders the size string with the Unicode multiplication sign so "3x3"
 * becomes "3×3" in the UI.
 */
function formatSize(size: string): string {
    return size.replace(/x/g, '×');
}

type WorkspaceRowProps = {
    variable: WorkspaceVariable;
};

function WorkspaceRow({ variable }: WorkspaceRowProps) {
    const { name, size, class: cls, value } = variable;
    const isScalar = value !== undefined;

    return (
        <li className="flex items-baseline justify-between border-b border-border-subtle px-3 py-1.5 last:border-b-0 hover:bg-surface-sunken">
            <span className="font-mono text-[12.5px] text-on-surface">
                {name}
                {isScalar && <span className="ml-1 text-on-surface">= {value}</span>}
            </span>
            {!isScalar && (
                <span className="ml-2 shrink-0 font-mono text-[11px] text-on-surface-muted">
                    [{formatSize(size)} {cls}]
                </span>
            )}
        </li>
    );
}

export default function VariableSidebar({ variables }: VariableSidebarProps) {
    const t = useT();

    return (
        <aside
            aria-label={t.console.variablesTitle}
            className="rounded-md border border-border bg-surface-raised"
            data-testid="variable-sidebar"
        >
            <div className="flex items-center justify-between px-4 py-3">
                <h2 className="text-sm font-semibold text-on-surface">{t.console.variablesTitle}</h2>
                {variables.length > 0 && (
                    <span className="rounded-full bg-surface-sunken px-2 py-0.5 font-mono text-[11px] text-on-surface-muted">
                        {variables.length}
                    </span>
                )}
            </div>
            {variables.length === 0 ? (
                <p className="px-4 pb-4 text-sm text-on-surface-muted">{t.console.variablesEmpty}</p>
            ) : (
                <ul className="flex flex-col">
                    {variables.map((v) => (
                        <WorkspaceRow key={v.name} variable={v} />
                    ))}
                </ul>
            )}
        </aside>
    );
}
