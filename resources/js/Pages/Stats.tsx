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

import Card, { CardBody, CardHeader } from '@/Components/ui/Card';
import { useT } from '@/hooks/useT';
import { useLocale } from '@/hooks/useT';
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

// ---------------------------------------------------------------------------
// Sub-components
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

type CountryTableProps = {
    rows: CountryRow[];
    emptyMessage: string;
    countryLabel: string;
    isoLabel: string;
    countLabel: string;
};

function CountryTable({ rows, emptyMessage, countryLabel, isoLabel, countLabel }: CountryTableProps): ReactElement {
    if (rows.length === 0) {
        return <p className="text-sm text-on-surface-muted">{emptyMessage}</p>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-border text-left text-on-surface-muted">
                        <th className="pb-2 pr-4 font-medium">{countryLabel}</th>
                        <th className="pb-2 pr-4 font-medium">{isoLabel}</th>
                        <th className="pb-2 font-medium text-right">{countLabel}</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.country_iso} className="border-b border-border/50 last:border-0">
                            <td className="py-2 pr-4">{row.country ?? row.country_iso}</td>
                            <td className="py-2 pr-4 font-mono text-xs text-on-surface-muted">{row.country_iso}</td>
                            <td className="py-2 text-right tabular-nums">{row.count}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

type CityTableProps = {
    rows: CityRow[];
    emptyMessage: string;
    cityLabel: string;
    countLabel: string;
};

function CityTable({ rows, emptyMessage, cityLabel, countLabel }: CityTableProps): ReactElement {
    if (rows.length === 0) {
        return <p className="text-sm text-on-surface-muted">{emptyMessage}</p>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-border text-left text-on-surface-muted">
                        <th className="pb-2 pr-4 font-medium">{cityLabel}</th>
                        <th className="pb-2 font-medium text-right">{countLabel}</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, i) => (
                        <tr key={i} className="border-b border-border/50 last:border-0">
                            <td className="py-2 pr-4">{row.city ?? '—'}</td>
                            <td className="py-2 text-right tabular-nums">{row.count}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Tabs
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
        <nav aria-label="Stats tabs" className="flex gap-1 rounded-lg border border-border bg-surface-raised p-1">
            {tabs.map((tab) => (
                <Link
                    key={tab.id}
                    href={tab.href}
                    aria-current={active === tab.id ? 'page' : undefined}
                    className={cn(
                        'rounded-md px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
                        active === tab.id
                            ? 'bg-primary text-on-primary'
                            : 'text-on-surface-muted hover:bg-secondary hover:text-on-surface',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
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
    // `detail` and `animation` are only present when ?animation= is set
    const detail = props.detail;
    const activeAnimation = props.animation;

    const activeTab: TabId =
        activeAnimation === 'pendulum' || activeAnimation === 'ball-beam' ? activeAnimation : 'summary';

    const localePrefix = `/${locale}`;

    return (
        <AppLayout title={t.stats.title}>
            <div className="flex flex-col gap-2">
                <h1 className="text-3xl font-bold text-on-surface">{t.stats.title}</h1>
                <p className="text-on-surface-muted">
                    {t.stats.subtitle} <span className="text-sm">({t.stats.last30Days})</span>
                </p>
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

            {/* Summary view */}
            {activeTab === 'summary' && (
                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Totals bar chart */}
                    <Card>
                        <CardHeader>
                            <h2 className="text-base font-semibold text-on-surface">{t.stats.totalsTitle}</h2>
                        </CardHeader>
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
                        <CardHeader>
                            <h2 className="text-base font-semibold text-on-surface">{t.stats.topCountriesTitle}</h2>
                        </CardHeader>
                        <CardBody>
                            <CountryTable
                                rows={summary.top_countries}
                                emptyMessage={t.stats.empty}
                                countryLabel={t.stats.columns.country}
                                isoLabel={t.stats.columns.iso}
                                countLabel={t.stats.columns.count}
                            />
                        </CardBody>
                    </Card>

                    {/* Per-day line chart — full width */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <h2 className="text-base font-semibold text-on-surface">{t.stats.perDayTitle}</h2>
                        </CardHeader>
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
                        <CardHeader>
                            <h2 className="text-base font-semibold text-on-surface">{t.stats.perDayTitle}</h2>
                        </CardHeader>
                        <CardBody>
                            <DetailPerDayChart
                                perDay={detail.per_day}
                                animationLabel={
                                    activeTab === 'pendulum' ? t.stats.animations.pendulum : t.stats.animations.ballBeam
                                }
                                timeLabel={t.stats.last30Days}
                            />
                        </CardBody>
                    </Card>

                    {/* Top countries */}
                    <Card>
                        <CardHeader>
                            <h2 className="text-base font-semibold text-on-surface">{t.stats.topCountriesTitle}</h2>
                        </CardHeader>
                        <CardBody>
                            <CountryTable
                                rows={detail.top_countries}
                                emptyMessage={t.stats.empty}
                                countryLabel={t.stats.columns.country}
                                isoLabel={t.stats.columns.iso}
                                countLabel={t.stats.columns.count}
                            />
                        </CardBody>
                    </Card>

                    {/* Top cities */}
                    <Card>
                        <CardHeader>
                            <h2 className="text-base font-semibold text-on-surface">{t.stats.topCitiesTitle}</h2>
                        </CardHeader>
                        <CardBody>
                            <CityTable
                                rows={detail.top_cities}
                                emptyMessage={t.stats.empty}
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
