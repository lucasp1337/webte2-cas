import { zodResolver } from '@hookform/resolvers/zod';
import { type ReactElement } from 'react';
import { useForm } from 'react-hook-form';

import { ballBeamParametersSchema, type BallBeamParameters } from '@/api/ballBeam';
import Button from '@/Components/ui/Button';
import FieldError from '@/Components/ui/FieldError';
import Input from '@/Components/ui/Input';
import Label from '@/Components/ui/Label';
import { useT } from '@/hooks/useT';

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
    min?: number;
    max?: number;
    step?: number;
};

const FIELDS: FieldConfig[] = [
    { name: 'ball_mass', min: 0.001, max: 100, step: 0.01 },
    { name: 'ball_radius', min: 0.001, max: 1, step: 0.001 },
    { name: 'inertia', min: 0.000001, max: 1, step: 0.000001 },
    { name: 'beam_length', min: 0.001, max: 10, step: 0.1 },
    { name: 'gravity', min: 0.001, max: 30, step: 0.01 },
    { name: 'reference_position', step: 0.05 },
    { name: 'initial_position', step: 0.05 },
    { name: 'initial_velocity', step: 0.1 },
    { name: 'initial_angle', step: 0.01 },
    { name: 'duration_seconds', min: 0.001, max: 30, step: 0.5 },
    { name: 'step_size', min: 0.001, max: 0.5, step: 0.001 },
];

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
            className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-4"
            aria-label={t.ballBeam.form.title}
            noValidate
        >
            <h2 className="text-base font-semibold text-on-surface">{t.ballBeam.form.title}</h2>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {FIELDS.map(({ name, min, max, step }) => {
                    const error = errors[name];
                    return (
                        <div key={name} className="flex flex-col gap-1">
                            <Label htmlFor={`ballbeam-${name}`} required>
                                {t.ballBeam.form.fields[name]}
                            </Label>
                            <Input
                                id={`ballbeam-${name}`}
                                type="number"
                                step={step}
                                min={min}
                                max={max}
                                error={error !== undefined}
                                {...register(name, { valueAsNumber: true })}
                            />
                            <FieldError>{error?.message}</FieldError>
                        </div>
                    );
                })}
            </div>

            <div className="flex flex-wrap gap-2 pt-2">
                <Button type="submit" disabled={disabled}>
                    {disabled ? t.ballBeam.form.running : t.ballBeam.form.run}
                </Button>

                {onRestartWithNewR !== undefined && (
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={disabled}
                        onClick={handleRestartClick}
                        title={t.pendulum.player.restartWithNewRHint}
                    >
                        {t.ballBeam.form.restartWithNewR}
                    </Button>
                )}

                <Button
                    type="button"
                    variant="ghost"
                    disabled={disabled}
                    onClick={() => {
                        reset(DEFAULT_VALUES);
                    }}
                >
                    {t.ballBeam.form.reset}
                </Button>
            </div>
        </form>
    );
}

/** Exported for tests only — the page does not need this. */
export { DEFAULT_VALUES as ballBeamDefaultValues };
