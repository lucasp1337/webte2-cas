import { zodResolver } from '@hookform/resolvers/zod';
import { type ReactElement } from 'react';
import { useForm } from 'react-hook-form';

import { ballBeamParametersSchema, type BallBeamParameters } from '@/api/ballBeam';
import Button from '@/Components/ui/Button';
import FieldError from '@/Components/ui/FieldError';
import { useT } from '@/hooks/useT';
import { cn } from '@/lib/cn';

/**
 * Default values from gulicka.m that produce a converging simulation.
 * ball_mass=0.11 kg, ball_radius=0.015 m, inertia=9.99e-6 kg·m²,
 * beam_length=1.0 m, gravity=9.81 m/s², reference_position=0.25 m.
 */
const DEFAULT_VALUES: BallBeamParameters = {
    ball_mass: 0.11,
    ball_radius: 0.015,
    inertia: 0.00000999,
    beam_length: 1.0,
    gravity: 9.81,
    reference_position: 0.25,
    initial_position: 0,
    initial_velocity: 0,
    initial_angle: 0,
    duration_seconds: 5,
    step_size: 0.05,
};

type BallBeamParameterFormProps = {
    /** Called when the user submits a fresh simulation run. */
    onRun: (parameters: BallBeamParameters) => void;
    /**
     * Called when the user submits "Restart with new r" — passes the current
     * (validated) form parameters to the page so it can continue from the
     * previous trajectory's final state with a new reference position.
     * Undefined means the button is not shown.
     */
    onRestartWithNewR?: ((parameters: BallBeamParameters) => void) | undefined;
    disabled?: boolean | undefined;
};

type FieldConfig = {
    name: keyof BallBeamParameters;
    symbol: string;
    unit: string;
    min?: number;
    max?: number;
    step?: number;
};

type SectionConfig = {
    key: string;
    labelKey: 'phys' | 'ref' | 'init' | 'disc';
    fields: FieldConfig[];
};

const SECTIONS: SectionConfig[] = [
    {
        key: 'phys',
        labelKey: 'phys',
        fields: [
            { name: 'ball_mass', symbol: 'm', unit: 'kg', min: 0.001, max: 100, step: 0.01 },
            { name: 'ball_radius', symbol: 'R', unit: 'm', min: 0.001, max: 1, step: 0.001 },
            { name: 'inertia', symbol: 'J', unit: 'kg·m²', min: 0.000001, max: 1, step: 0.000001 },
            { name: 'beam_length', symbol: 'L', unit: 'm', min: 0.001, max: 10, step: 0.1 },
            { name: 'gravity', symbol: 'g', unit: 'm/s²', min: 0.001, max: 30, step: 0.01 },
        ],
    },
    {
        key: 'ref',
        labelKey: 'ref',
        fields: [{ name: 'reference_position', symbol: 'r', unit: 'm', step: 0.05 }],
    },
    {
        key: 'init',
        labelKey: 'init',
        fields: [
            { name: 'initial_position', symbol: 'x₀', unit: 'm', step: 0.05 },
            { name: 'initial_velocity', symbol: 'ẋ₀', unit: 'm/s', step: 0.1 },
            { name: 'initial_angle', symbol: 'α₀', unit: 'rad', step: 0.01 },
        ],
    },
    {
        key: 'disc',
        labelKey: 'disc',
        fields: [
            { name: 'duration_seconds', symbol: 't_end', unit: 's', min: 0.001, max: 30, step: 0.5 },
            { name: 'step_size', symbol: 'dt', unit: 's', min: 0.001, max: 0.5, step: 0.001 },
        ],
    },
];

const SECTION_LABELS = {
    phys: 'Physical',
    ref: 'Reference',
    init: 'Initial conditions',
    disc: 'Discretisation',
};

