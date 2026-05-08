# Phase 05 — Octave console

**Duration**: 1 d
**Tier**: any
**Required reading**: `CLAUDE.md`, `docs/ARCHITECTURE.md` §§ 3.1, 4, 6

## Goal

A working "type Octave commands, hit run, see output" page with syntax highlighting, persistent workspace, and the `OctaveCommandExecuted` event firing for downstream metrics.

## Definition of Done

- [ ] CodeMirror 6 editor with Octave/MATLAB syntax highlighting
- [ ] Submit (button + Ctrl+Enter) sends `command` + `console_session_id` to `/api/v1/octave/exec`
- [ ] Output panel shows stdout (default) and stderr (red), monospace, with duration
- [ ] "Current variables" sidebar populated by calling `who` after each successful exec
- [ ] Sequential commands share state: `a = 1+1` then `a+2` returns `4`
- [ ] Loading spinner during exec; submit disabled while pending
- [ ] Errors render in a non-shouty red bordered panel (not toast)
- [ ] "Clear session" button resets the workspace via `DELETE /api/v1/octave/session`
- [ ] `ExecuteOctaveCommand` action fires `OctaveCommandExecuted` event
- [ ] `RecordOctaveMetricsListener` (queued) increments cache counters

## Prerequisites

Phases 02, 03, 04 complete.

## Tasks

### 5.1 Backend: real `ExecuteOctaveCommandController`

Replace the Phase 03 stub:

```php
final class ExecuteOctaveCommandController
{
    public function __construct(private readonly ExecuteOctaveCommand $action) {}

    public function __invoke(ExecuteOctaveCommandRequest $request): OctaveExecutionResource
    {
        return OctaveExecutionResource::make(
            $this->action->handle($request->consoleSessionId(), $request->validated('command'))
        );
    }
}
```

`ExecuteOctaveCommandRequest`:

```php
public function rules(): array
{
    return ['command' => ['required', 'string', 'max:4096']];
}

public function consoleSessionId(): string
{
    return session()->get('console_session_id')
        ?? tap((string) Str::uuid(), fn ($id) => session(['console_session_id' => $id]));
}
```

`ExecuteOctaveCommand` action:

```php
final readonly class ExecuteOctaveCommand
{
    public function __construct(private OctaveBridgeClient $bridge) {}

    public function handle(string $sessionId, string $command): OctaveExecutionResult
    {
        try {
            $result = $this->bridge->execute($sessionId, $command);
            OctaveCommandExecuted::dispatch($result, $sessionId);
            return $result;
        } catch (OctaveCommandRejectedException $e) {
            return OctaveExecutionResult::rejected($e->getMessage());
        } catch (OctaveTimeoutException) {
            return OctaveExecutionResult::timedOut();
        }
        // OctaveBridgeUnavailableException propagates → handler returns 503
    }
}
```

### 5.2 Event + listener

`app/Events/OctaveCommandExecuted.php`:

```php
final readonly class OctaveCommandExecuted
{
    use Dispatchable;
    public function __construct(
        public OctaveExecutionResult $result,
        public string $sessionId,
    ) {}
}
```

`app/Listeners/RecordOctaveMetricsListener.php`:

```php
final class RecordOctaveMetricsListener implements ShouldQueue
{
    public int $tries = 3;

    public function handle(OctaveCommandExecuted $event): void
    {
        Cache::increment('metrics:octave:total');
        if ($event->result->isSuccessful()) {
            Cache::increment('metrics:octave:successful');
        } else {
            Cache::increment('metrics:octave:failed');
        }
        Cache::increment('metrics:octave:duration_ms_total', $event->result->durationMs);
    }
}
```

