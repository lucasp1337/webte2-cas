import { Link } from '@inertiajs/react';

import type { Translation } from '@/i18n';
import Button from '@/Components/ui/Button';
import { useT } from '@/hooks/useT';
import { useLocale } from '@/hooks/useT';
import AppLayout from '@/Layouts/AppLayout';

type SuggestedRoute = {
    navKey: keyof Translation['nav'];
    path: string;
};

const SUGGESTED_ROUTES: readonly SuggestedRoute[] = [
    { navKey: 'home', path: '' },
    { navKey: 'console', path: '/console' },
    { navKey: 'pendulum', path: '/pendulum' },
    { navKey: 'ballBeam', path: '/ball-beam' },
    { navKey: 'logs', path: '/logs' },
    { navKey: 'apiDocs', path: '/api-docs' },
    { navKey: 'stats', path: '/stats' },
];

// No page props needed — this component is purely presentational.
export type NotFoundProps = Record<string, never>;

export default function NotFound() {
    const t = useT();
    const locale = useLocale();
    const prefix = `/${locale}`;

    return (
        <AppLayout title={t.notFound.title}>
            <div className="flex flex-col items-center py-16 text-center">
                {/* Hero mono "404" */}
                <div
                    className="font-mono text-[96px] font-semibold leading-none tracking-[-0.03em] text-on-surface-faint"
                    aria-hidden="true"
                >
                    404
                </div>

                {/* Title */}
                <h1 className="mt-6 text-[28px] font-semibold leading-tight tracking-[-0.025em] text-on-surface">
                    {t.notFound.title}
                </h1>

                {/* Subtitle */}
                <p className="mt-2 max-w-[52ch] text-[15px] leading-relaxed text-on-surface-muted">
                    {t.notFound.subtitle}
                </p>

                {/* Back-home CTA */}
                <div className="mt-8">
                    <Link href={prefix} tabIndex={-1}>
                        <Button variant="primary" size="lg">
                            {t.notFound.backHome}
                        </Button>
                    </Link>
                </div>

                {/* Suggested routes */}
                <div className="mt-12 w-full max-w-sm">
                    <p className="mb-3 font-mono text-[11px] uppercase tracking-[0.08em] text-on-surface-muted">
                        {t.notFound.suggested}
                    </p>
                    <ul className="divide-y divide-border rounded-lg border border-border bg-surface-raised">
                        {SUGGESTED_ROUTES.map((route) => (
                            <li key={route.navKey}>
                                <Link
                                    href={`${prefix}${route.path}`}
                                    className="flex items-center justify-between px-4 py-[10px] text-[13px] text-on-surface-muted transition-colors hover:bg-surface-sunken hover:text-on-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent"
                                >
                                    <span>{t.nav[route.navKey]}</span>
                                    <span className="font-mono text-[11px] tracking-[0.02em] text-on-surface-faint">
                                        {`/${locale}${route.path}`}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </AppLayout>
    );
}
