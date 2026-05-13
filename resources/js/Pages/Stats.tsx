import { Link, usePage } from '@inertiajs/react';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    type ChartOptions,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import { type ReactElement } from 'react';
import { Bar, Line } from 'react-chartjs-2';

import Badge from '@/Components/ui/Badge';
import Card, { CardBody, CardHeader } from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import { useLocale, useT } from '@/hooks/useT';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/cn';

ChartJS.register(CategoryScale, LinearScale, BarElement, PointElement, LineElement, Title, Tooltip, Legend);

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export type TotalsShape = Record<string, number>;

export type PerDayRow = {
    date: string;
    animation: string;
    count: number;
};

export type PerDayDetailRow = {
    date: string;
    count: number;
};

export type CountryRow = {
    country_iso: string;
    country: string | null;
    count: number;
};

export type CityRow = {
    city: string | null;
    count: number;
};

export type SummaryShape = {
    totals: TotalsShape;
    per_day: PerDayRow[];
    top_countries: CountryRow[];
};

export type DetailShape = {
    animation: string;
    per_day: PerDayDetailRow[];
    top_countries: CountryRow[];
    top_cities: CityRow[];
};

type StatsPageProps = {
    summary: SummaryShape;
    detail?: DetailShape;
    animation?: string;
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Generate an ISO date string (YYYY-MM-DD) for each day in the last N days,
 * with the most recent day last.
 */
function buildDateRange(days: number): string[] {
    const result: string[] = [];
    const now = new Date();
    for (let i = days - 1; i >= 0; i--) {
        const d = new Date(now);
        d.setDate(d.getDate() - i);
        result.push(d.toISOString().slice(0, 10));
    }
    return result;
}

const DATE_RANGE = buildDateRange(30);

function fillZeros(rows: PerDayRow[], animation: string): number[] {
    const map = new Map<string, number>();
    for (const row of rows) {
        if (row.animation === animation) {
            map.set(row.date, row.count);
        }
    }
    return DATE_RANGE.map((d) => map.get(d) ?? 0);
}

function fillDetailZeros(rows: PerDayDetailRow[]): number[] {
    const map = new Map<string, number>();
    for (const row of rows) {
        map.set(row.date, row.count);
    }
    return DATE_RANGE.map((d) => map.get(d) ?? 0);
}

/** Sum all values in a per-day series. */
function sumSeries(values: number[]): number {
    return values.reduce((acc, v) => acc + v, 0);
}

/** Find the max value in a per-day series and return its date label. */
function peakDate(rows: PerDayRow[]): string {
    if (rows.length === 0) return '—';
    let best = rows[0];
    for (const r of rows) {
        if (r.count > (best?.count ?? 0)) best = r;
    }
    return best?.date ?? '—';
}

// ---------------------------------------------------------------------------
// KPI card
// ---------------------------------------------------------------------------

type KpiCardProps = {
    label: string;
    value: string;
};

function KpiCard({ label, value }: KpiCardProps): ReactElement {
    return (
        <div className="flex flex-col gap-3 rounded-md border border-border bg-surface-raised p-5">
            <span className="font-mono text-[11px] uppercase tracking-[0.08em] text-on-surface-muted">{label}</span>
            <span className="font-mono text-[32px] font-semibold leading-none tracking-[-0.025em] text-on-surface">
                {value}
            </span>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Chart sub-components (unchanged datasets; only wrapper chrome changed)
// ---------------------------------------------------------------------------

type TotalsChartProps = {
    totals: TotalsShape;
    pendulumLabel: string;
    ballBeamLabel: string;
    chartTitle: string;
};

function TotalsChart({ totals, pendulumLabel, ballBeamLabel, chartTitle }: TotalsChartProps): ReactElement {
    const data = {
        labels: [pendulumLabel, ballBeamLabel],
        datasets: [
            {
                label: chartTitle,
                data: [totals['pendulum'] ?? 0, totals['ball-beam'] ?? 0],
                backgroundColor: ['rgba(59, 130, 246, 0.7)', 'rgba(16, 185, 129, 0.7)'],
                borderColor: ['rgb(59, 130, 246)', 'rgb(16, 185, 129)'],
                borderWidth: 1.5,
            },
        ],
    };

    const options: ChartOptions<'bar'> = {
        responsive: true,
        animation: false,
        plugins: {
            legend: { display: false },
            title: { display: false },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 },
            },
        },
    };

    return <Bar data={data} options={options} />;
}

type PerDayChartProps = {
    perDay: PerDayRow[];
    pendulumLabel: string;
    ballBeamLabel: string;
    timeLabel: string;
};

function PerDayChart({ perDay, pendulumLabel, ballBeamLabel, timeLabel }: PerDayChartProps): ReactElement {
    const data = {
        labels: DATE_RANGE,
        datasets: [
            {
                label: pendulumLabel,
                data: fillZeros(perDay, 'pendulum'),
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 1.5,
                pointRadius: 2,
                tension: 0.2,
            },
            {
                label: ballBeamLabel,
                data: fillZeros(perDay, 'ball-beam'),
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 1.5,
                pointRadius: 2,
                tension: 0.2,
            },
        ],
    };

    const options: ChartOptions<'line'> = {
        responsive: true,
        animation: false,
        plugins: {
            legend: { position: 'top' },
            title: { display: false },
        },
        scales: {
            x: {
                title: { display: true, text: timeLabel },
                ticks: { maxTicksLimit: 8 },
            },
            y: {
                beginAtZero: true,
                ticks: { precision: 0 },
            },
        },
    };

    return <Line data={data} options={options} />;
}

type DetailPerDayChartProps = {
    perDay: PerDayDetailRow[];
    animationLabel: string;
    timeLabel: string;
};

function DetailPerDayChart({ perDay, animationLabel, timeLabel }: DetailPerDayChartProps): ReactElement {
    const data = {
        labels: DATE_RANGE,
        datasets: [
            {
                label: animationLabel,
                data: fillDetailZeros(perDay),
                borderColor: 'rgb(99, 102, 241)',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderWidth: 1.5,
                pointRadius: 2,
                tension: 0.2,
            },
        ],
    };

    const options: ChartOptions<'line'> = {
        responsive: true,
        animation: false,
        plugins: {
            legend: { position: 'top' },
            title: { display: false },
        },
        scales: {
            x: {
                title: { display: true, text: timeLabel },
                ticks: { maxTicksLimit: 8 },
            },
            y: {
                beginAtZero: true,
                ticks: { precision: 0 },
            },
        },
    };

    return <Line data={data} options={options} />;
}

// ---------------------------------------------------------------------------
// Table sub-components — styled with bar indicator
// ---------------------------------------------------------------------------

type CountryTableProps = {
    rows: CountryRow[];
    emptyTitle: string;
    emptyDescription: string;
    countryLabel: string;
    isoLabel: string;
    countLabel: string;
};

function CountryTable({
    rows,
    emptyTitle,
    emptyDescription,
    countryLabel,
    isoLabel,
    countLabel,
}: CountryTableProps): ReactElement {
    if (rows.length === 0) {
        return <EmptyState title={emptyTitle} description={emptyDescription} />;
    }

    const maxCount = rows.reduce((acc, r) => Math.max(acc, r.count), 0);

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-border text-left text-on-surface-muted">
                        <th className="pb-2 pr-4 font-medium">{countryLabel}</th>
                        <th className="pb-2 pr-4 font-medium">{isoLabel}</th>
                        <th className="w-32 pb-2 pr-4" />
                        <th className="pb-2 font-medium text-right">{countLabel}</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={row.country_iso}
                            className="border-b border-border/50 last:border-0 hover:bg-surface-sunken"
                        >
                            <td className="py-2 pr-4">{row.country ?? row.country_iso}</td>
                            <td className="py-2 pr-4 font-mono text-xs text-on-surface-muted">{row.country_iso}</td>
                            <td className="py-2 pr-4">
                                <div className="h-1 overflow-hidden rounded-sm bg-surface-sunken">
                                    <div
                                        className="h-full rounded-sm bg-on-surface"
                                        style={{ width: `${maxCount > 0 ? (row.count / maxCount) * 100 : 0}%` }}
                                    />
                                </div>
                            </td>
                            <td className="py-2 text-right font-mono tabular-nums text-on-surface-muted">
                                {row.count.toLocaleString()}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

type CityTableProps = {
    rows: CityRow[];
    emptyTitle: string;
    emptyDescription: string;
    cityLabel: string;
    countLabel: string;
};

function CityTable({ rows, emptyTitle, emptyDescription, cityLabel, countLabel }: CityTableProps): ReactElement {
    if (rows.length === 0) {
        return <EmptyState title={emptyTitle} description={emptyDescription} />;
    }

    const maxCount = rows.reduce((acc, r) => Math.max(acc, r.count), 0);

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-border text-left text-on-surface-muted">
                        <th className="pb-2 pr-4 font-medium">{cityLabel}</th>
                        <th className="w-32 pb-2 pr-4" />
                        <th className="pb-2 font-medium text-right">{countLabel}</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, i) => (
                        <tr key={i} className="border-b border-border/50 last:border-0 hover:bg-surface-sunken">
                            <td className="py-2 pr-4">{row.city ?? '—'}</td>
                            <td className="py-2 pr-4">
                                <div className="h-1 overflow-hidden rounded-sm bg-surface-sunken">
                                    <div
                                        className="h-full rounded-sm bg-on-surface"
                                        style={{ width: `${maxCount > 0 ? (row.count / maxCount) * 100 : 0}%` }}
                                    />
                                </div>
                            </td>
                            <td className="py-2 text-right font-mono tabular-nums text-on-surface-muted">
                                {row.count.toLocaleString()}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Tabs — styled per design: mono font, square corners, sunken active
// ---------------------------------------------------------------------------

type TabId = 'summary' | 'pendulum' | 'ball-beam';

type TabsProps = {
    active: TabId;
    summaryLabel: string;
    pendulumLabel: string;
    ballBeamLabel: string;
    localePrefix: string;
};

function Tabs({ active, summaryLabel, pendulumLabel, ballBeamLabel, localePrefix }: TabsProps): ReactElement {
    const tabs: { id: TabId; label: string; href: string }[] = [
        { id: 'summary', label: summaryLabel, href: `${localePrefix}/stats` },
        { id: 'pendulum', label: pendulumLabel, href: `${localePrefix}/stats?animation=pendulum` },
        { id: 'ball-beam', label: ballBeamLabel, href: `${localePrefix}/stats?animation=ball-beam` },
    ];

    return (
        <nav aria-label="Stats tabs" className="flex gap-1 rounded border border-border bg-surface-sunken p-1">
            {tabs.map((tab) => (
                <Link
                    key={tab.id}
                    href={tab.href}
                    aria-current={active === tab.id ? 'page' : undefined}
                    className={cn(
                        'rounded-[3px] px-4 py-1.5 font-mono text-[12px] tracking-[-0.005em] transition-colors',
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
                        active === tab.id
                            ? 'bg-surface-raised text-on-surface-strong shadow-sm'
                            : 'text-on-surface-muted hover:text-on-surface',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}

// ---------------------------------------------------------------------------
// Card header with eyebrow + count badge
// ---------------------------------------------------------------------------

type SectionCardHeaderProps = {
    eyebrow: string;
    title: string;
    badge?: string;
};

function SectionCardHeader({ eyebrow, title, badge }: SectionCardHeaderProps): ReactElement {
    return (
        <CardHeader className="flex items-center justify-between gap-4">
            <div>
                <p className="font-mono text-[10px] uppercase tracking-[0.08em] text-on-surface-muted">{eyebrow}</p>
                <h2 className="mt-0.5 text-[15px] font-semibold leading-tight tracking-[-0.015em] text-on-surface">
                    {title}
                </h2>
            </div>
            {badge !== undefined && (
                <Badge variant="neutral" square>
                    {badge}
                </Badge>
            )}
        </CardHeader>
    );
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function Stats(): ReactElement {
    const t = useT();
    const locale = useLocale();
    const { props } = usePage<StatsPageProps>();

    const { summary } = props;
    const detail = props.detail;
    const activeAnimation = props.animation;

    const activeTab: TabId =
        activeAnimation === 'pendulum' || activeAnimation === 'ball-beam' ? activeAnimation : 'summary';

    const localePrefix = `/${locale}`;

    // Derive KPI values from summary totals
    const pendulumTotal = summary.totals['pendulum'] ?? 0;
    const ballBeamTotal = summary.totals['ball-beam'] ?? 0;
    const totalRuns = pendulumTotal + ballBeamTotal;

    const topCountry =
        summary.top_countries.length > 0
            ? (summary.top_countries[0]?.country ?? summary.top_countries[0]?.country_iso ?? '—')
            : t.stats.kpi.notEnoughData;

    const peakDayValue = peakDate(summary.per_day);

    // Check if there's any data at all
    const hasData = totalRuns > 0;

    return (
        <AppLayout title={t.stats.title}>
            {/* Page title strip */}
            <div className="flex items-end justify-between gap-4">
                <div>
                    <h1 className="text-[28px] font-semibold leading-tight tracking-[-0.025em] text-on-surface">
                        {t.stats.title}
                    </h1>
                    <p className="mt-1 text-[15px] text-on-surface-muted">{t.stats.subtitle}</p>
                </div>
                <Badge variant="neutral" square className="shrink-0">
                    {t.stats.lastThirtyDays}
                </Badge>
            </div>

            {/* Tab navigation */}
            <div className="mt-6">
                <Tabs
                    active={activeTab}
                    summaryLabel={t.stats.tabs.summary}
                    pendulumLabel={t.stats.tabs.pendulum}
                    ballBeamLabel={t.stats.tabs.ballBeam}
                    localePrefix={localePrefix}
                />
            </div>

            {/* KPI strip — always visible */}
            {hasData ? (
                <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <KpiCard label={t.stats.kpi.totalRuns} value={totalRuns.toLocaleString()} />
                    <KpiCard label={t.stats.kpi.topCountry} value={topCountry} />
                    <KpiCard label={t.stats.kpi.peakDay} value={peakDayValue} />
                </div>
            ) : (
                <div className="mt-6">
                    <EmptyState title={t.stats.emptyState.title} description={t.stats.emptyState.description} />
                </div>
            )}

            {/* Summary view */}
            {activeTab === 'summary' && hasData && (
                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Totals bar chart */}
                    <Card>
                        <SectionCardHeader
                            eyebrow={t.stats.totalsTitle}
                            title={`${pendulumTotal.toLocaleString()} + ${ballBeamTotal.toLocaleString()}`}
                        />
                        <CardBody>
                            <TotalsChart
                                totals={summary.totals}
                                pendulumLabel={t.stats.animations.pendulum}
                                ballBeamLabel={t.stats.animations.ballBeam}
                                chartTitle={t.stats.totalsTitle}
                            />
                        </CardBody>
                    </Card>

                    {/* Top countries table */}
                    <Card>
                        <SectionCardHeader
                            eyebrow={t.stats.topCountriesTitle}
                            title={t.stats.topCountriesTitle}
                            badge={`top ${Math.min(summary.top_countries.length, 10).toString()}`}
                        />
                        <CardBody>
                            <CountryTable
                                rows={summary.top_countries}
                                emptyTitle={t.stats.emptyState.title}
                                emptyDescription={t.stats.emptyState.description}
                                countryLabel={t.stats.columns.country}
                                isoLabel={t.stats.columns.iso}
                                countLabel={t.stats.columns.count}
                            />
                        </CardBody>
                    </Card>

                    {/* Per-day line chart — full width */}
                    <Card className="lg:col-span-2">
                        <SectionCardHeader eyebrow={t.stats.lastThirtyDays} title={t.stats.perDayTitle} />
                        <CardBody>
                            <PerDayChart
                                perDay={summary.per_day}
                                pendulumLabel={t.stats.animations.pendulum}
                                ballBeamLabel={t.stats.animations.ballBeam}
                                timeLabel={t.stats.last30Days}
                            />
                        </CardBody>
                    </Card>
                </div>
            )}

            {/* Per-animation drilldown view */}
            {detail !== undefined && (activeTab === 'pendulum' || activeTab === 'ball-beam') && (
                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Per-day line chart — full width */}
                    <Card className="lg:col-span-2">
                        <SectionCardHeader eyebrow={t.stats.lastThirtyDays} title={t.stats.perDayTitle} />
                        <CardBody>
                            {sumSeries(fillDetailZeros(detail.per_day)) === 0 ? (
                                <EmptyState
                                    title={t.stats.emptyState.title}
                                    description={t.stats.emptyState.description}
                                />
                            ) : (
                                <DetailPerDayChart
                                    perDay={detail.per_day}
                                    animationLabel={
                                        activeTab === 'pendulum'
                                            ? t.stats.animations.pendulum
                                            : t.stats.animations.ballBeam
                                    }
                                    timeLabel={t.stats.last30Days}
                                />
                            )}
                        </CardBody>
                    </Card>

                    {/* Top countries */}
                    <Card>
                        <SectionCardHeader
                            eyebrow={t.stats.topCountriesTitle}
                            title={t.stats.topCountriesTitle}
                            badge={`top ${Math.min(detail.top_countries.length, 10).toString()}`}
                        />
                        <CardBody>
                            <CountryTable
                                rows={detail.top_countries}
                                emptyTitle={t.stats.emptyState.title}
                                emptyDescription={t.stats.emptyState.description}
                                countryLabel={t.stats.columns.country}
                                isoLabel={t.stats.columns.iso}
                                countLabel={t.stats.columns.count}
                            />
                        </CardBody>
                    </Card>

                    {/* Top cities */}
                    <Card>
                        <SectionCardHeader
                            eyebrow={t.stats.topCitiesTitle}
                            title={t.stats.topCitiesTitle}
                            badge={`top ${Math.min(detail.top_cities.length, 10).toString()}`}
                        />
                        <CardBody>
                            <CityTable
                                rows={detail.top_cities}
                                emptyTitle={t.stats.emptyState.title}
                                emptyDescription={t.stats.emptyState.description}
                                cityLabel={t.stats.columns.city}
                                countLabel={t.stats.columns.count}
                            />
                        </CardBody>
                    </Card>
                </div>
            )}
        </AppLayout>
    );
}
