import { zodResolver } from '@hookform/resolvers/zod';
import { type ReactElement } from 'react';
import { useForm } from 'react-hook-form';

import { pendulumParametersSchema, type PendulumParameters } from '@/api/pendulum';
import Button from '@/Components/ui/Button';
import FieldError from '@/Components/ui/FieldError';
import Input from '@/Components/ui/Input';
import Label from '@/Components/ui/Label';
import { useT } from '@/hooks/useT';

/**
 * Default values that produce a converging simulation in roughly 5 s.
 * These mirror the openapi.yaml example for the PendulumRequest schema.
 */
const DEFAULT_VALUES: PendulumParameters = {
    cart_mass: 1.0,
    pendulum_mass: 0.2,
    friction: 0.1,
    inertia: 0.006,
    gravity: 9.81,
    length: 0.5,
    reference_position: 0.2,
    initial_position: 0.0,
    initial_angle: 0.15,
    duration_seconds: 10.0,
    step_size: 0.02,
};

type PendulumParameterFormProps = {
    /** Called when the user submits a fresh simulation run. */
    onRun: (parameters: PendulumParameters) => void;
    /**
     * Called when the user submits "Restart with new r" — passes the current
     * (validated) form parameters to the page so it can continue from the
     * previous trajectory's final state with a new reference position.
     * Undefined means the button is not shown.
     */
    onRestartWithNewR?: ((parameters: PendulumParameters) => void) | undefined;
    disabled?: boolean | undefined;
};

type FieldConfig = {
    name: keyof PendulumParameters;
    min?: number;
    max?: number;
    step?: number;
};

const FIELDS: FieldConfig[] = [
    { name: 'cart_mass', min: 0.001, step: 0.1 },
    { name: 'pendulum_mass', min: 0.001, step: 0.01 },
    { name: 'friction', min: 0, step: 0.01 },
    { name: 'inertia', min: 0.001, step: 0.001 },
    { name: 'gravity', min: 0.001, step: 0.01 },
    { name: 'length', min: 0.001, step: 0.01 },
    { name: 'reference_position', step: 0.1 },
    { name: 'initial_position', step: 0.1 },
    { name: 'initial_angle', step: 0.01 },
    { name: 'duration_seconds', min: 0.001, max: 30, step: 0.5 },
    { name: 'step_size', min: 0.001, max: 0.5, step: 0.001 },
];

export default function PendulumParameterForm({
    onRun,
    onRestartWithNewR,
    disabled = false,
}: PendulumParameterFormProps): ReactElement {
    const t = useT();

    const {
        register,
        handleSubmit,
        reset,
        formState: { errors },
    } = useForm<PendulumParameters>({
        resolver: zodResolver(pendulumParametersSchema),
        defaultValues: DEFAULT_VALUES,
    });

    const handleRunSubmit = (data: PendulumParameters): void => {
        onRun(data);
    };

    const handleRestartClick = (): void => {
        if (onRestartWithNewR === undefined) return;
        // Validate first, then pass current values.
        void handleSubmit((data) => {
            onRestartWithNewR(data);
        })();
    };

    return (
        <form
            // eslint-disable-next-line @typescript-eslint/no-misused-promises
            onSubmit={handleSubmit(handleRunSubmit)}
            className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-4"
            aria-label={t.pendulum.form.title}
            noValidate
        >
            <h2 className="text-base font-semibold text-on-surface">{t.pendulum.form.title}</h2>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {FIELDS.map(({ name, min, max, step }) => {
                    const error = errors[name];
                    return (
                        <div key={name} className="flex flex-col gap-1">
                            <Label htmlFor={`pendulum-${name}`} required>
                                {t.pendulum.fields[name]}
                            </Label>
                            <Input
                                id={`pendulum-${name}`}
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
                    {disabled ? t.pendulum.form.running : t.pendulum.form.run}
                </Button>

                {onRestartWithNewR !== undefined && (
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={disabled}
                        onClick={handleRestartClick}
                        title={t.pendulum.player.restartWithNewRHint}
                    >
                        {t.pendulum.player.restartWithNewR}
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
                    {t.pendulum.form.reset}
                </Button>
            </div>
        </form>
    );
}

/** Exported for tests only — the page does not need this. */
export { DEFAULT_VALUES as pendulumDefaultValues };
