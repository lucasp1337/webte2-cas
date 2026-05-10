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

import type { BallBeamTrajectory } from '@/api/ballBeam';
import { useT } from '@/hooks/useT';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

// ---------------------------------------------------------------------------
// Cursor plugin — draws a vertical "now" line at the current frame index.
// Pattern mirrors PendulumChart's cursor plugin exactly; registered under a
// distinct id so both charts can coexist on the same page without conflict.
// ---------------------------------------------------------------------------

type CursorPluginOptions = {
    cursorRatio: number; // 0.0 – 1.0
};

const ballBeamCursorPlugin: Plugin<'line', CursorPluginOptions> = {
    id: 'ballBeamCursor',
    afterDraw(chart): void {
        // `chart.options.plugins` is widened to `Record<string, unknown>` in the
        // `afterDraw` callback — the cast is safe given our module augmentation.
        const pluginOptions = chart.options.plugins?.ballBeamCursor as CursorPluginOptions | undefined;
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

ChartJS.register(ballBeamCursorPlugin);

declare module 'chart.js' {
    interface PluginOptionsByType<TType extends import('chart.js').ChartType> {
        ballBeamCursor?: TType extends 'line' ? CursorPluginOptions : never;
    }
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

type BallBeamChartProps = {
    trajectory: BallBeamTrajectory | null;
    cursorIndex: number;
};

export default function BallBeamChart({ trajectory, cursorIndex }: BallBeamChartProps): ReactElement {
    const t = useT();

    // Derive chart data only when the trajectory changes — cursor movement is
    // handled cheaply via the plugin option, not a data rebuild.
    const chartData = useMemo(() => {
        if (trajectory === null) {
            return { labels: [], datasets: [] };
        }

        const labels = trajectory.samples.map((s) => s.t.toFixed(2));
        return {
            labels,
            datasets: [
                {
                    label: t.ballBeam.chart.positionLabel,
                    data: trajectory.samples.map((s) => s.position),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 1.5,
                    pointRadius: 0,
                    tension: 0.2,
                    yAxisID: 'yPosition',
                },
                {
                    label: t.ballBeam.chart.angleLabel,
                    data: trajectory.samples.map((s) => s.beam_angle),
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
                    text: t.ballBeam.chart.title,
                },
                // Cursor ratio is updated outside useMemo — see note below.
                ballBeamCursor: { cursorRatio: 0 },
            },
            scales: {
                x: {
                    title: { display: true, text: t.ballBeam.chart.timeLabel },
                    ticks: { maxTicksLimit: 10 },
                },
                yPosition: {
                    type: 'linear',
                    position: 'left',
                    title: { display: true, text: t.ballBeam.chart.positionLabel },
                },
                yAngle: {
                    type: 'linear',
                    position: 'right',
                    title: { display: true, text: t.ballBeam.chart.angleLabel },
                    grid: { drawOnChartArea: false },
                },
            },
        }),
        [t],
    );

    // Mutate the plugin option directly and call update('none') — avoids a full
    // re-render on every animation frame. cursorRatio is intentionally outside
    // useMemo's dep array; the plugin reads the current value at draw time.
    if (options.plugins?.ballBeamCursor !== undefined) {
        options.plugins.ballBeamCursor.cursorRatio = cursorRatio;
    }

    return (
        <div className="w-full rounded-md border border-border bg-surface p-3">
            <Line data={chartData} options={options} />
        </div>
    );
}
