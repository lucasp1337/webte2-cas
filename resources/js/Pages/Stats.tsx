import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/hooks/useT';

export default function Stats() {
    const t = useT();

    return (
        <AppLayout title={t.stats.title}>
            <h1 className="text-3xl font-bold text-on-surface">{t.stats.title}</h1>
            <p className="mt-2 text-on-surface-muted">{t.stats.subtitle}</p>
        </AppLayout>
    );
}