(These counters can power a future "system health" page; for now they're plumbing, demonstrating the pattern.)

### 5.3 Clear-session endpoint

`ClearOctaveSessionController`:

```php
public function __invoke(Request $request, OctaveBridgeClient $bridge): Response
{
    $sessionId = $request->session()->pull('console_session_id');
    if ($sessionId !== null) {
        $bridge->clearSession($sessionId);
    }
    return response()->noContent();
}
```

### 5.4 Frontend: console page

`resources/js/Pages/Console.tsx`:

```typescript
type ConsoleProps = { apiKey: string };

export default function Console({ apiKey }: ConsoleProps) {
  const t = useT();
  const [code, setCode] = useState<string>('');
  const [output, setOutput] = useState<OctaveResult[]>([]);
  const [variables, setVariables] = useState<string[]>([]);
  const [isPending, setPending] = useState<boolean>(false);

  const submit = useCallback(async () => {
    if (!code.trim() || isPending) return;
    setPending(true);
    try {
      const result = await runCommand(code);
      setOutput(prev => [...prev, result]);
      setVariables(await listVariables());
    } finally {
      setPending(false);
    }
  }, [code, isPending]);

  useHotkeys('ctrl+enter,meta+enter', submit, { enableOnFormTags: true });

  return (
    <AppLayout title={t.console.title}>
      <div className="grid grid-cols-1 lg:grid-cols-[1fr_240px] gap-4">
        <div>
          <CodeMirror
            value={code}
            extensions={[StreamLanguage.define(octave)]}
            theme={useTheme()[0] === 'dark' ? oneDark : 'light'}
            onChange={setCode}
            height="240px"
          />
          <Button onClick={submit} disabled={isPending}>
            {isPending ? <Spinner /> : t.console.run}
          </Button>
          <OutputPanel results={output} />
        </div>
        <VariableSidebar variables={variables} />
      </div>
    </AppLayout>
  );
}
```

`runCommand` is a thin `fetch` wrapper that adds the API key. `listVariables` calls `runCommand('who')` and parses `stdout`.

### 5.5 OutputPanel

Each entry: command (dimmed monospace) + stdout + stderr (red) + duration ms. Rejected commands: red bordered panel with reason. Latest entry first.

### 5.6 Tests

**Backend**:
- `ExecuteOctaveCommandControllerTest`: happy path; 422 on rejection; 504 on timeout; 503 on bridge down; `Event::fake([OctaveCommandExecuted::class])` asserts dispatch on success
- `RecordOctaveMetricsListenerTest`: direct call asserts cache counters incremented
- `ConsoleSessionPersistenceTest`: two sequential calls share `sessionId` (assert against `FakeOctaveBridgeClient`)
- `ClearOctaveSessionControllerTest`: clears session; subsequent exec gets a new session id

**Frontend**:
- `Console.test.tsx`: renders editor, submits on click and Ctrl+Enter, displays output, variable sidebar refreshes after run

## Quality gates

- [ ] `composer qa` + `npm run qa` green
- [ ] Manual: `a = 1+1` → run, `a+2` → returns `ans = 4`
- [ ] Manual: `system("ls")` → 422 with rejection reason
- [ ] Horizon shows `RecordOctaveMetricsListener` ran
- [ ] `redis-cli get metrics:octave:total` reflects manual runs

## Risks

| Risk | Mitigation |
|---|---|
| CSP blocking CodeMirror inline styles | Allow `style-src 'self' 'unsafe-inline'` only on console + api-docs routes (Phase 10 ships strict CSP elsewhere) |
| `who` output format varies by Octave version | Pin Octave version in bridge image; capture golden file in tests |

## Hand-off to next phase

Phases 06/07 reuse: `ExecuteOctaveCommandRequest` patterns (Form Request shape), the bridge client, the action+event pattern.

## Agent brief (copy-paste)

> Read `CLAUDE.md`, `docs/ARCHITECTURE.md` §§ 3.1, 4, 6, and this phase markdown.
>
> Implement the real `ExecuteOctaveCommandController`, the action with `OctaveCommandExecuted` event dispatch, the form request, the resource. Add `ClearOctaveSessionController`.
>
> Implement the event class and `RecordOctaveMetricsListener` (queued, increments Redis cache counters).
>
> Frontend: `resources/js/Pages/Console.tsx`, `OutputPanel`, `VariableSidebar`, `resources/js/api/octave.ts` for the fetch wrappers.
>
> Tests: `Event::fake` for dispatch, direct `handle()` for the listener, `FakeOctaveBridgeClient` for the action, Vitest for the page.
>
> Verify metrics counters increment in Redis after a manual run.
>
> PR labelled `phase:05`.
