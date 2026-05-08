---
description: Kick off a phase. Validates the working tree, creates the branch, hands off to phase-coordinator for an ordered task plan, and persists the plan for reference mid-phase.
argument-hint: <phase-number>
allowed-tools: Read, Bash, Write, Glob, Grep, Task
---

Start work on phase **$ARGUMENTS** for the WEBTE2 project.

## Step 1 — Normalise and validate the argument

Acceptable forms: `3`, `03`, `phase-03`, `phase 03`. Normalise to a two-digit string (`03`).

Valid phase numbers and their docs:

| # | Phase | Tier |
|---|---|---|
| 00 | Spec lock | any |
| 01 | Infrastructure (web/cli, Redis, Horizon) | senior |
| 02 | Octave bridge | **senior** |
| 03 | Auth, API, logging, events, observers | any |
| 04 | Frontend foundation | any |
| 05 | Octave console | any |
| 06 | Inverted pendulum | **senior** |
| 07 | Ball on beam | any |
| 08 | OpenAPI + queued PDF | any |
| 09 | Statistics (event-driven) | any |
| 10 | Polish, security, Horizon gate | any |
| 11 | Documentation, video, submission | any |

If the argument is missing, malformed, or out of range, stop and show this table. Don't guess.

## Step 2 — Locate the phase doc

```bash
ls docs/phases/phase-<NN>-*.md 2>/dev/null
```

If nothing matches, stop and report. The user may need to pull `main` first.

## Step 3 — Sanity-check the working tree

```bash
git status --porcelain
git rev-parse --abbrev-ref HEAD
```

Outcomes:

- **Dirty working tree** → stop, list the uncommitted files, ask the user how to proceed (stash, commit, or override).
- **Not on `main`** → stop, report the current branch, ask whether to switch or branch from here.
- **Clean and on `main`** → continue.

## Step 4 — Verify prerequisites

The phase doc declares prerequisites at the top under "Prerequisites". Read them. For each prior phase listed, look for evidence in the codebase that it landed:

- Phase 01 → `docker-compose.yml` has `web` and `cli` services; Horizon installed
- Phase 02 → `app/Services/Octave/OctaveBridgeClient.php` exists as an interface
- Phase 03 → `api_keys` migration exists; `ApiKeyMiddleware` exists
- Phase 04 → `resources/js/Layouts/AppLayout.tsx` exists; `resources/js/i18n/{sk,en}.ts` exist
- Phase 05 → `ExecuteOctaveCommandController` is real (not a stub returning a TODO)
- Phase 06 → `app/Events/SimulationStarted.php` exists; `AnimationName` enum exists

If a prerequisite is missing, stop and surface. Don't try to retrofit prior phases.

## Step 5 — Create the branch

```bash
git fetch origin
git checkout main
git pull --ff-only
git checkout -b phase-<NN>/coordinator-plan
```

Branch naming: `phase-<NN>/coordinator-plan` for the planning branch. The specialist agents may push directly to this branch or open sub-PRs at the user's discretion.

## Step 6 — Ensure `.work/` is gitignored

```bash
grep -q '^docs/phases/.work/' .gitignore || echo 'docs/phases/.work/' >> .gitignore
```

This is where the plan gets persisted. It should not be committed.

## Step 7 — Hand off to phase-coordinator

Invoke the **phase-coordinator** subagent with this brief:

> Plan phase <NN> for the WEBTE2 project.
>
> Read `CLAUDE.md`, the `docs/ARCHITECTURE.md` sections cited by the phase doc, and `docs/phases/phase-<NN>-*.md` end to end.
>
> Verify each prerequisite listed in the phase doc against the codebase (cite file:line evidence).
>
> Produce:
> 1. The ordered task list with `@<specialist>` delegations
> 2. A pre-filled PR description using the template from `CLAUDE.md § 12`
> 3. Any open questions or ambiguities in the phase doc
>
> Do not write feature code. Coordinate.

## Step 8 — Persist the plan

After phase-coordinator returns, save its full output to:

```
docs/phases/.work/phase-<NN>-plan.md
```

Create the directory if missing. Format:

```markdown
# Phase <NN> — Plan

**Branch**: phase-<NN>/coordinator-plan
**Created**: <ISO timestamp>
**Coordinator agent run**: <link to chat or "see scrollback">

---

<the phase-coordinator's output verbatim>
```

## Step 9 — Report

Tell the user, in this order:

1. **Branch created**: `phase-<NN>/coordinator-plan`
2. **Plan saved**: `docs/phases/.work/phase-<NN>-plan.md`
3. **Prerequisites**: ✓ all met / ✗ missing (with details)
4. **First task in the queue**: `<task 1 from the plan>` → recommended specialist: `@<agent-name>`
5. **Open questions**: list them, or "none — phase doc is unambiguous"
6. **Suggested next command**:
   - If everything is green: "Ready to delegate. Say the word and I'll hand task 1 to `@<agent>`."
   - If prerequisites are missing: "Block on the missing prerequisite. Want me to investigate via `@codebase-analyst`?"
   - If there are open questions: "Resolve the open questions first. They affect the plan."

End the response cleanly. Don't auto-delegate task 1; wait for the user to confirm.
