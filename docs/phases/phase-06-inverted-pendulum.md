# Phase 06 — Inverted pendulum

**Duration**: 2 d
**Tier**: **senior**
**Required reading**: `CLAUDE.md`, `docs/ARCHITECTURE.md` §§ 3.2, 6, 9

## Goal

Parameter form → backend simulation via Octave bridge → 2D animation synchronised with a position/angle chart. `SimulationStarted` event fires for the (Phase 09) stats listener. Renderer interface designed so a 3D version can drop in later without touching parents.

## Definition of Done

- [ ] Parameter form lets the user set M, m, b, I, g, l, r, init position, init angle, t_end, dt
- [ ] Backend constructs Octave script from `kyvadlo.txt` with parameters substituted, executes via the bridge, parses `t`, `y`, `x` matrices
- [ ] Frontend receives the full trajectory, animates the pendulum on Konva, shows Chart.js graph
- [ ] Vertical "now" cursor on the chart tracks the current animation frame
- [ ] Play / Pause / Reset / Restart-with-new-r controls
- [ ] "Restart with new r" continues from the last state (matches the brief: r=0.2 then r=0.5 starting from previous final state)
- [ ] Server-side slowdown coefficient honoured by the animation loop
- [ ] `RunPendulumSimulation` action fires `SimulationStarted` event
- [ ] Renderer is `Pendulum2D` conforming to `AnimationRenderer<PendulumFrame>`
- [ ] `ValidPendulumParameters` custom validation rule used in the Form Request

## Prerequisites

Phases 02, 03, 04, 05 complete.

## Tasks

### 6.1 Backend: parameters DTO + custom rule

`app/Data/PendulumParameters.php`:

```php
final class PendulumParameters extends Data
{
    public function __construct(
        public float $M,
        public float $m,
        public float $b,
        public float $I,
        public float $g,
        public float $l,
        public float $r,
        public float $init_position,
        public float $init_angle,
        public float $t_end,
        public float $dt,
    ) {}
}
```

`app/Rules/ValidPendulumParameters.php`:

```php
final class ValidPendulumParameters implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) { $fail('Parameters must be an object'); return; }
        $p = PendulumParameters::from($value);
        if ($p->M <= 0 || $p->M >= 1000) $fail('M out of range (0, 1000)');
        if ($p->m <= 0 || $p->m >= 1000) $fail('m out of range (0, 1000)');
        if ($p->I <= 0 || $p->I >= 100)  $fail('I out of range (0, 100)');
        if ($p->l <= 0 || $p->l >= 10)   $fail('l out of range (0, 10)');
        if ($p->g <= 0 || $p->g >= 30)   $fail('g out of range (0, 30)');
        if ($p->t_end <= 0 || $p->t_end > 30) $fail('t_end out of range (0, 30]');
        if ($p->dt < 0.001 || $p->dt > 0.5)   $fail('dt out of range [0.001, 0.5]');
        // ... etc
    }
}
```

`RunPendulumSimulationRequest`:

```php
public function rules(): array
{
    return [
        'parameters'      => ['required', 'array', new ValidPendulumParameters],
        'continue_from'   => ['nullable', 'array', 'size:4'],
        'continue_from.*' => ['numeric'],
    ];
}
```

### 6.2 Action

```php
final readonly class RunPendulumSimulation
{
    public function __construct(private OctaveBridgeClient $bridge) {}

    public function handle(
        PendulumParameters $params,
        ?array $initialState,
        string $anonToken,
        string $ip,
    ): SimulationTrajectory {
        SimulationStarted::dispatch(
            AnimationName::Pendulum,
            $anonToken,
            $ip,
            md5(serialize($params->toArray())),
        );

        $sessionId = 'sim-' . Str::ulid();
        $script = view('octave.pendulum', ['p' => $params, 'init' => $initialState])->render();
        $result = $this->bridge->execute($sessionId, $script, timeoutSeconds: 15);
        $this->bridge->clearSession($sessionId);

        return TrajectoryParser::parsePendulum($result->stdout, $params);
    }
}
```

### 6.3 Event

`app/Events/SimulationStarted.php`:

```php
final readonly class SimulationStarted
{
    use Dispatchable;
    public function __construct(
        public AnimationName $animation,
        public string $anonToken,
        public string $ip,
        public string $parameterHash,
    ) {}
}
```

`app/Enums/AnimationName.php`:

```php
enum AnimationName: string {
    case Pendulum = 'pendulum';
    case BallBeam = 'ball-beam';
}
```

