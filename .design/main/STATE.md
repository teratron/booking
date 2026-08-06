# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-06 18:39
**Phase:** 2 — Back Office Core
**Status:** Active

## Current Position

- **Task:** Phase 1 complete (21/21 tasks, all four tracks). Phase 2 not yet decomposed.
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral and unchanged by the pivot; the 3 L2 documents were rewritten. TZ coverage 134/134, registry parity clean.
- **Next Action:** Run /magic.task main to decompose Phase 2 into atomic tasks — no queued task exists yet for /magic.run to execute.

## Progress

```
Overall: [1/7] █░░░░░░░ 14%
Plan:           [7 phases] generated (Bootstrap, tentative); Phase 1 decomposed, 2-7 scoped
Implementation: [21/21] Phase 1 — ALL TRACKS DONE (102 migrations, 11 models, full auth+module layer)
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
- **Public OSM tile servers are prohibited in production** (OSMF policy) —
  never `tile.openstreetmap.org`; use MapTiler, Stadia, or self-hosted.
- **Host port conflicts**: Postgres native→5433, web 8300, Mailpit 8325 (Win
  TCP 7915–8114 reserved; check first). `postgres:18+` data lives under a
  major-version subdir, not `.../data`.
- **No PHP/Composer on the host** — toolchain runs through `docker compose
  exec app …`. Never assume bare `php`/`composer` in a shell command.
- **The Windows bind mount is not a benchmark host** — benchmark inside the
  container, non-bind-mounted. **No Postgres FT dictionary for
  Georgian/Ukrainian** — trigram carries name search; escalates to
  Typesense (`l2-tech-stack.md` §5.7) if inadequate.
- **`.design/main/archives/tasks/phase-1.md`–`phase-6.md` are leftovers from
  the superseded Next.js stack**, not this project's own archives — do not
  read them for context or let `archive-phases` overwrite them.
- **Domain invariants:** hiding a Filament action/Blade block is never an
  access control — Policies enforce scoped permissions server-side. Catalog
  ordering is placement-tier first, never relevance-first. **`objects.rating`
  does not exist** — add as a maintained aggregate later.
- **Tooling quirks:** a composer script named `audit` is silently skipped
  (collides with Composer's own command); Rector/Pint disagree — `composer
  fix` after `composer rector`. Array-form scripts abort at the first
  non-zero step, so order matters. `process-timeout: 900` — suite outgrew 300s.
- **Postgres role topology has a hard ceiling:** `booking` is table owner
  and bootstrap superuser — both bypass `GRANT`/`REVOKE`. Enforce "no role
  can do X" with a `BEFORE`-trigger instead. `migrate:fresh` drops tables
  but not functions/triggers — `CREATE OR REPLACE` both.
- **Git hooks are versioned at `.githooks/`** — `git config core.hooksPath
  .githooks` once per clone, or the pre-commit gate silently never fires.
- **Tests run against real Postgres (`booking_testing`), not SQLite** — the
  schema has geography columns and partial indexes SQLite cannot represent.
  `phpunit.xml` + CI both point at it; local db via
  `docker/postgres/init/00-create-testing-database.sql` (volume recreate needed).
- **Spatie packages (permission, medialibrary) don't auto-run migrations** —
  publish explicitly, tag `Str::after(name,'laravel-')` + `-migrations`.
  **`astrotomic/laravel-translatable`** uses a `locale` string column
  matching `languages.code`, not a `language_id` FK. **`laravel-permission`'s
  cache**: `DatabaseSeeder`'s `WithoutModelEvents` suppresses the `saved`
  event it invalidates on — call `PermissionRegistrar::forgetCachedPermissions()`
  before `givePermissionTo()` in a seeder, or it reads stale/empty state.
- **`Object` is a reserved PHP class name** — model is `Object_`, with
  `$translationModel`/`$translationForeignKey` set explicitly. **`shouldBeStrict()`
  breaks astrotomic's optional `$this->x ?: default()` properties** — declare
  them as real null properties (`Concerns\TranslatableDefaults`).
- **`make:migration` timestamps don't know about FK dependencies** — a table
  created before the one it references will fail; check dependency order first.

## Session Continuity

**Reading order for a fresh session:** this file (position, decisions,
constraints) → `CLAUDE.md` (stack, conventions, engineering discipline) →
`.design/main/PLAN.md` (7 phases, Bootstrap/tentative) →
`.design/main/tasks/phase-2.md` (active phase — scoped, not yet decomposed).

**Do not carry forward** anything about Next.js, TypeScript, Drizzle, Better Auth,
react-admin, or Vercel — that stack is superseded and preserved only at tag `v0.1.34`.
