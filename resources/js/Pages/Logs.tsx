import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/hooks/useT';

export default function Logs() {
    const t = useT();

    return (
        <AppLayout title={t.logs.title}>
            <h1 className="text-3xl font-bold text-on-surface">{t.logs.title}</h1>
            <p className="mt-2 text-on-surface-muted">{t.logs.subtitle}</p>
        </AppLayout>
    );
}
