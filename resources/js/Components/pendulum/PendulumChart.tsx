import {
    CategoryScale,
    Chart as ChartJS,
    type ChartOptions,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    type Plugin,
    Title,
    Tooltip,
} from 'chart.js';
import { type ReactElement, useMemo } from 'react';
import { Line } from 'react-chartjs-2';

import type { PendulumTrajectory } from '@/api/pendulum';
import { useT } from '@/hooks/useT';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

// ---------------------------------------------------------------------------
// Cursor plugin — draws a vertical "now" line at the current frame index.
// The plugin reads `cursorX` from chart.data.datasets[0] via a shared object
// attached to the chart instance's options so the chart re-uses its canvas
// rather than full-remounting every frame.
// ---------------------------------------------------------------------------

type CursorPluginOptions = {
    cursorRatio: number; // 0.0 – 1.0
};

const cursorPlugin: Plugin<'line', CursorPluginOptions> = {
    id: 'pendulumCursor',
    afterDraw(chart): void {
        // `chart.options.plugins` is typed as `PluginOptionsByType<'line'>` which
        // includes our augmented `pendulumCursor` key, but the `afterDraw`
        // callback receives a generic `Chart` whose `.options.plugins` type is
        // widened to `Record<string, unknown>` — the cast is safe given our
        // module augmentation above.
        const pluginOptions = chart.options.plugins?.pendulumCursor as CursorPluginOptions | undefined;
        if (pluginOptions === undefined) return;

        const { cursorRatio } = pluginOptions;
        const { ctx, chartArea } = chart;
        if (chartArea === undefined) return;

        const x = chartArea.left + cursorRatio * (chartArea.right - chartArea.left);

        ctx.save();
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.strokeStyle = 'rgba(255, 100, 0, 0.75)';
        ctx.lineWidth = 2;
        ctx.setLineDash([4, 3]);
        ctx.stroke();
        ctx.restore();
    },
};

// Register the plugin once — subsequent renders reuse it.
ChartJS.register(cursorPlugin);

// Extend Chart.js plugin type registry so TS knows about our custom plugin.
declare module 'chart.js' {
    interface PluginOptionsByType<TType extends import('chart.js').ChartType> {
        pendulumCursor?: TType extends 'line' ? CursorPluginOptions : never;
    }
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

type PendulumChartProps = {
    trajectory: PendulumTrajectory | null;
    cursorIndex: number;
};

export default function PendulumChart({ trajectory, cursorIndex }: PendulumChartProps): ReactElement {
    const t = useT();

    // Derive chart data only when the trajectory changes — cursor movement
    // is handled cheaply via the plugin option, not a data rebuild.
    const chartData = useMemo(() => {
        if (trajectory === null) {
            return { labels: [], datasets: [] };
        }

        const labels = trajectory.samples.map((s) => s.t.toFixed(2));
        return {
            labels,
            datasets: [
                {
                    label: t.pendulum.chart.positionLabel,
                    data: trajectory.samples.map((s) => s.x),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 1.5,
                    pointRadius: 0,
                    tension: 0.2,
                    yAxisID: 'yPosition',
                },
                {
                    label: t.pendulum.chart.angleLabel,
                    data: trajectory.samples.map((s) => s.theta),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 1.5,
                    pointRadius: 0,
                    tension: 0.2,
                    yAxisID: 'yAngle',
                },
            ],
        };
    }, [trajectory, t]);

    const frameCount = trajectory?.samples.length ?? 1;
    const cursorRatio = frameCount > 1 ? cursorIndex / (frameCount - 1) : 0;

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
                // Cursor ratio is updated outside useMemo — see note below.
                pendulumCursor: { cursorRatio: 0 },
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
                },
                yAngle: {
                    type: 'linear',
                    position: 'right',
                    title: { display: true, text: t.pendulum.chart.angleLabel },
                    grid: { drawOnChartArea: false },
                },
            },
        }),
        [t],
    );

    // Mutate the plugin option directly and call update('none') — this avoids
    // a full re-render of the chart on every animation frame. The `options`
    // object is stable across frame changes so we can safely mutate a leaf.
    // `cursorRatio` is NOT in useMemo's dep array intentionally — the plugin
    // reads the current value at draw time.
    if (options.plugins?.pendulumCursor !== undefined) {
        options.plugins.pendulumCursor.cursorRatio = cursorRatio;
    }

    return (
        <div className="w-full">
            <Line data={chartData} options={options} />
        </div>
    );
}
