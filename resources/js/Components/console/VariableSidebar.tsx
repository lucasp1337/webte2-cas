import type { WorkspaceVariable } from '@/api/octave';
import Badge from '@/Components/ui/Badge';
import Skeleton from '@/Components/ui/Skeleton';
import { useT } from '@/hooks/useT';

export type VariableSidebarProps = {
    variables: WorkspaceVariable[];
    /** When true, renders three skeleton rows (a command is in flight). */
    isLoading?: boolean;
    /** When true, shows "offline" in the footer status chip. */
    bridgeError?: boolean;
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
        <li
            className="flex items-baseline justify-between border-b border-border-subtle font-mono text-[12.5px] last:border-b-0 hover:bg-surface-sunken"
            style={{ padding: '7px 12px' }}
        >
            <span className="text-on-surface">{name}</span>
            <span className="ml-2 shrink-0 text-[11.5px] text-on-surface-muted">
                {isScalar ? (
                    <span className="text-on-surface">{value}</span>
                ) : (
                    <>
                        {formatSize(size)} <span className="text-on-surface-faint">({cls})</span>
                    </>
                )}
            </span>
        </li>
    );
}

export default function VariableSidebar({ variables, isLoading = false, bridgeError = false }: VariableSidebarProps) {
    const t = useT();
    const isEmpty = variables.length === 0 && !isLoading;

    return (
        <aside
            aria-label={t.console.variablesTitle}
            className="sticky top-20 rounded-md border border-border bg-surface-raised"
            data-testid="variable-sidebar"
        >
            {/* Header */}
            <div className="flex items-center justify-between border-b border-border" style={{ padding: '14px 16px' }}>
                <span className="text-[13px] font-semibold text-on-surface">{t.console.variablesTitle}</span>
                <Badge variant="neutral">{variables.length.toString()}</Badge>
            </div>

            {/* Loading skeleton */}
            {isLoading && (
                <div className="p-3.5">
                    <Skeleton className="mb-2.5 h-[11px] w-full" />
                    <Skeleton className="mb-2.5 h-[11px] w-4/5" />
                    <Skeleton className="h-[11px] w-[90%]" />
                </div>
            )}

            {/* Variable rows */}
            {!isLoading && variables.length > 0 && (
                <ul className="flex flex-col">
                    {variables.map((v) => (
                        <WorkspaceRow key={v.name} variable={v} />
                    ))}
                </ul>
            )}

            {/* Empty state */}
            {(isEmpty || bridgeError) && !isLoading && (
                <div className="p-[18px] text-center">
                    <p className="font-mono text-[12px] italic text-on-surface-muted">{t.console.variablesEmpty}</p>
                    <p className="mt-2.5 text-[11.5px] text-on-surface-faint">{t.console.variablesEmptySub}</p>
                </div>
            )}

            {/* Footer strip */}
            <div
                className="flex items-center justify-between border-t border-border font-mono text-[11px] text-on-surface-faint"
                style={{ padding: '10px 16px' }}
            >
                <span>{t.console.workspace.memLabel} · —</span>
                <span>{bridgeError ? t.console.workspace.offline : t.console.workspace.idle}</span>
            </div>
        </aside>
    );
}
