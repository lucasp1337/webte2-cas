import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/hooks/useT';

export default function ApiDocs() {
    const t = useT();

    return (
        <AppLayout title={t.apiDocs.title}>
            <h1 className="text-3xl font-bold text-on-surface">{t.apiDocs.title}</h1>
            <p className="mt-2 text-on-surface-muted">{t.apiDocs.subtitle}</p>
        </AppLayout>
    );
}
