import {
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
import { type ReactElement, useMemo } from 'react';
import { Line } from 'react-chartjs-2';

import type { PendulumTrajectory } from '@/api/pendulum';
import { useT } from '@/hooks/useT';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

type PendulumChartProps = {
    trajectory: PendulumTrajectory | null;
    cursorIndex: number;
};

/**
 * Padded [min, max] for a y-axis. Computed from the full trajectory so the
 * chart frame stays fixed while the line is progressively revealed — without
 * this the axis would rescale on every frame and the line would jitter.
 */
function paddedBounds(values: number[]): { min: number; max: number } {
    const first = values[0];
    if (first === undefined) {
        return { min: 0, max: 1 };
    }
    let lo = first;
    let hi = first;
    for (const v of values) {
        if (v < lo) lo = v;
        if (v > hi) hi = v;
    }
    const margin = (hi - lo) * 0.1 || 1;
    return { min: lo - margin, max: hi + margin };
}

/** pointRadius array — every point invisible except the head (current frame). */
function headMarker(count: number): number[] {
    return Array.from({ length: count }, (_, i) => (i === count - 1 ? 4 : 0));
}

/**
 * Trajectory chart for the inverted pendulum.
 *
 * The line is drawn progressively: only the samples up to `cursorIndex` are
 * plotted, so the graph fills in step with the Konva animation. A dot marks
 * the head of the line — the exact sample the animation is showing. The axes
 * are pinned to the full trajectory's range so the frame never rescales.
 */
export default function PendulumChart({ trajectory, cursorIndex }: PendulumChartProps): ReactElement {
    const t = useT();

    // Full series — labels and both data columns, derived once per trajectory.
    const series = useMemo(() => {
        if (trajectory === null) {
            return null;
        }
        return {
            labels: trajectory.samples.map((s) => s.t.toFixed(2)),
            position: trajectory.samples.map((s) => s.x),
            angle: trajectory.samples.map((s) => s.theta),
        };
    }, [trajectory]);

    // y-axis bounds from the FULL trajectory — keeps the frame fixed.
    const bounds = useMemo(() => {
        if (series === null) {
            return null;
        }
        return {
            position: paddedBounds(series.position),
            angle: paddedBounds(series.angle),
        };
    }, [series]);

    // Samples revealed so far — drives the progressive draw.
    const visibleCount = cursorIndex + 1;

    const chartData = useMemo(() => {
        if (series === null) {
            return { labels: [], datasets: [] };
        }
        return {
            // Full label set — the x-axis spans the whole run; only the data
            // is sliced, so the line stops at the current frame.
            labels: series.labels,
            datasets: [
                {
                    label: t.pendulum.chart.positionLabel,
                    data: series.position.slice(0, visibleCount),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 1.5,
                    pointRadius: headMarker(visibleCount),
                    pointBackgroundColor: 'rgb(59, 130, 246)',
                    tension: 0.2,
                    yAxisID: 'yPosition',
                },
                {
                    label: t.pendulum.chart.angleLabel,
                    data: series.angle.slice(0, visibleCount),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 1.5,
                    pointRadius: headMarker(visibleCount),
                    pointBackgroundColor: 'rgb(16, 185, 129)',
                    tension: 0.2,
                    yAxisID: 'yAngle',
                },
            ],
        };
    }, [series, visibleCount, t]);

    const options = useMemo<ChartOptions<'line'>>(
        () => ({
            responsive: true,
            animation: false,
            plugins: {
                legend: { position: 'top' },
                title: {
                    display: true,
                    text: t.pendulum.chart.title,
                },
            },
            scales: {
                x: {
                    title: { display: true, text: t.pendulum.chart.timeLabel },
                    ticks: { maxTicksLimit: 10 },
                },
                yPosition: {
                    type: 'linear',
                    position: 'left',
                    title: { display: true, text: t.pendulum.chart.positionLabel },
                    min: bounds?.position.min,
                    max: bounds?.position.max,
                },
                yAngle: {
                    type: 'linear',
                    position: 'right',
                    title: { display: true, text: t.pendulum.chart.angleLabel },
                    grid: { drawOnChartArea: false },
                    min: bounds?.angle.min,
                    max: bounds?.angle.max,
                },
            },
        }),
        [t, bounds],
    );

    return (
        <div className="w-full">
            <Line data={chartData} options={options} />
        </div>
    );
}
