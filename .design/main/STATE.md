# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-12 01:45
**Phase:** 2 — Back Office Core
**Status:** Active

## Current Position

- **Task:** Phase 2 Track A + Track B + Track C + Track D + T-2T01 + T-2T02 all done (23/25). Only T-2T03–T-2T04 remain.
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral and unchanged by the pivot; the 3 L2 documents were rewritten. TZ coverage 134/134, registry parity clean.
- **Next Action:** T-2T03 (journal completeness and append-only enforcement) — no cross-track blockers before it.

## Progress

```
Overall: [1/7] █░░░░░░░ 14%
Plan:           [7 phases] Bootstrap/tentative; Phase 1 archived, Phase 2 decomposed, 3-7 scoped
Implementation: [21/21] Phase 1 DONE · [23/25] Phase 2 — Track A + B + C + D complete, T-2T01–T-2T02 done, T-2T03–T-2T04 remain
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-12 **Decision: `T-2T02` is verification-only — all four moderation invariants already held, so no production code changed** — each was proven capable of failing first: `reject()` temporarily applying proposed data, `approve()` temporarily skipping it, the availability override temporarily routed through the pipeline, and the review page's `diff()` temporarily reading the live target instead of the stored snapshot, each reverted after its assertion failed at the expected point. Its own fixture hit the same `moderation_status` gotcha `T-2D02` first documented — the target's polymorphic relation is invisible to `approve()` unless `moderation_status` is set to `approved` explicitly.
- 2026-08-12 **Decision: `T-2T01`'s matrix proved `ObjectResource`'s edit page does not bypass actor-scope narrowing, only the soft-delete scope** — its `resolveRecord()` override adds `withTrashed()` so an archived record's edit page stays reachable for the restore action, but the actor-scope narrowing baked into `getEloquentQuery()` still runs underneath it; an out-of-scope object therefore 404s, the same as every other resource, never 403. This surfaced a pre-existing test in `ObjectResourceFormTest` that was passing for the wrong reason — its actor lacked `admin_panel_access`, so the 403 it observed was panel-admission denial, not the category-policy outcome the test's name claimed — fixed alongside the matrix (actor granted access, assertion corrected to 404). The module-gating scenario the spec names by example has no current admin resource bound to an optional module, so it is deferred rather than stubbed against zero cases — `EnsureModuleEnabled` is a registered middleware alias with no current admin-panel binding site.
- 2026-08-12 **Decision: `T-2D05`'s archive/restore/transfer proof runs against `Object_` and a generic anonymous model, not Phase 3's content types** — `news_items`, `promotions`, `banners`, and `articles` all ship `softDeletes()` columns from Phase 1's upfront schema, but none has an Eloquent model yet; those are Phase 3 deliverables. An anonymous class (`new class extends Model { use SoftDeletes; protected $table = 'news_items'; }`) proves the mechanism generically without pulling a content-pipeline model forward. `users` has no `deleted_at` at all — its `blocked_at`/`blocked_by` lifecycle from an earlier task is the archive-equivalent, asserted as its own thing rather than duplicated with a column that was never meant to exist. Permanent deletion's re-authentication is a real password check (`Hash::check()`) in the service layer, not just a Filament confirmation — the service re-checks the chief-administrator restriction independently of the Filament `->authorize()` gate, mirroring the defense-in-depth pattern already used for critical settings.
- 2026-08-08 **Decision: `T-2D04`'s archival job exports aging journal entries to `s3` rather than deleting them** — the append-only trigger from Phase 1 fires `BEFORE UPDATE OR DELETE` on `audits` unconditionally, for every role including a scheduled job's own connection ("never, by any role" per its own migration comment), so pruning the live table is not an option anything can reach for. `outcome` (success/failure) has no column — derived from the event name's existing `_failed`/`_refused`/`_denied`/`_rejected` suffix convention instead of adding one that could drift. `Audit` is a vendor model (`owen-it/laravel-auditing`), so its Policy is bound via `Gate::policy()` in `AppServiceProvider`, not a `#[UsePolicy]` attribute — that attribute only works on a model this project owns.

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
