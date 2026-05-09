import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/hooks/useT';

export default function Home() {
    const t = useT();

    return (
        <AppLayout title={t.home.title}>
            <h1 className="text-3xl font-bold text-on-surface">{t.home.title}</h1>
            <p className="mt-2 text-on-surface-muted">{t.home.subtitle}</p>
        </AppLayout>
    );
}
