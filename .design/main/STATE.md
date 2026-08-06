# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-06 11:17
**Phase:** 1 — Foundation, Schema & Authorization
**Status:** Active

## Current Position

- **Task:** T-1B04 Migrations: notifications, analytics, platform, dormant booking
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral and unchanged by the pivot; the 3 L2 documents were rewritten. TZ coverage 134/134, registry parity clean.
- **Next Action:** Execute T-1B05 Index plan — composite, spatial, trigram, GIN, partial via /magic.run main

## Progress

```
Overall: [0/7] ░░░░░░░░ 0%
Plan:           [7 phases] generated (Bootstrap, tentative); Phase 1 decomposed, 2-7 scoped
Implementation: [8/21] Phase 1 — Track A DONE; Track B: T-1B01-T-1B04 done (85 migrations); T-1B05 next
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-05 **Decision: plan generated in Bootstrap mode.** No specification reached `Stable`, so the C6 default (plan only `Stable`, backlog the rest) would have produced an empty plan. The Bootstrap Exception applies. `RFC → Stable` promotion was **withheld** — `RULES.md` §2 requires no open questions and the set carries twenty inline TBDs. Two touch Phase 1, recorded in `PLAN.md` §Open Questions Carried into Phase 1; the higher-value one (region-scoped permission transitivity) is worth closing before `T-1B01`.
- 2026-08-05 **Decision: back office before the public site**, inverting `[TZ]` §23's stages 4–6. The public site renders data that does not exist until the back office creates it; `l2-tech-stack.md` §6.4–§6.5 require scoped authorization before any panel screen. Recorded as a divergence, not applied silently — every stage is still delivered.

## Blockers

<!-- Empty if none. Format: [severity] description -->

(none)

## Blocking Constraints

<!-- Anti-patterns discovered through real failures. MANDATORY reading. -->
<!-- Agent MUST explicitly acknowledge each constraint before working. -->

- **Engine bug:** `executor.js update-state` corrupts STATE.md fields on nearly
  every invocation (top-level `**Status:**`, `## Progress`). Always re-open
  STATE.md after `update-state`/`finalize` and manually verify both.
- **Public OSM tile servers are prohibited in production** (OSMF Tile Usage
  Policy) — never `tile.openstreetmap.org`; use MapTiler, Stadia, or
  self-hosted tiles.
- **Host port conflicts**: Postgres native→5433, web 8300, Mailpit 8325 (Win
  TCP 7915–8114 reserved; check `netsh ... excludedportrange` first).
  `postgres:18+` data lives under a major-version subdir, not `.../data`.
- **No PHP/Composer on the host** — toolchain runs through `docker compose
  exec app …`. Never assume bare `php`/`composer` in a shell command.
- **The Windows bind mount is not a benchmark host** (13–20s first-byte vs a
  400ms budget — filesystem cost, not app cost). `T-1T05` measures inside the
  container against a non-bind-mounted copy. **PostgreSQL has no full-text
  dictionary for Georgian/Ukrainian** — trigram carries name search; FTS
  escalates to Typesense per `l2-tech-stack.md` §5.7 if it proves inadequate.
- **Domain invariants:** hiding a Filament action/Blade block is never an
  access control — permissions (`[TZ]` §121, scoped by country/territory/
  category) are enforced in Policies, server-side. Catalog ordering is
  placement-tier first, never "improved" into relevance-first (`[TZ]` §25.2).
- **Tooling quirks (verified, not guessed):** a composer script named `audit`
  is silently skipped (collides with Composer's own command — call
  `composer audit` directly); Rector and Pint disagree on formatting, run
  `composer fix` after `composer rector`; `fallow`'s
  `dev-dependencies-in-production` cannot be suppressed per-file (whole-graph
  rule) — left at `warn`, `tailwindcss` in devDependencies is correct; `git`
  must be in the app image for `fallow audit`/`review`. Composer's array-form
  scripts abort at the first non-zero step — `unused` sat before `audit` in
  `quality`, silently skipping `audit` every run since `T-1A03` until
  reordered (`T-1B03`).
- **Git hooks are versioned at `.githooks/`** — `git config core.hooksPath
  .githooks` once per clone, or the pre-commit gate silently never fires.
- **Tests run against real Postgres (`booking_testing`), not SQLite** — the
  schema has geography columns and partial indexes SQLite cannot represent.
  `phpunit.xml` + CI service container both point at it; local db created via
  `docker/postgres/init/00-create-testing-database.sql` (volume recreate
  needed to pick it up).
- **Spatie packages built on Laravel Package Tools don't auto-run migrations**
  (`spatie/laravel-permission`, `spatie/laravel-medialibrary`) — publish
  explicitly; tag is `Str::after(package-name, 'laravel-')` + `-migrations`
  (`permission-migrations`, `medialibrary-migrations`, not the full package
  name). **`astrotomic/laravel-translatable` expects a `locale` string
  column** matching `languages.code`, not a `language_id` FK.
- **`make:migration` timestamps don't know about FK dependencies** — a table
  created before the one it references will fail. Check dependency order
  before writing content, not after (`object_user` → `objects` bit this in
  `T-1B02`, fixed by renaming the file).

## Session Continuity

**Reading order for a fresh session:** this file (position, decisions,
constraints) → `CLAUDE.md` (stack, conventions, engineering discipline) →
`.design/main/PLAN.md` (7 phases, Bootstrap/tentative) →
`.design/main/tasks/phase-1.md` (active phase, atomic tasks).

**Do not carry forward** anything about Next.js, TypeScript, Drizzle, Better Auth,
react-admin, or Vercel — that stack is superseded and preserved only at tag `v0.1.34`.
