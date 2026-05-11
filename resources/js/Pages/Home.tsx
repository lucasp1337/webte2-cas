import { Link } from '@inertiajs/react';

import { ArrowIcon } from '@/Components/icons';
import Button from '@/Components/ui/Button';
import Badge from '@/Components/ui/Badge';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/hooks/useT';
import { useLocale } from '@/hooks/useT';
import { cn } from '@/lib/cn';

// ── Feature card ──────────────────────────────────────────────────────────

type FeatureCardBadge = {
    label: string;
    variant?: 'neutral' | 'accent';
};

type FeatureCardProps = {
    label: string;
    title: string;
    desc: string;
    href: string;
    badges: FeatureCardBadge[];
};

function FeatureCard({ label, title, desc, href, badges }: FeatureCardProps) {
    return (
        <Link
            href={href}
            className={cn(
                'flex min-h-[180px] flex-col gap-3 rounded-md border border-border bg-surface-raised p-5',
                'text-on-surface no-underline',
                'transition-[border-color,transform] duration-150',
                'hover:border-border-strong',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
            )}
        >
            {/* Card header */}
            <div className="flex items-center justify-between">
                <span
                    className={cn(
                        'font-mono text-[11px] font-medium tracking-[0em]',
                        'rounded-[3px] border px-[7px] py-px',
                        'border-accent/25 bg-accent-soft text-accent',
                    )}
                >
                    {label}
                </span>
                <span className="text-on-surface-faint">
                    <ArrowIcon />
                </span>
            </div>

            {/* Card body */}
            <div>
                <div className="mb-[6px] text-[18px] font-semibold leading-tight tracking-[-0.015em]">{title}</div>
                <div className="text-[13.5px] leading-[1.5] text-on-surface-muted">{desc}</div>
            </div>

            {/* Card footer badges */}
            <div className="mt-auto flex flex-wrap gap-1.5">
                {badges.map((b) => (
                    <Badge key={b.label} variant={b.variant ?? 'neutral'} square>
                        {b.label}
                    </Badge>
                ))}
            </div>
        </Link>
    );
}

// ── Code highlight atoms ──────────────────────────────────────────────────

function C({ children }: { children: React.ReactNode }) {
    return <span className="text-code-comment">{children}</span>;
}
function K({ children }: { children: React.ReactNode }) {
    return <span className="text-code-keyword">{children}</span>;
}
function S({ children }: { children: React.ReactNode }) {
    return <span className="text-code-string">{children}</span>;
}
function O({ children }: { children: React.ReactNode }) {
    return <span className="text-on-surface-muted">{children}</span>;
}

// ── Page ─────────────────────────────────────────────────────────────────

