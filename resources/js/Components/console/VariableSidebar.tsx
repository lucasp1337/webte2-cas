import { useT } from '@/hooks/useT';

export type VariableSidebarProps = {
    variables: string[];
};

export default function VariableSidebar({ variables }: VariableSidebarProps) {
    const t = useT();

    return (
        <aside
            aria-label={t.console.variablesTitle}
            className="rounded-md border border-border bg-surface-raised p-4"
            data-testid="variable-sidebar"
        >
            <h2 className="mb-3 text-sm font-semibold text-on-surface">{t.console.variablesTitle}</h2>
            {variables.length === 0 ? (
                <p className="text-sm text-on-surface-muted">{t.console.variablesEmpty}</p>
            ) : (
                <ul className="flex flex-col gap-1 font-mono text-sm text-on-surface">
                    {variables.map((name) => (
                        <li key={name} className="break-all">
                            {name}
                        </li>
                    ))}
                </ul>
            )}
        </aside>
    );
}
