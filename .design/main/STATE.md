# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-06 22:13
**Phase:** 2 — Back Office Core
**Status:** Active

## Current Position

- **Task:** Phase 2 Track A + T-2B01 + T-2D01 done (7/25). T-2B02 and T-2C01 now unblocked, run next.
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral and unchanged by the pivot; the 3 L2 documents were rewritten. TZ coverage 134/134, registry parity clean.
- **Next Action:** Execute T-2B02 (object form) and T-2C01 (territory administration) in parallel; both were blocked only on T-2D01, now clear.

## Progress

```
Overall: [1/7] █░░░░░░░ 14%
Plan:           [7 phases] Bootstrap/tentative; Phase 1 archived, Phase 2 decomposed, 3-7 scoped
Implementation: [21/21] Phase 1 DONE · [7/25] Phase 2 — Track A + object list + moderation resolution
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-05 **Decision: plan generated in Bootstrap mode.** No specification reached `Stable`. `RFC → Stable` promotion stays **withheld** — `RULES.md` §2 requires no open questions and the set carries twenty inline TBDs.
- 2026-08-06 **Decision: field-level partial acceptance of a moderated change set is implemented behind a portal setting, defaulting off** (`T-2D03`). The client specification marks it optional and the snapshot model already supports it, so gating costs a setting read rather than a redesign.
- 2026-08-07 **Decision: `pragmarx/google2fa-laravel` removed** (user-authorized dependency cleanup). Filament's own MFA provider depends on `pragmarx/google2fa` directly and never called the Laravel wrapper's facade/middleware; confirmed unused before removal, full suite green after. `CLAUDE.md`'s required-packages table updated to match.
- 2026-08-07 **Decision: `ModerationModeResolver` and `ModuleResolver` stay separate**, reversing this phase's own planning note that predicted one shared service. Built concretely, the two ladders diverge enough (module_settings carries a module FK plus dependency-graph walking; moderation_settings has no portal row at all, unlike module_settings) that unifying them would force an abstraction to fit. **Plan gap surfaced, not filled unilaterally:** no task in this phase decomposes an admin write screen for `moderation_settings`; the natural write points (object form, owner management, category registry) don't claim it either. Flagged for the next `/magic.task` pass rather than invented here.

## Blockers

<!-- Empty if none. Format: [severity] description -->

(none)

## Blocking Constraints

<!-- Anti-patterns discovered through real failures. MANDATORY reading. -->
<!-- Agent MUST explicitly acknowledge each constraint before working. -->

- **Engine bug:** `executor.js update-state` corrupts STATE.md fields on nearly
  every invocation (top-level `**Status:**`, `## Progress`). Always re-open
  STATE.md after `update-state`/`finalize` and manually verify both.
- **Public OSM tile servers are prohibited in production** (OSMF policy) — never `tile.openstreetmap.org`; use MapTiler, Stadia, or self-hosted.
- **Host port conflicts**: Postgres native→5433, web 8300, Mailpit 8325 (Win
  TCP 7915–8114 reserved; check first). `postgres:18+` data lives under a
  major-version subdir, not `.../data`.
- **No PHP/Composer on the host** — toolchain runs through `docker compose
  exec app …`. Never assume bare `php`/`composer` in a shell command.
- **The Windows bind mount is not a benchmark host** — benchmark inside the
  container, non-bind-mounted. **No Postgres FT dictionary for
  Georgian/Ukrainian** — trigram carries name search; escalates to
  Typesense (`l2-tech-stack.md` §5.7) if inadequate.
- **Superseded Next.js-era archives moved to `archives/tasks/v1-nextjs/`** —
  they occupied the exact filenames `archive-phases` writes to. Do not read
  them for context; do not move them back.
- **Domain invariants:** hiding a Filament action/Blade block is never an
  access control — Policies enforce scoped permissions server-side. Catalog
  ordering is placement-tier first, never relevance-first. **`objects.rating`
  does not exist** — add as a maintained aggregate later.
- **Tooling quirks:** a composer script named `audit` is silently skipped
  (collides with Composer's own command); Rector/Pint disagree — `composer
  fix` after `composer rector`. Array-form scripts abort at the first
  non-zero step, so order matters. `process-timeout: 900` — suite outgrew 300s.
- **Postgres role topology has a hard ceiling:** `booking` is table owner and bootstrap superuser — both bypass `GRANT`/`REVOKE`; enforce "no role can do X" with a `BEFORE`-trigger. `migrate:fresh` drops tables but not functions or triggers — `CREATE OR REPLACE` both.
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
`.design/main/tasks/phase-2.md` (active phase — 25 tasks, track ordering,
planning audit) → `.design/main/PLAN.md` only for cross-phase context.

**Do not carry forward** anything about Next.js, TypeScript, Drizzle, Better Auth,
react-admin, or Vercel — that stack is superseded and preserved only at tag `v0.1.34`.
