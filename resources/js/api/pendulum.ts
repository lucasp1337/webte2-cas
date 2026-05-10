import { z } from 'zod';

// ---------------------------------------------------------------------------
// Schemas — mirror the backend ValidPendulumParameters rule bounds exactly.
// Bounds: duration_seconds ∈ (0, 30], step_size ∈ [0.001, 0.5].
// ---------------------------------------------------------------------------

export const pendulumParametersSchema = z.object({
    cart_mass: z.number().positive(),
    pendulum_mass: z.number().positive(),
    friction: z.number().min(0),
    inertia: z.number().positive(),
    gravity: z.number().positive(),
    length: z.number().positive(),
    reference_position: z.number().finite(),
    initial_position: z.number().finite(),
    initial_angle: z.number().finite(),
    duration_seconds: z.number().positive().max(30),
    step_size: z.number().min(0.001).max(0.5),
});

export type PendulumParameters = z.infer<typeof pendulumParametersSchema>;

export const pendulumTrajectorySchema = z.object({
    request_id: z.string(),
    animation: z.literal('pendulum'),
    duration_seconds: z.number(),
    step_size: z.number(),
    samples: z.array(
        z.object({
            t: z.number(),
            x: z.number(),
            theta: z.number(),
        }),
    ),
    final_state: z.tuple([z.number(), z.number(), z.number(), z.number()]),
});

export type PendulumTrajectory = z.infer<typeof pendulumTrajectorySchema>;

// ---------------------------------------------------------------------------
// Error type
// ---------------------------------------------------------------------------

export class SimulationApiError extends Error {
    readonly httpStatus: number;
    readonly body: unknown;

    constructor(message: string, httpStatus: number, body: unknown) {
        super(message);
        this.name = 'SimulationApiError';
        this.httpStatus = httpStatus;
        this.body = body;
    }
}

// ---------------------------------------------------------------------------
// API client
// ---------------------------------------------------------------------------

const PENDULUM_PATH = '/api/v1/simulations/pendulum';

type RunPendulumInput = {
    parameters: PendulumParameters;
    continue_from?: [number, number, number, number];
    apiKey: string;
    /** Optional fetch override for tests. */
    fetcher?: typeof fetch;
};

/**
 * Posts to `/api/v1/simulations/pendulum` and returns a validated trajectory.
 *
 * The `continue_from` tuple carries the previous trajectory's `final_state`
 * (`[x, x_dot, theta, theta_dot]`), enabling the "Restart with new r" flow.
 *
 * Throws `SimulationApiError` for any non-2xx response.
 */
export async function runPendulumSimulation({
    parameters,
    continue_from,
    apiKey,
    fetcher = globalThis.fetch.bind(globalThis),
}: RunPendulumInput): Promise<PendulumTrajectory> {
    const body: Record<string, unknown> = { parameters };
    if (continue_from !== undefined) {
        body.continue_from = continue_from;
    }

    const response = await fetcher(PENDULUM_PATH, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-API-Key': apiKey,
        },
        body: JSON.stringify(body),
    });

    const rawJson: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        throw new SimulationApiError(
            `Pendulum simulation failed: HTTP ${response.status.toString()}`,
            response.status,
            rawJson,
        );
    }

    return pendulumTrajectorySchema.parse(rawJson);
}
