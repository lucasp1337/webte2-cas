# Phase 07 — Ball on beam

**Duration**: 1 d
**Tier**: any
**Required reading**: `CLAUDE.md`, `docs/phases/phase-06-inverted-pendulum.md` (mirror its patterns), `docs/ARCHITECTURE.md` §§ 3.2, 6, 9

## Goal

Same architecture as Phase 06 with a different visual. Reuse the renderer interface, animation loop, chart cursor, continue-from-state mechanism, and `SimulationStarted` event.

## Definition of Done

- [ ] Parameter form for m, R, g, J, r, init velocity, init acceleration, t_end, dt
- [ ] Backend builds the Octave script from `gulicka.txt`, executes via the bridge, returns the trajectory
- [ ] Frontend animates the beam tilted by the angle and the ball sliding along it
- [ ] Chart shows ball position over time (and beam angle on a secondary axis if it doesn't clutter)
- [ ] Same play / pause / reset / continue-with-new-r controls as the pendulum page
- [ ] Renderer is `BallBeam2D` conforming to `AnimationRenderer<BallBeamFrame>`
- [ ] `RunBallBeamSimulation` action fires `SimulationStarted` event with `AnimationName::BallBeam`
- [ ] `ValidBallBeamParameters` custom rule used in the Form Request

## Prerequisites

Phase 06 complete (you reuse most of its scaffolding).

## Tasks

### 7.1 Backend mirrors Phase 06

`BallBeamParameters` DTO, `ValidBallBeamParameters` rule, `RunBallBeamSimulationRequest`, `RunBallBeamSimulationController`, `RunBallBeamSimulation` action — copy-edit-rename from the pendulum equivalents. Action dispatches `SimulationStarted` with `AnimationName::BallBeam`.

`resources/views/octave/ball-beam.blade.php`:

```
m = {{ $p->m }};
R = {{ $p->R }};
g = -9.8;
J = {{ $p->J }};
H = -m*g/(J/(R^2)+m);
A = [0 1 0 0; 0 0 H 0; 0 0 0 1; 0 0 0 0];
B = [0;0;0;1];
C = [1 0 0 0];
D = [0];
K = place(A,B,[-2+2i,-2-2i,-20,-80]);
N = -inv(C*inv(A-B*K)*B);
sys = ss(A-B*K,B,C,D);
t = 0:{{ $p->dt }}:{{ $p->t_end }};
r = {{ $p->r }};
@if ($init)
init_state = [{{ implode(';', $init) }}];
@else
init_state = [{{ $p->init_velocity }};0;{{ $p->init_acceleration }};0];
@endif
[y,t,x] = lsim(N*sys, r*ones(size(t)), t, init_state);
disp('---T---'); disp(t); disp('---END-T---');
disp('---Y---'); disp(y); disp('---END-Y---');
disp('---X---'); disp(x); disp('---END-X---');
```

Extend `TrajectoryParser` with `parseBallBeam(...)`.

### 7.2 Frontend: `BallBeam2D` renderer

```typescript
export type BallBeamFrame = { ballPosition: number; beamAngle: number };

const ANGLE_VISUAL_MULTIPLIER = 500; // real angle ≈ 1e-4 rad — invisible without amplification

export const BallBeam2D: AnimationRenderer<BallBeamFrame> = ({
  trajectory, frameIndex, width, height,
}) => {
  const frame = trajectory?.[frameIndex];
  if (!frame) return <EmptyStage width={width} height={height} />;

  const PX_PER_M = 300;
  const angleVisual = frame.beamAngle * ANGLE_VISUAL_MULTIPLIER;
  const beamLength = width * 0.7;
  const cx = width / 2;
  const cy = height / 2;
  // Ball position along the rotated beam:
  const bx = cx + frame.ballPosition * PX_PER_M * Math.cos(angleVisual);
  const by = cy + frame.ballPosition * PX_PER_M * Math.sin(angleVisual);

  return (
    <Stage width={width} height={height}>
      <Layer>
        <Rect
          x={cx - beamLength / 2}
          y={cy - 5}
          width={beamLength}
          height={10}
          fill="var(--color-on-surface)"
          rotation={angleVisual * 180 / Math.PI}
        />
        <Circle x={bx} y={by} radius={14} fill="var(--color-primary)" />
        <Line points={[cx-10, cy+30, cx+10, cy+30, cx, cy+5]} closed fill="var(--color-secondary)" />
      </Layer>
    </Stage>
  );
};
```

The visual angle is amplified for visibility. Label the canvas with a small note: "(beam tilt exaggerated for clarity)". The chart shows the **real** angle.

### 7.3 Page

`resources/js/Pages/BallBeam.tsx` mirrors `Pendulum.tsx` structure with the renderer and parameters swapped.

If, after this phase, `Pendulum.tsx` and `BallBeam.tsx` share more than ~80 lines of identical structure, extract a `<SimulationLayout>`. **Do not extract speculatively.**

### 7.4 Tests

Mirror Phase 06's test suite for ball-beam. The renderer test asserts the ball x-coordinate maps correctly along the tilted beam.

## Quality gates

- [ ] All tests green
- [ ] Manual: defaults run with r=0.25 → ball converges in ~2 s, chart matches the brief's reference
- [ ] Continue-with-new-r=0.5 starts where the previous run ended
- [ ] Beam tilt visible to the naked eye (multiplier doing its job)
- [ ] `Event::fake([SimulationStarted::class])` test confirms dispatch with `AnimationName::BallBeam`

## Risks

| Risk | Mitigation |
|---|---|
| Tiny real angle (~1e-4 rad) invisible | Visual multiplier + UI label saying "exaggerated" |
| Code duplication with Phase 06 | Extract `<SimulationLayout>` only if duplication is real (≥ ~80 lines) |

## Hand-off to next phase

Phase 09 listens to `SimulationStarted` events from both Phase 06 and this phase.

## Agent brief (copy-paste)

> Read `CLAUDE.md`, `docs/phases/phase-06-inverted-pendulum.md` for the patterns to mirror, and this phase markdown.
>
> Backend:
> - `BallBeamParameters` DTO + `ValidBallBeamParameters` rule
> - `RunBallBeamSimulationRequest`, `RunBallBeamSimulationController`, `RunBallBeamSimulation` action (must dispatch `SimulationStarted` with `AnimationName::BallBeam`)
> - `octave.ball-beam` Blade template
> - Extend `TrajectoryParser::parseBallBeam`
>
> Frontend:
> - `resources/js/Pages/BallBeam.tsx`
> - `resources/js/animations/BallBeam2D.tsx` with `ANGLE_VISUAL_MULTIPLIER = 500` and a UI label noting the exaggeration
> - `BallBeamChart` and `BallBeamParameterForm`
>
> If `Pendulum.tsx` and `BallBeam.tsx` share more than ~80 lines of identical structure after this phase, propose a `<SimulationLayout>` extraction in the PR description (do not extract speculatively).
>
> Run quality gates. Verify both reference graphs from `gulicka.txt` match.
>
> PR labelled `phase:07`.
