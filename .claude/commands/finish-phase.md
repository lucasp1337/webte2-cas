---
description: Wrap up a phase before merging. Walks the Definition of Done, runs qa-gate-runner, runs code-reviewer, drafts the final PR description, surfaces what's blocking merge.
argument-hint: <phase-number>
allowed-tools: Read, Bash, Write, Grep, Glob, Task
---

Finish phase **$ARGUMENTS** for the WEBTE2 project.

## Step 1 — Normalise the argument

Same rules as `/start-phase`: accept `3`, `03`, `phase-03`, normalise to `03`. If invalid, stop and list the phases.

## Step 2 — Locate the phase doc

```bash
ls docs/phases/phase-<NN>-*.md
```

Read it end to end.

## Step 3 — Walk the Definition of Done

For every checkbox in the phase doc's "Definition of Done" section, look for evidence in the codebase that it's done. Use `Grep`, `Glob`, and `Read` aggressively.

Produce a table:

| DoD item | Status | Evidence |
|---|---|---|
| `ApiKeyMiddleware` rejects requests without a valid `X-API-Key` | ✓ done | `app/Http/Middleware/ApiKeyMiddleware.php:18` |
| `ApiKeyMiddleware` fires `ApiKeyUsed` event after successful auth | ✓ done | `app/Http/Middleware/ApiKeyMiddleware.php:27` |
| `UpdateApiKeyLastUsedListener` (queued) updates `last_used_at` | ✓ done | `app/Listeners/UpdateApiKeyLastUsedListener.php:14` |
| Feature tests for every endpoint | ✗ partial | `tests/Feature/Auth/` has 3 of 6 expected files |
| ... | ... | ... |

If the codebase doesn't yet support the check (e.g., the phase isn't actually implemented), stop early and say so.

## Step 4 — Run the quality gates

Invoke the **qa-gate-runner** subagent. Capture its report.

If gates are red, **stop here**. Do not proceed to code review on a red diff. Surface the failures and recommend the appropriate specialist.

## Step 5 — Run the code review

If gates are green, invoke the **code-reviewer** subagent with scope `branch` (i.e., `git diff main...HEAD`). Capture its report.

If there are **must-fix** findings, stop here. Surface them and recommend the right specialist to delegate to.

## Step 6 — Draft the PR description

Using the template from `CLAUDE.md § 12`, fill in:

```markdown
## What
<one-paragraph summary of phase <NN> — pull from the phase doc's "Goal">

## Why
Implements `docs/phases/phase-<NN>-*.md`.

## How
<2–4 sentences on notable design decisions specific to this phase>
<call out any deviations from the phase doc with reasoning>

## Quality gates
- [x] Pint
- [x] PHPStan max
- [x] Pest (X tests, Y assertions)
- [x] tsc --noEmit
- [x] ESLint --max-warnings=0
- [x] Prettier --check
- [x] Vitest (X tests)
- [x] mypy --strict <if Python touched>
- [x] Manually tested in Chrome and Firefox
- [x] Mobile viewport (375 px) sanity-checked

## Risks
<from the phase doc's Risks table; mention any that materialised>

## Hand-off
<from the phase doc's "Hand-off to next phase" — confirm what's now in place for downstream phases>
```

Save the draft to `docs/phases/.work/phase-<NN>-pr.md`.

## Step 7 — Final report

End with a clear verdict:

- **Ready to merge** — DoD complete, gates green, no must-fix review items. Tell the user the PR draft is at `docs/phases/.work/phase-<NN>-pr.md` and they can `gh pr create --body-file <path>` from there.
- **Blocked by DoD gaps** — list the unfinished items, recommend the right specialist for each.
- **Blocked by quality gates** — list the failing gates, recommend `qa-gate-runner` triage.
- **Blocked by review findings** — list the must-fix items, recommend the right specialist.

Don't merge. The user (or the human reviewer) makes that call.
