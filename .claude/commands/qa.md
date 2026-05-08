---
description: Run every quality gate (pint, phpstan, pest, tsc, eslint, prettier, vitest, ruff, mypy, pytest), auto-fix what's auto-fixable, surface what isn't.
allowed-tools: Read, Edit, Bash, Task
---

Run the full quality gate suite for the WEBTE2 project.

Invoke the **qa-gate-runner** subagent with this brief:

> Run every quality gate in order:
>
> **PHP**: `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --no-progress`, `vendor/bin/pest --parallel`
>
> **JavaScript/TypeScript**: `npx tsc --noEmit`, `npx eslint . --max-warnings=0`, `npx prettier --check .`, `npx vitest run`
>
> **Python (octave bridge)**: `cd docker/octave-bridge && uv run ruff check . && uv run ruff format --check . && uv run mypy --strict . && uv run pytest`
>
> Auto-fix formatter and lint diffs in place. For non-auto-fixable failures (phpstan errors, type errors, test failures), produce the structured report per your output format.
>
> Refuse to silently work around: no additions to phpstan-baseline.neon, no `// @ts-ignore`, no skipped tests, no rule-severity downgrades.

After qa-gate-runner returns its report:

- **All green** → tell the user, recommend `/review` next.
- **Auto-fixes applied** → list them, recommend `git diff` to review the auto-fixes before committing.
- **Red gates with needs-thinking failures** → surface them prominently, recommend the right specialist to delegate the fix to (per qa-gate-runner's hypotheses).
