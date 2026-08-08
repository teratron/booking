# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-08 17:10
**Phase:** 2 — Back Office Core
**Status:** Active

## Current Position

- **Task:** Phase 2 Track A + Track B (all of T-2B01–B07) + T-2D01 + T-2C01 + T-2C02 + T-2C03 done (16/25). Track B is fully complete. T-2C04 (untranslated report), Track D (D02-D05) remain, no cross-track blockers left.
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral and unchanged by the pivot; the 3 L2 documents were rewritten. TZ coverage 134/134, registry parity clean.
- **Next Action:** T-2C04 (reads the same catalogs T-2C03 built) or any of T-2D02+ — pick by track order (C ∥ D → T per phase-2.md).

## Progress

```
Overall: [1/7] █░░░░░░░ 14%
Plan:           [7 phases] Bootstrap/tentative; Phase 1 archived, Phase 2 decomposed, 3-7 scoped
Implementation: [21/21] Phase 1 DONE · [16/25] Phase 2 — Track A + Track B complete (objects/owners/impersonation/availability) + moderation + territories + object types + languages
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-08 **Decision: `T-2C03`'s interface-catalog override table is named `interface_catalog_overrides`, not `*_translations`** — it is a flat key/value override store, not an entity-translation sibling pair, and the `%_translations` name collides with `CatalogIndexPlanSchemaTest`'s generic scan for that other shape. The whole override table is cached as one entry (not one per locale/group) since a request can load several translation groups; `InterfaceCatalogRepository::save()` invalidates that one entry. Translation fallback now resolves to the current primary language via `Lang::determineLocalesUsing()`, never `config('app.fallback_locale')` — both must be wired before anything resolves the `translator` singleton, or a already-built `Translator` keeps the unwrapped loader.
- 2026-08-08 **Decision: `T-2B07`'s bulk reset and auto-reset both funnel through `AvailabilityAdministrationService`'s existing `applyStatus()` core** (via `override()` for the bulk path, a new `autoReset()` for the scheduled sweep) rather than duplicating the transaction/history/journal/cache logic a second time. The sweep is a no-op whenever `availability.auto_reset_enabled` is off — the setting's own seeded default, since auto-reset can manufacture a false vacancy claim.
- 2026-08-08 **Decision: `T-2B06`'s cache invalidation is real tag-based Redis flushing** (`catalog`/`territory:{id}`/`object:{id}` tags), not a stub — even though no catalog/territory/object page exists yet to write under those tags. Establishes the naming convention those later pages inherit.
- 2026-08-08 **Decision: `ImpersonationContext` is the single actor-resolution source for both journal-writing paths** — `AuditJournal`'s explicit writes and `owen-it/laravel-auditing`'s own automatic-audit resolver both consult it, so a mutation made during support-mode impersonation is always attributed to the administrator, never the owner. Session swap (`Auth::guard('web')->loginUsingId()`) happens only after the entry journal write commits, so a failed switch can never erase the record of having entered.
- 2026-08-07 **Decision: a blocked owner's "session termination" is enforced via `canAccessPanel()`**, re-checked by Filament on every request, rather than by hunting down Redis-backed session keys (this project's session store has no per-user index to walk). Also: `objects.owner_id` is now nullable with `ON DELETE SET NULL` — "ownerless" is a real, renderable state, not data loss.

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
- **Host port conflicts**: Postgres native→5433, web 8300, Mailpit 8325 (Win TCP 7915–8114 reserved; check first). `postgres:18+` data lives under a major-version subdir, not `.../data`.
- **No PHP/Composer on the host** — toolchain runs through `docker compose exec app …`. Never assume bare `php`/`composer` in a shell command.
- **The Windows bind mount is not a benchmark host** — benchmark inside the container, non-bind-mounted. **No Postgres FT dictionary for Georgian/Ukrainian** — trigram carries name search; escalates to Typesense (`l2-tech-stack.md` §5.7) if inadequate.
- **Superseded Next.js-era archives moved to `archives/tasks/v1-nextjs/`** — they occupied the exact filenames `archive-phases` writes to. Do not read them for context; do not move them back.
- **Domain invariants:** hiding a Filament action/Blade block is never an
  access control — Policies enforce scoped permissions server-side. Catalog
  ordering is placement-tier first, never relevance-first. **`objects.rating`
  does not exist** — add as a maintained aggregate later. **Strict
  authorization mode needs a bound Policy for every model a resource or
  relation manager touches**, including a purely read-only one — add it in
  the same change, or every unrelated page touching that resource 500s.
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
- **Larastan types every `BelongsTo` as non-nullable** regardless of the FK's real nullability — a legitimate nullsafe (`?->`) on a genuinely-nullable relation gets flagged "unnecessary"; check the FK column directly instead of removing the nullsafe.
- **`composer test`/`test:coverage`/`test:arch`/`test:slow` all run at a raised `memory_limit=1G`** — the suite outgrew PHP's 128 MB default as it passed ~190 tests.
- **A resolved `Translator` singleton keeps whatever `translation.loader` it was built with** — `astrotomic/laravel-translatable`'s `Locales` class depends on `TranslatorContract`, so resolving it (even just to sync the locale registry) locks the loader in; any `extend('translation.loader', …)` must run first in `boot()`, not after.

## Session Continuity

**Reading order for a fresh session:** this file (position, decisions,
constraints) → `CLAUDE.md` (stack, conventions, engineering discipline) →
`.design/main/tasks/phase-2.md` (active phase — 25 tasks, track ordering,
planning audit) → `.design/main/PLAN.md` only for cross-phase context.

**Do not carry forward** anything about Next.js, TypeScript, Drizzle, Better Auth,
react-admin, or Vercel — that stack is superseded and preserved only at tag `v0.1.34`.
