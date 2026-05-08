---
description: Review code changes against CLAUDE.md anti-patterns and project conventions. Default scope is the current branch vs main.
argument-hint: [staged|uncommitted|branch]
allowed-tools: Read, Bash, Grep, Glob, Task
---

Run a code review for scope **$ARGUMENTS** (default: `branch`).

Scope mapping:

| Argument | Diff range | When to use |
|---|---|---|
| `staged` | `git diff --cached` | Before committing |
| `uncommitted` | `git diff` | Mid-work sanity check |
| `branch` (or empty) | `git diff main...HEAD` | Pre-merge review |

If the argument is something other than these three, default to `branch` and tell the user.

Invoke the **code-reviewer** subagent with this brief:

> Review the changes for the WEBTE2 project at scope: `<scope>`.
>
> Walk every file changed against `CLAUDE.md § 13` (anti-patterns) and the project's Laravel/React/Python conventions documented in `CLAUDE.md` and `docs/ARCHITECTURE.md`.
>
> Mark every finding with severity: **must-fix**, **should-fix**, **nit**, or **praise**. Cite `file:line` for each finding. End with a clear verdict.

After code-reviewer returns:

- **No must-fix items** → tell the user "ready to merge" (or "ready to commit", depending on scope). If `branch`, suggest `/finish-phase <NN>` for the final wrap-up.
- **Must-fix items present** → surface them prominently. Recommend the right specialist to delegate the fixes to (e.g., `@laravel-backend-dev`, `@react-frontend-dev`).
- **Only should-fix or nits** → tell the user the call is theirs: ship now and follow up, or fix in this PR.