export default function BallBeamParameterForm({
    onRun,
    onRestartWithNewR,
    disabled = false,
}: BallBeamParameterFormProps): ReactElement {
    const t = useT();

    const {
        register,
        handleSubmit,
        reset,
        formState: { errors },
    } = useForm<BallBeamParameters>({
        resolver: zodResolver(ballBeamParametersSchema),
        defaultValues: DEFAULT_VALUES,
    });

    const handleRunSubmit = (data: BallBeamParameters): void => {
        onRun(data);
    };

    const handleRestartClick = (): void => {
        if (onRestartWithNewR === undefined) return;
        void handleSubmit((data) => {
            onRestartWithNewR(data);
        })();
    };

    return (
        <form
            // eslint-disable-next-line @typescript-eslint/no-misused-promises
            onSubmit={handleSubmit(handleRunSubmit)}
            className="flex flex-col gap-0 rounded-md border border-border bg-surface-raised"
            aria-label={t.ballBeam.form.title}
            noValidate
        >
            {/* Card header */}
            <div className="flex items-center justify-between border-b border-border px-5 py-3">
                <span className="font-mono text-[11px] uppercase tracking-[0.08em] text-on-surface-muted">
                    {t.ballBeam.parametersTitle}
                </span>
                <button
                    type="button"
                    disabled={disabled}
                    onClick={() => {
                        reset(DEFAULT_VALUES);
                    }}
                    className="font-mono text-[11px] text-on-surface-faint transition-colors hover:text-on-surface disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {t.ballBeam.parametersReset}
                </button>
            </div>

            {/* Sections */}
            <div className="flex flex-col gap-0 px-5 py-4">
                {SECTIONS.map((section, si) => (
                    <div key={section.key} className={cn('flex flex-col gap-2', si < SECTIONS.length - 1 && 'mb-5')}>
                        {/* Section eyebrow */}
                        <div className="mb-2 border-b border-border pb-2 font-mono text-[11px] uppercase tracking-[0.08em] text-on-surface-muted">
                            {SECTION_LABELS[section.labelKey]}
                        </div>
                        {section.fields.map(({ name, symbol, unit, min, max, step }) => {
                            const error = errors[name];
                            return (
                                <div key={name} className="mb-[10px]">
                                    <label
                                        htmlFor={`ballbeam-${name}`}
                                        className="mb-[5px] flex items-center justify-between text-[12px] font-medium text-on-surface"
                                    >
                                        <span>{symbol}</span>
                                        <span className="font-mono text-[11px] font-normal text-on-surface-faint">
                                            {t.ballBeam.form.fields[name]}
                                        </span>
                                    </label>
                                    <div className="relative">
                                        <input
                                            id={`ballbeam-${name}`}
                                            type="number"
                                            step={step}
                                            min={min}
                                            max={max}
                                            aria-invalid={error !== undefined}
                                            {...register(name, { valueAsNumber: true })}
                                            className={cn(
                                                'w-full rounded border bg-surface-sunken px-[10px] py-[7px] pr-10',
                                                'font-mono text-[13px] text-on-surface',
                                                'transition-[border-color,box-shadow]',
                                                'focus:outline-none focus:ring-[3px] focus:ring-accent/20',
                                                error !== undefined
                                                    ? 'border-error focus:border-error'
                                                    : 'border-border focus:border-accent',
                                                'disabled:cursor-not-allowed disabled:opacity-60',
                                            )}
                                            disabled={disabled}
                                        />
                                        <span className="pointer-events-none absolute right-[10px] top-1/2 -translate-y-1/2 font-mono text-[11px] text-on-surface-faint">
                                            {unit}
                                        </span>
                                    </div>
                                    {error !== undefined && <FieldError>{error.message}</FieldError>}
                                </div>
                            );
                        })}
                    </div>
                ))}
            </div>

            {/* CTA row */}
            <div className="flex flex-col gap-2 border-t border-border px-5 py-4">
                <Button
                    type="submit"
                    size="lg"
                    disabled={disabled}
                    loading={disabled}
                    className="w-full justify-center"
                >
                    {disabled ? t.ballBeam.runningLabel : t.ballBeam.runButton}
                </Button>

                {onRestartWithNewR !== undefined && (
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={disabled}
                        onClick={handleRestartClick}
                        title={t.pendulum.player.restartWithNewRHint}
                        className="w-full justify-center"
                    >
                        {t.ballBeam.form.restartWithNewR}
                    </Button>
                )}
            </div>
        </form>
    );
}

/** Exported for tests only — the page does not need this. */
export { DEFAULT_VALUES as ballBeamDefaultValues };
