import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/hooks/useT';

export default function Pendulum() {
    const t = useT();

    return (
        <AppLayout title={t.pendulum.title}>
            <h1 className="text-3xl font-bold text-on-surface">{t.pendulum.title}</h1>
            <p className="mt-2 text-on-surface-muted">{t.pendulum.subtitle}</p>
        </AppLayout>
    );
}