### 6.4 Octave script template

`resources/views/octave/pendulum.blade.php`:

```
M = {{ $p->M }};
m = {{ $p->m }};
b = {{ $p->b }};
I = {{ $p->I }};
g = {{ $p->g }};
l = {{ $p->l }};
p = I*(M+m)+M*m*l^2;
A = [0 1 0 0; 0 -(I+m*l^2)*b/p (m^2*g*l^2)/p 0; 0 0 0 1; 0 -(m*l*b)/p m*g*l*(M+m)/p 0];
B = [ 0; (I+m*l^2)/p; 0; m*l/p];
C = [1 0 0 0; 0 0 1 0];
D = [0; 0];
K = lqr(A,B,C'*C,1);
Ac = (A-B*K);
N = -inv(C(1,:)*inv(A-B*K)*B);
sys = ss(Ac,B*N,C,D);
t = 0:{{ $p->dt }}:{{ $p->t_end }};
r = {{ $p->r }};
@if ($init)
init_state = [{{ implode(';', $init) }}];
@else
init_state = [{{ $p->init_position }};0;{{ $p->init_angle }};0];
@endif
[y,t,x] = lsim(sys, r*ones(size(t)), t, init_state);
disp('---T---'); disp(t); disp('---END-T---');
disp('---Y---'); disp(y); disp('---END-Y---');
disp('---X---'); disp(x); disp('---END-X---');
```

`TrajectoryParser` extracts the three matrices between markers.

### 6.5 Frontend: page + renderer interface

`resources/js/animations/types.ts`:

```typescript
export type AnimationRenderer<TFrame> = ComponentType<{
  trajectory: TFrame[] | null;
  frameIndex: number;
  width: number;
  height: number;
}>;

export type PendulumFrame = { cartPosition: number; angle: number };

export const pendulumStateAt = (tr: PendulumTrajectory, idx: number): PendulumFrame => ({
  cartPosition: tr.x[idx]?.[0] ?? 0,
  angle:        tr.x[idx]?.[2] ?? 0,
});
```

`resources/js/Pages/Pendulum.tsx`:

```typescript
type PendulumProps = { defaults: PendulumParameters; slowdownFactor: number };

export default function Pendulum({ defaults, slowdownFactor }: PendulumProps) {
  const t = useT();
  const [params, setParams] = useState<PendulumParameters>(defaults);
  const [trajectory, setTrajectory] = useState<PendulumTrajectory | null>(null);
  const [frame, setFrame] = useState<number>(0);
  const [isPlaying, setPlaying] = useState<boolean>(false);

  useAnimationLoop(isPlaying, trajectory?.t.length ?? 0, params.dt, slowdownFactor, setFrame);

  const run = async (continueFrom?: number[]) => {
    const tr = await runPendulumSimulation(params, continueFrom);
    setTrajectory(tr); setFrame(0); setPlaying(true);
  };

  return (
    <AppLayout title={t.pendulum.title}>
      <div className="grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-6">
        <PendulumParameterForm value={params} onChange={setParams} onRun={run} />
        <div>
          <PendulumAnimation
            renderer={Pendulum2D}
            trajectory={trajectory && trajectory.frames}
            frameIndex={frame}
            width={800}
            height={300}
          />
          <PlayerControls
            isPlaying={isPlaying}
            onTogglePlay={() => setPlaying(p => !p)}
            onReset={() => { setFrame(0); setPlaying(false); }}
          />
          <PendulumChart trajectory={trajectory} cursorFrame={frame} />
        </div>
      </div>
    </AppLayout>
  );
}
```

### 6.6 `Pendulum2D` (Konva)

```typescript
export const Pendulum2D: AnimationRenderer<PendulumFrame> = ({
  trajectory, frameIndex, width, height,
}) => {
  const frame = trajectory?.[frameIndex];
  if (!frame) return <EmptyStage width={width} height={height} />;

  const PX_PER_M = 200;
  const cartX = width / 2 + frame.cartPosition * PX_PER_M;
  const bobX = cartX + Math.sin(frame.angle) * PX_PER_M * 0.5;
  const bobY = height / 2 - Math.cos(frame.angle) * PX_PER_M * 0.5;

  return (
    <Stage width={width} height={height}>
      <Layer>
        <Line points={[0, height/2, width, height/2]} stroke="currentColor" />
        <Rect x={cartX - 30} y={height/2 - 15} width={60} height={30} fill="var(--color-primary)" />
        <Line points={[cartX, height/2, bobX, bobY]} stroke="var(--color-on-surface)" strokeWidth={3} />
        <Circle x={bobX} y={bobY} radius={12} fill="var(--color-secondary)" />
      </Layer>
    </Stage>
  );
};
```

