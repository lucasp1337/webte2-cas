import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/hooks/useT';

export default function BallBeam() {
    const t = useT();

    return (
        <AppLayout title={t.ballBeam.title}>
            <h1 className="text-3xl font-bold text-on-surface">{t.ballBeam.title}</h1>
            <p className="mt-2 text-on-surface-muted">{t.ballBeam.subtitle}</p>
        </AppLayout>
    );
}
