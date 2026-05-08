# Phase 11 — Documentation, video, submission

**Duration**: 1 d
**Tier**: any
**Required reading**: `CLAUDE.md`, the assignment brief

## Goal

Ship. Technical documentation, demo video, deployment, and the submission ZIP. After this phase the project is handed in.

## Definition of Done

- [ ] Technical documentation PDF in both languages: `docs/technical-documentation.sk.pdf` and `.en.pdf`
- [ ] Demo video recorded covering every rubric item, ~10–14 minutes
- [ ] Public deployment reachable from the open internet
- [ ] Source ZIP built per the brief's structure
- [ ] Source ZIP uploaded to https://node11.webte.fei.stuba.sk
- [ ] All commit history visible (each contributor with at least 3 meaningful commits to defendable code)
- [ ] Submission checklist (§ 11.7) walked end-to-end on the day before the deadline

## Prerequisites

Phase 10 complete. No further functional changes during this phase.

## Tasks

### 11.1 Technical documentation

Cover:

- System overview (one-page architecture diagram from `ARCHITECTURE.md` § 2)
- Stack and rationale
- Container topology (web/cli/redis/mysql/octave-bridge/nginx)
- API reference (link to live OpenAPI + a frozen snapshot)
- Authentication (API key flow, where the key is stored, how to rotate)
- Octave bridge security model (sandbox + blocklist + container lockdown)
- Events / jobs / observers catalog
- Deployment guide:
  - Required env vars
  - `docker compose up -d` walkthrough
  - First-boot: migrate, seed, create the demo API key
  - Horizon access via `HORIZON_ADMIN_TOKEN`
- Development guide:
  - Local setup
  - Running tests
  - Running quality gates
  - How to add a new API route end to end
- Known limitations & follow-ups

Write the source in markdown under `docs/`, generate PDFs via Pandoc + a print-styled CSS:

```bash
pandoc docs/technical-documentation.sk.md \
    --pdf-engine=weasyprint \
    --css=docs/print.css \
    -o docs/technical-documentation.sk.pdf
```

### 11.2 Demo video

Target ~12 minutes. Tools: OBS Studio. 1080p. Resulting file ≤ 200 MB.

Outline:

1. **(0:00)** Intro: project overview, where it's deployed, link to repo
2. **(0:45)** Quick architecture walkthrough — diagram from the docs, explain web/cli split
3. **(1:30)** API key creation: `php artisan cas:create-api-key demo`, then a curl call
4. **(2:30)** Octave console: simple expressions, persistent workspace (`a = 1+1; a+2`), syntax highlighting, error handling, clear session
5. **(4:00)** Inverted pendulum: parameters, run, animation + chart sync, change `r`, continue from previous state
6. **(6:00)** Ball on beam: similar walkthrough, note the "tilt exaggerated" label
7. **(7:30)** Logs page: filter, sort, CSV export
8. **(8:15)** API docs page: Swagger UI, try-it-out, PDF download (queued — show the spinner, the polling, the page numbering)
9. **(9:30)** Statistics: total + per-day + map/table by country, demonstrate cooldown by reloading rapidly
10. **(10:30)** Bilingual switch on every page
11. **(11:00)** Horizon dashboard with admin token — show queues, completed listeners, no failures
12. **(11:30)** Wrap-up: deployment URL, repo URL, submission ZIP

Record one full take, edit cut points for length.

### 11.3 Deployment

Target: school server or any public VM. Steps:

```bash
git clone <repo-url>
cd <repo>
cp .env.example .env
# fill in: APP_KEY, CAS_API_KEY_PLAINTEXT, HORIZON_ADMIN_TOKEN, GEOLITE_LICENSE_KEY (optional)
docker compose up -d
docker compose exec web php artisan key:generate
docker compose exec web php artisan migrate --force
docker compose exec web php artisan db:seed --class=DemoSeeder
docker compose exec web php artisan cas:create-api-key production
```

For a real public deployment, add Caddy or Traefik in front for TLS. Document both options in `docs/deployment.md`.

### 11.4 Submission ZIP

Per the brief:

```
webte2-{surname1}-{surname2}.zip
├── README.md                           # quick start
├── docker-compose.yml                  # production-ready
├── .env.example                        # full var inventory
├── docs/
│   ├── technical-documentation.sk.pdf
│   ├── technical-documentation.en.pdf
│   ├── ARCHITECTURE.md
│   └── api-docs-snapshot.pdf           # frozen OpenAPI PDF
├── seed.sql                            # mysqldump of demo state
├── octave-models/                      # the original .txt models
└── src/                                # full source tree
```

Build script:

```bash
#!/usr/bin/env bash
set -euo pipefail
out=webte2-submission.zip
rm -f "$out"
zip -r "$out" \
  README.md docker-compose.yml .env.example \
  docs/ src/ octave-models/ seed.sql \
  -x '*/node_modules/*' '*/vendor/*' '*/.git/*' '*/storage/logs/*'
```

Generate `seed.sql`:

```bash
docker compose exec mysql mysqldump -u webte2 -pchangeme webte2 > seed.sql
```

### 11.5 Repository

- README.md at the repo root: quick start (clone → up → migrate → seed → first key)
- LICENSE (if applicable to the brief)
- All commit history preserved
- Tag `submission-v1` on the final commit

### 11.6 Task split (for the record)

Document in the PR or in `docs/contributors.md` who handled which areas. A neutral, accurate split:

| Area | Owner |
|---|---|
| Phase 00 — Spec | Both (initial sync) |
| Phase 01 — Infrastructure | Track A |
| Phase 02 — Octave bridge | Track A |
| Phase 03 — Auth, logging, events | Track B |
| Phase 04 — Frontend foundation | Track B |
| Phase 05 — Console | Track A |
| Phase 06 — Pendulum | Track A |
| Phase 07 — Ball on beam | Track B |
| Phase 08 — OpenAPI + PDF | Track B |
| Phase 09 — Statistics | Track A |
| Phase 10 — Polish | Both |
| Phase 11 — Docs + video | Both |

Each track produces meaningful commits that can be defended at the viva. Pair-review across tracks is expected.

### 11.7 Submission checklist (run on day-before)

- [ ] Clean clone + fresh `docker compose up -d` works on a colleague's machine
- [ ] All quality gates green on `main`
- [ ] CI green on the latest commit
- [ ] Demo video uploaded somewhere accessible (YouTube unlisted, drive link)
- [ ] Public deployment URL reachable and stable
- [ ] PDF documentation generated for both locales
- [ ] OpenAPI PDF snapshot generated and included
- [ ] `seed.sql` regenerated from the deployed database
- [ ] Submission ZIP built, < 200 MB
- [ ] Repo URL, video URL, deployment URL all in `README.md`
- [ ] ZIP uploaded to `https://node11.webte.fei.stuba.sk`
- [ ] Each contributor has ≥ 3 meaningful commits
- [ ] Final tag `submission-v1` pushed

## Risks

| Risk | Mitigation |
|---|---|
| Video recording surfaces last-minute bugs | Don't accept "small fixes" — note them, ship the video, follow up after submission |
| ZIP > 200 MB | Excludes ensure no `node_modules`, `vendor`, `storage/logs` |
| Deployment instability | Bring up the production deployment 2 days before; smoke-test daily |

## Hand-off

There is no Phase 12. After submission, follow up on any deferred items as separate issues.

## Agent brief (copy-paste)

> Read `CLAUDE.md` and this phase markdown.
>
> Tasks:
> 1. Write `docs/technical-documentation.{sk,en}.md` covering the topics in § 11.1; render to PDF via pandoc + weasyprint
> 2. Generate the API docs PDF snapshot from the running app
> 3. Run the deployment per § 11.3 on the target server; verify reachable
> 4. Generate `seed.sql` from the deployed database
> 5. Build the submission ZIP per § 11.4 (make sure node_modules, vendor, .git, storage/logs are excluded)
> 6. Walk the day-before checklist (§ 11.7) and tick items in the PR
> 7. Tag `submission-v1` after final review
>
> Do not introduce behavioural changes during this phase. Bug fixes only if they block submission.
>
> PR labelled `phase:11`.