(`Pendulum3D` is a Phase 10 stretch; leave a `TODO` comment placeholder.)

### 6.7 Animation loop

`useAnimationLoop`:

```typescript
export function useAnimationLoop(
  isPlaying: boolean,
  totalFrames: number,
  dt: number,
  slowdownFactor: number,
  onFrame: (i: number) => void,
) {
  useEffect(() => {
    if (!isPlaying || totalFrames === 0) return;
    let raf = 0;
    let lastWall = performance.now();
    let simElapsed = 0;
    const step = (now: number) => {
      simElapsed += ((now - lastWall) / 1000) / slowdownFactor;
      lastWall = now;
      const idx = Math.min(Math.floor(simElapsed / dt), totalFrames - 1);
      onFrame(idx);
      if (idx < totalFrames - 1) raf = requestAnimationFrame(step);
    };
    raf = requestAnimationFrame(step);
    return () => cancelAnimationFrame(raf);
  }, [isPlaying, totalFrames, dt, slowdownFactor, onFrame]);
}
```

### 6.8 Chart sync

`PendulumChart` wraps Chart.js — two datasets (position, angle) plus a custom plugin drawing a vertical line at `t[cursorFrame]`.

### 6.9 Continue-from-final-state

When the user clicks "Run again" with a new `r`, the form passes `continueFrom = trajectory.finalState` (4 values) to the backend, which feeds it as `init_state`. Matches the brief's example.

### 6.10 Tests

**Backend**:
- `RunPendulumSimulationTest`: happy path with `FakeOctaveBridgeClient`; parser produces expected shape; `Event::fake([SimulationStarted::class])` asserts dispatch
- `TrajectoryParserTest`: golden-file against captured Octave output
- `ValidPendulumParametersTest`: every bound rejected when violated
- `EndToEndPendulumTest` (CI integration): real bridge run with default params, asserts cart converges to `r` within tolerance

**Frontend**:
- `pendulumStateAt` extracts the right frame
- `useAnimationLoop` advances frame correctly (Vitest fake timers)
- `Pendulum2D` renders the cart at the expected x

## Quality gates

- [ ] All tests green
- [ ] Manual: defaults run → cart converges to r=0.2 in ~5 s, chart matches reference
- [ ] Manual: change r to 0.5, click Run → continues from previous final state
- [ ] Animation cursor and chart cursor stay locked (eye-test by pausing)
- [ ] At slowdown 2, animation visibly slower

## Risks

| Risk | Mitigation |
|---|---|
| Octave matrix output parsing | `---T---` / `---END-T---` markers; bounded slice; golden tests |
| Konva perf with 5000 frames | Single Stage, no per-frame shape recreation |
| Float precision in continue-from | Serialise with `sprintf('%.15e', ...)` in the Octave template |

## Hand-off to next phase

Phase 07 reuses: action/event pattern, renderer interface, animation loop, parameter form pattern, parser. Phase 09 listens to `SimulationStarted`.

## Agent brief (copy-paste)

> Read `CLAUDE.md`, `docs/ARCHITECTURE.md` §§ 3.2, 6, 9, and this phase markdown.
>
> Backend:
> - `PendulumParameters` DTO + `ValidPendulumParameters` rule
> - `RunPendulumSimulationRequest`, `RunPendulumSimulationController`, `RunPendulumSimulation` action
> - `SimulationStarted` event + `AnimationName` enum
> - `octave.pendulum` Blade template
> - `TrajectoryParser` with golden-file tests
>
> Frontend:
> - `resources/js/animations/{types,Pendulum2D}.tsx` + `useAnimationLoop`
> - `resources/js/Pages/Pendulum.tsx`
> - `PendulumChart` (Chart.js + cursor plugin)
> - `PendulumParameterForm` (React Hook Form + Zod mirroring backend rules)
>
> Do NOT implement `Pendulum3D` here. Leave the renderer interface clean.
>
> Action MUST dispatch `SimulationStarted` before calling the bridge. Phase 09 depends on that event.
>
> Run all quality gates. Manually verify against `kyvadlo.txt` reference.
>
> PR labelled `phase:06`.
