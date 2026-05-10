import { z } from 'zod';

import { SimulationApiError } from '@/api/pendulum';

export { SimulationApiError };

// ---------------------------------------------------------------------------
// Schemas — mirror the backend ValidBallBeamParameters rule bounds exactly.
// Bounds per phase-07-plan.md: m∈(0,100), R∈(0,1), J∈(0,1),
// beam_length∈(0,10), gravity∈(0,30), duration_seconds∈(0,30], step_size∈[0.001,0.5].
// ---------------------------------------------------------------------------

export const ballBeamParametersSchema = z.object({
    ball_mass: z.number().positive().max(100),
    ball_radius: z.number().positive().max(1),
    inertia: z.number().positive().max(1),
    beam_length: z.number().positive().max(10),
    gravity: z.number().positive().max(30),
    reference_position: z.number().finite(),
    initial_position: z.number().finite(),
    initial_velocity: z.number().finite(),
    initial_angle: z.number().finite(),
    duration_seconds: z.number().positive().max(30),
    step_size: z.number().min(0.001).max(0.5),
});

export type BallBeamParameters = z.infer<typeof ballBeamParametersSchema>;

export const ballBeamTrajectorySchema = z.object({
    request_id: z.string(),
    animation: z.literal('ball-beam'),
    duration_seconds: z.number(),
    step_size: z.number(),
    samples: z.array(
        z.object({
            t: z.number(),
            position: z.number(),
            beam_angle: z.number(),
        }),
    ),
    final_state: z.tuple([z.number(), z.number(), z.number(), z.number()]),
});

export type BallBeamTrajectory = z.infer<typeof ballBeamTrajectorySchema>;

// ---------------------------------------------------------------------------
// API client
// ---------------------------------------------------------------------------

const BALL_BEAM_PATH = '/api/v1/simulations/ball-beam';

type RunBallBeamInput = {
    parameters: BallBeamParameters;
    continue_from?: [number, number, number, number];
    apiKey: string;
    /** Optional fetch override for tests. */
    fetcher?: typeof fetch;
};

/**
 * Posts to `/api/v1/simulations/ball-beam` and returns a validated trajectory.
 *
 * The `continue_from` tuple carries the previous trajectory's `final_state`
 * (`[position, velocity, angle, angular_velocity]`), enabling the
 * "Restart with new r" flow.
 *
 * Throws `SimulationApiError` for any non-2xx response.
 */
export async function runBallBeamSimulation({
    parameters,
    continue_from,
    apiKey,
    fetcher = globalThis.fetch.bind(globalThis),
}: RunBallBeamInput): Promise<BallBeamTrajectory> {
    const body: Record<string, unknown> = { parameters };
    if (continue_from !== undefined) {
        body.continue_from = continue_from;
    }

    const response = await fetcher(BALL_BEAM_PATH, {
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
            `Ball-beam simulation failed: HTTP ${response.status.toString()}`,
            response.status,
            rawJson,
        );
    }

    return ballBeamTrajectorySchema.parse(rawJson);
}