export default function Home() {
    const t = useT();
    const locale = useLocale();

    const localePrefix = `/${locale}`;

    return (
        <AppLayout title={t.home.title}>
            {/* ── Hero ── */}
            <section className="pb-14 pt-7">
                {/* Eyebrow */}
                <div className="mb-[18px] flex items-center gap-[10px] font-mono text-[11px] uppercase tracking-[0.08em] text-on-surface-muted">
                    <span aria-hidden="true" className="h-[6px] w-[6px] shrink-0 rounded-full bg-success" />
                    <span>{t.home.tag}</span>
                    <span className="text-on-surface-faint">&middot;</span>
                    <span>{t.home.version}</span>
                </div>

                {/* Giant wordmark */}
                <h1
                    className="mb-6 max-w-[920px] text-[88px] font-semibold leading-[0.95] tracking-[-0.045em]"
                    style={{
                        background: 'var(--brand-gradient)',
                        WebkitBackgroundClip: 'text',
                        backgroundClip: 'text',
                        color: 'transparent',
                    }}
                >
                    WEBTE2
                </h1>

                {/* Tagline */}
                <p className="mb-8 max-w-[640px] text-[20px] leading-[1.4] tracking-[-0.012em] text-on-surface-muted">
                    {t.home.tagline}
                </p>

                {/* CTAs */}
                <div className="flex flex-wrap gap-[10px]">
                    <Button
                        variant="primary"
                        size="lg"
                        trailingIcon={
                            <span className="ml-1">
                                <ArrowIcon />
                            </span>
                        }
                        onClick={() => {
                            window.location.href = `${localePrefix}/console`;
                        }}
                    >
                        {t.home.cta}
                    </Button>
                    <Button
                        variant="secondary"
                        size="lg"
                        onClick={() => {
                            window.location.href = `${localePrefix}/api-docs`;
                        }}
                    >
                        {t.home.ctaSecondary}
                    </Button>
                </div>
            </section>

            {/* ── Features grid ── */}
            <section className="mb-14">
                <div className="mb-[10px] font-mono text-[11px] uppercase tracking-[0.08em] text-on-surface-muted">
                    {t.home.featuresEyebrow}
                </div>
                <div className="mt-[14px] grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <FeatureCard
                        label={t.home.features.console.label}
                        title={t.home.features.console.title}
                        desc={t.home.features.console.desc}
                        href={`${localePrefix}/console`}
                        badges={[{ label: 'session' }, { label: 'persistent' }]}
                    />
                    <FeatureCard
                        label={t.home.features.simulations.label}
                        title={t.home.features.simulations.title}
                        desc={t.home.features.simulations.desc}
                        href={`${localePrefix}/pendulum`}
                        badges={[{ label: 'konva', variant: 'accent' }, { label: '16:9' }]}
                    />
                    <FeatureCard
                        label={t.home.features.api.label}
                        title={t.home.features.api.title}
                        desc={t.home.features.api.desc}
                        href={`${localePrefix}/api-docs`}
                        badges={[{ label: 'openapi 3.1' }, { label: 'pdf' }]}
                    />
                </div>
            </section>

            {/* ── Quick start ── */}
            <section>
                {/* Section header */}
                <div className="mb-[14px] flex flex-wrap items-baseline justify-between gap-4">
                    <div>
                        <div className="font-mono text-[11px] uppercase tracking-[0.08em] text-on-surface-muted">
                            {t.home.quickStartEyebrow}
                        </div>
                        <h2 className="mt-1 text-[22px] font-semibold tracking-[-0.02em]">{t.home.quickStartTitle}</h2>
                    </div>
                    <Link
                        href={`${localePrefix}/api-docs`}
                        className={cn(
                            'flex items-center gap-1 font-mono text-[12px] tracking-[0.02em] text-on-surface-muted',
                            'transition-colors hover:text-on-surface',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                        )}
                    >
                        {t.home.quickStartRef} <ArrowIcon />
                    </Link>
                </div>

                {/* Code block */}
                <div className="overflow-hidden rounded-md border border-border bg-code-bg font-mono text-code-text">
                    {/* Terminal bar */}
                    <div
                        className={cn(
                            'flex items-center justify-between border-b border-border',
                            'px-[14px] py-[10px] text-[11px] tracking-[0.04em] text-on-surface-muted',
                        )}
                    >
                        <span className="flex gap-[14px]">
                            <span className="text-on-surface">$ curl</span>
                            <span>POST</span>
                            <span>/api/v1/octave/exec</span>
                        </span>
                        <span className="flex items-center gap-2">
                            <Badge variant="success" dot>
                                200 OK
                            </Badge>
                            {/* Decorative copy button — no logic in wave 1 */}
                            <button
                                type="button"
                                className={cn(
                                    'inline-flex h-[22px] cursor-pointer items-center rounded border border-transparent',
                                    'px-[9px] text-[12px] font-medium text-on-surface-muted',
                                    'transition-colors hover:bg-surface-sunken hover:text-on-surface',
                                )}
                            >
                                copy
                            </button>
                        </span>
                    </div>

                    {/* Code body */}
                    <pre className="whitespace-pre px-4 py-[14px] text-[12.5px] leading-[1.7]">
                        <div>
                            <O>$</O> <span className="text-code-text">curl</span> <C>-X</C> <K>POST</K>{' '}
                            <S>{'http://localhost/api/v1/octave/exec'}</S> <O>\</O>
                        </div>
                        <div>
                            {'    '}
                            <C>-H</C> <S>{'"X-API-Key: $WEBTE2_KEY"'}</S> <O>\</O>
                        </div>
                        <div>
                            {'    '}
                            <C>-H</C> <S>{'"Content-Type: application/json"'}</S> <O>\</O>
                        </div>
                        <div>
                            {'    '}
                            <C>-d</C> <S>{'\'{"command": "a = 1+1; a+2"}\''}</S>
                        </div>
                        <div className="mt-[10px]">
                            <C>{'# → { "stdout": "ans = 4\\n", "stderr": "", "duration_ms": 38 }'}</C>
                        </div>
                    </pre>
                </div>
            </section>
        </AppLayout>
    );
}
