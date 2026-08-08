# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-08 20:45
**Phase:** 2 — Back Office Core
**Status:** Active

## Current Position

- **Task:** Phase 2 Track A + Track B + Track C all done, plus T-2D01–D04 (20/25). Only T-2D05 and Track T (T01-T04) remain.
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral and unchanged by the pivot; the 3 L2 documents were rewritten. TZ coverage 134/134, registry parity clean.
- **Next Action:** T-2D05 (archive/restore/permanent deletion) — the last Track D task; Track T last, no cross-track blockers before it.

## Progress

```
Overall: [1/7] █░░░░░░░ 14%
Plan:           [7 phases] Bootstrap/tentative; Phase 1 archived, Phase 2 decomposed, 3-7 scoped
Implementation: [21/21] Phase 1 DONE · [20/25] Phase 2 — Track A + B + C complete, Track D underway (queue/review/journal done, archive remains)
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-08 **Decision: `T-2D04`'s archival job exports aging journal entries to `s3` rather than deleting them** — the append-only trigger from Phase 1 fires `BEFORE UPDATE OR DELETE` on `audits` unconditionally, for every role including a scheduled job's own connection ("never, by any role" per its own migration comment), so pruning the live table is not an option T-2D05 (or anything else) can reach for either. `outcome` (success/failure) has no column — derived from the event name's existing `_failed`/`_refused`/`_denied`/`_rejected` suffix convention instead of adding one that could drift. `Audit` is a vendor model (`owen-it/laravel-auditing`), so its Policy is bound via `Gate::policy()` in `AppServiceProvider`, not a `#[UsePolicy]` attribute — that attribute only works on a model this project owns.
- 2026-08-08 **Decision: `T-2D03`'s decisions pin the target's translation locale to the portal's primary language, not the moderator's own UI locale** — the request model carries no `locale` column despite proposed data sometimes holding translated fields, so `fill()` (which `astrotomic/laravel-translatable` routes to whatever locale is currently active) would otherwise write into whichever language the deciding moderator happens to be browsing in. Partial acceptance settles the original request as `approved` and spins off a fresh `pending` request for exactly the untouched remainder, since the `decision` enum has no "partially approved" state to represent it in place. Every decision writes its target mutation, the request's own outcome, and the journal entry in one transaction — the specification's own explicit requirement, not just house style.
- 2026-08-08 **Decision: `T-2D02`'s queue denormalizes `country_id`/`owner_id` onto `moderation_requests` at submission time** — `ScopedResource` scope narrowing filters a direct column on the resource's own table, and the request's target is polymorphic so no such column existed to filter on. `ModerationPipeline::submit()` already received both for mode resolution; only persisting them was missing. Test-fixture gotcha hit here: an object with no explicit `moderation_status` defaults to null, and `ModerationScope` (`WHERE moderation_status = 'approved'`) hides it even from an eager-loaded polymorphic relation, not just direct queries — seed fixtures must set it explicitly.
- 2026-08-08 **Decision: `T-2C04`'s translatable-entity discovery scans `app/Models` for the `Translatable` contract instead of a maintained list** — reflection resolves each model's translation table/FK/attributes generically, so a Phase 3 model needs no registry change to appear in the report. `needs_review` + `published_at` added to all nine `*_translations` tables in one migration, backfilled so existing rows don't silently un-publish. Filament's `Table::query()` requires a real Eloquent builder, so the drill-down table is scoped to one entity+locale at a time rather than a hand-built cross-table union.
- 2026-08-08 **Decision: `T-2C03`'s interface-catalog override table is named `interface_catalog_overrides`, not `*_translations`** — it is a flat key/value override store, not an entity-translation sibling pair, and the `%_translations` name collides with `CatalogIndexPlanSchemaTest`'s generic scan for that other shape. The whole override table is cached as one entry (not one per locale/group) since a request can load several translation groups; `InterfaceCatalogRepository::save()` invalidates that one entry. Translation fallback now resolves to the current primary language via `Lang::determineLocalesUsing()`, never `config('app.fallback_locale')` — both must be wired before anything resolves the `translator` singleton, or a already-built `Translator` keeps the unwrapped loader.

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
