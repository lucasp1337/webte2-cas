import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/hooks/useT';

export default function Console() {
    const t = useT();

    return (
        <AppLayout title={t.console.title}>
            <h1 className="text-3xl font-bold text-on-surface">{t.console.title}</h1>
            <p className="mt-2 text-on-surface-muted">{t.console.subtitle}</p>
        </AppLayout>
    );
}
