# WEBTE2 — Záverečné zadanie LS 2025/2026

Build plan for the Octave-backed CAS web application.

**Stack**: Laravel 13, PHP 8.5, Inertia + React 19, MySQL 9, Redis 7, Python aiohttp Octave bridge, Docker Compose. Heavy use of Laravel queues, events, observers, and scheduled jobs.

**Container topology**: web (FPM) + cli (queue worker + scheduler) split, Redis-backed queues with Horizon dashboard.

**Deadline**: 21. 5. 2026 (23:55).

---

## How to use this plan with AI agents

Each phase is a self-contained markdown that an agent can execute given:

1. Repository access (the agent works on a feature branch)
2. `CLAUDE.md` (code-quality rules, conventions, anti-patterns)
3. `docs/ARCHITECTURE.md` (system shape, events/jobs catalog)
4. The phase markdown itself (DoD, tasks, agent brief)

Every phase markdown ends with a **copy-paste agent brief** — that's the prompt you hand to the agent at the start of the session.

Phases are sized so a competent coding agent finishes one in 0.5–2 working days. The riskier phases (02 — Octave bridge, 06 — pendulum animation) flag themselves as needing a senior-tier agent.

### Claude Code subagents and slash commands

This bundle ships eleven subagents in `.claude/agents/` and four slash commands in `.claude/commands/`. Claude Code picks them up automatically — no install step.

**Subagents**:

- `phase-coordinator` — orchestrator
- `laravel-backend-dev`, `react-frontend-dev`, `octave-bridge-dev` — implementation specialists
- `test-engineer`, `qa-gate-runner` — quality
- `code-reviewer`, `security-auditor`, `codebase-analyst` — read-only review and investigation
- `devops-engineer`, `docs-writer` — supporting roles

**Slash commands**:

- `/start-phase <NN>` — validate tree, branch, hand off to phase-coordinator, persist plan
- `/qa` — run every quality gate, auto-fix, surface
- `/review [scope]` — code review against anti-patterns
- `/finish-phase <NN>` — DoD walk + qa + review + PR draft

In a Claude Code session, run `/agents` to confirm all eleven are listed under "project agents", and `/help` to see the four custom commands alongside the built-ins.

A typical phase loop:

```
/start-phase 03                   plan and branch
@laravel-backend-dev <task>       delegate from the coordinator's plan
@test-engineer <task>             tests in parallel
/qa                               run gates, auto-fix, surface
/review                           pre-merge review
/finish-phase 03                  DoD walk + final PR draft
```

---

## Read order

1. `CLAUDE.md` — read first, every session
2. `docs/ARCHITECTURE.md` — system overview, request flows, events/jobs/observers catalog
3. `docs/phases/phase-XX-*.md` — the work for the current phase

---

## Phase index

| # | Phase | Duration | Tier | Parallel with |
|---|---|---|---|---|
| 00 | Spec lock | 0.5 d | any | — |
| 01 | Infrastructure (web/cli split, Redis, Horizon) | 1.5–2 d | senior | — |
| 02 | Octave bridge | 2–3 d | **senior** | 04 |
| 03 | Auth, API, logging, events, observers | 1.5–2 d | any | 04 |
| 04 | Frontend foundation | 1.5–2 d | any | 02, 03 |
| 05 | Octave console | 1 d | any | — |
| 06 | Inverted pendulum simulation | 2 d | **senior** | 07 (after 05) |
| 07 | Ball on beam simulation | 1 d | any | 06 |
| 08 | OpenAPI docs + queued PDF generation | 1 d | any | 09 |
| 09 | Statistics (event-driven) | 1 d | any | 08 |
| 10 | Polish, security, cross-browser, Horizon gate | 1.5 d | any | — |
| 11 | Documentation, video, submission | 1 d | any | — |

Phase docs:
[00](phases/phase-00-spec-lock.md) ·
[01](phases/phase-01-infrastructure.md) ·
[02](phases/phase-02-octave-bridge.md) ·
[03](phases/phase-03-auth-api-logging.md) ·
[04](phases/phase-04-frontend-foundation.md) ·
[05](phases/phase-05-octave-console.md) ·
[06](phases/phase-06-inverted-pendulum.md) ·
[07](phases/phase-07-ball-on-beam.md) ·
[08](phases/phase-08-openapi-pdf.md) ·
[09](phases/phase-09-statistics.md) ·
[10](phases/phase-10-polish.md) ·
[11](phases/phase-11-docs-video.md)

**Total**: ~16–20 working days. Parallel agents shrink this materially.

### Suggested parallelisation

- **After Phase 01**: split into two streams.
  - Stream A (senior): Phase 02 (Octave bridge)
  - Stream B (any): Phase 03 → Phase 04
- **After Phase 05**: Phase 06 and Phase 07 can run in parallel if you have two agent sessions and disciplined merging.
- **Phase 08 and Phase 09** can run in parallel.

---

## Quick commands

```bash
# Bring everything up (web + cli + redis + mysql + octave-bridge + nginx)
docker compose up -d

# Migrate + seed
docker compose exec web php artisan migrate --seed

# All quality gates
docker compose exec web composer qa
docker compose exec web npm run qa

# Tests
docker compose exec web php artisan test
docker compose exec web npm test

# Watch the queue (cli container does this in a long-running process)
docker compose logs -f cli

# Horizon dashboard
open http://localhost/horizon?token=$HORIZON_ADMIN_TOKEN
```

---

## Submission checklist

Lives in Phase 11. The short version:

- [ ] Source ZIP with full source, `docker-compose.yml`, `.env.example`, SQL dump
- [ ] Demo video walking through every rubric item
- [ ] Public deployment URL
- [ ] VCS URL with visible commit history
- [ ] Same source ZIP uploaded to https://node11.webte.fei.stuba.sk
