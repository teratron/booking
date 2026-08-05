# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-05 22:09
**Phase:** 1 — Foundation, Schema & Authorization
**Status:** Active

## Current Position

- **Task:** T-1A03 Quality toolchain and the composer quality gate
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral and unchanged by the pivot; the 3 L2 documents were rewritten. TZ coverage 134/134, registry parity clean.
- **Next Action:** Execute T-1A04 Asset pipeline — Vite, Tailwind 4, Alpine, Livewire 4 via /magic.run main

## Progress

```
Overall: [0/7] ░░░░░░░░ 0%
Plan:           [7 phases] generated (Bootstrap, tentative); Phase 1 decomposed, 2-7 scoped
Implementation: [3/21] Phase 1 — Track A: quality gate wired, `composer quality` green; A04 next
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-05 **Decision: plan generated in Bootstrap mode.** No specification reached `Stable`, so the C6 default (plan only `Stable`, backlog the rest) would have produced an empty plan. The Bootstrap Exception applies. `RFC → Stable` promotion was **withheld** — `RULES.md` §2 requires no open questions and the set carries twenty inline TBDs. Two touch Phase 1, recorded in `PLAN.md` §Open Questions Carried into Phase 1; the higher-value one (region-scoped permission transitivity) is worth closing before `T-1B01`.
- 2026-08-05 **Decision: back office before the public site**, inverting `[TZ]` §23's stages 4–6. The public site renders data that does not exist until the back office creates it; `l2-tech-stack.md` §6.4–§6.5 require scoped authorization before any panel screen. Recorded as a divergence, not applied silently — every stage is still delivered.
- 2026-08-05 **Decision:** `l1-room-reservation.md` **to Backlog.** Ships disabled in Phase 1 (`T-1T04` proves absence, not hiding); outside `[TZ]` §134's mandatory release, prior implementation explicitly not a migration source.
- 2026-08-05 **Standing instruction: continuous quality gates**, wired as `composer quality` (T-1A03) so local and CI are identical. Conventions enforced by Pest `arch()` tests, not review. Detail in CLAUDE.md "Engineering Discipline"; budgets in `l2-tech-stack.md` §5.9.
- 2026-08-05 **Decision: Stack — Laravel 13 + Filament 5 + PostgreSQL/PostGIS + Redis**, self-hosted. Full rationale in `l2-tech-stack.md` §1; earlier decisions (§98 waiver, deployment-fork closure, TZ-gap findings, Figma-first instruction) are in git history and each spec's own Document History — not repeated here.

## Blockers

<!-- Empty if none. Format: [severity] description -->

(none)

## Blocking Constraints

<!-- Anti-patterns discovered through real failures. MANDATORY reading. -->
<!-- Agent MUST explicitly acknowledge each constraint before working. -->

- **Engine bug:** `executor.js update-state` corrupts STATE.md fields on nearly
  every invocation (top-level `**Status:**`, `## Progress`). Always re-open
  STATE.md after `update-state`/`finalize` and manually verify both.
- **Public OSM tile servers are prohibited in production** by the OSMF Tile
  Usage Policy. Never ship pointing at `tile.openstreetmap.org` — use MapTiler,
  Stadia, or self-hosted tiles. The previous implementation shipped this
  violation unnoticed, which is how it was found.
- **Local Postgres occupies host port 5432** — the Docker service is mapped to
  5433. `postgres:18+` images store data under major-version subdirectories of
  `/var/lib/postgresql`, not `/var/lib/postgresql/data`.
- **Windows reserves TCP 7915–8114 on this machine.** Both 8000 (web) and 8025
  (Mailpit UI) fall inside it and fail to bind with a permissions error, not
  "address in use". They are mapped to 8300 and 8325. Check
  `netsh interface ipv4 show excludedportrange protocol=tcp` before picking any
  new host port.
- **No PHP or Composer on the host** — the entire PHP toolchain runs through the
  `booking-app` image (`docker compose exec app …`). Never assume a bare `php`
  or `composer` is available in a shell command.
- **The Windows bind mount is not a benchmark host.** First-byte latency through
  it measures 13–20 s against a 400 ms budget; that is filesystem cost, not
  application cost. `T-1T05` must measure inside the container against a
  non-bind-mounted copy, or the numbers describe Docker Desktop rather than the
  portal.
- **PostgreSQL ships no full-text dictionary for Georgian or Ukrainian.**
  Trigram matching carries name search; stemmed FTS will be incomplete.
  Escalation trigger to Typesense is recorded in `l2-tech-stack.md` §5.7.
- **Hiding a Filament action or Blade block is never an access control.**
  `[TZ]` §121 permissions are scoped by country/territory/category and must be
  enforced in Policies, server-side, on every read and write.
- **Catalog ordering is placement-tier first** — never "improve" it into
  relevance-first. A lower-tier object outranking a higher-tier one breaks the
  revenue model (`[TZ]` §25.2).
- **A composer script named `audit` is silently skipped** — it collides with
  Composer's own built-in command and is dropped with only a one-line warning,
  not an error. Call `composer audit` directly inside `quality`'s array
  instead of defining a wrapper script.
- **Git hooks are versioned at `.githooks/`, not `.git/hooks/`.** A fresh clone
  must run `git config core.hooksPath .githooks` once, or the pre-commit gate
  (engine-integrity check + `pint --test` on staged PHP) silently never fires.
- **Rector and Pint disagree on some formatting** (e.g. arrow-function
  spacing). Always run `composer fix` after `composer rector` and re-check
  `composer lint` before considering a refactor done.

## Session Continuity

**Reading order for a fresh session:** this file (position, decisions,
constraints) → `CLAUDE.md` (stack, conventions, engineering discipline) →
`.design/main/PLAN.md` (7 phases, Bootstrap/tentative) →
`.design/main/tasks/phase-1.md` (active phase, atomic tasks).

**Do not carry forward** anything about Next.js, TypeScript, Drizzle, Better Auth,
react-admin, or Vercel — that stack is superseded and preserved only at tag `v0.1.34`.
