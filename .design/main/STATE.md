# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-15 15:13
**Phase:** 6 — Discovery, Reporting & Public API
**Status:** Active

## Current Position

- **Task:** Phase 6 activated and decomposed — 16 atomic tasks across four tracks plus validation. Phase 5 archived at 18/18. Nothing executed yet. The phase is genuinely three-wide (`(A → B) ∥ C ∥ D → T`): Track C reads the aggregate tier Phase 3 built, Track D layers over Phase 5's `CatalogQueryService`, and neither consumes the SEO addressing chain that gates Track B. `T-6A01` (URL grammar) is the hard gate and the phase's real risk — it **retrofits** addressing that 21 existing files already reference, unlike the greenfield gates of Phases 2 and 5.
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral; the 3 L2 documents were rewritten for the pivot. TZ coverage 134/134, registry parity clean. Phase 6's three specs (`l1-seo`, `l1-analytics`, `l1-public-api`) carry two open questions that touch it — see PLAN.md.
- **Next Action:** Execute T-6A01 Per-language slug resolution and the territory-hierarchy URL grammar via /magic.run main

## Progress

```
Phase 6: [0/16] ░░░░░░░░ 0%
Overall: [5/7] ██████░░ 71%
Plan:           [7 phases] Bootstrap/tentative; Phase 1-5 complete & archived, 6 active, 7 scoped
Implementation: [21/21] Phase 1 DONE · [25/25] Phase 2 DONE · [23/23] Phase 3 DONE · [16/16] Phase 4 DONE · [18/18] Phase 5 DONE · [0/16] Phase 6 ACTIVE
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-15 **Decision: `T-5T02` (event-emission invariant) closed** — new `PublicEventEmissionInvariantTest.php`, no product changes. The Method's container-rebind falsification technique doesn't transplant literally (`EventCaptureService` has no rebindable collaborator on its queue-dispatch path); adapted to `config()->set('queue.default', 'nonexistent-connection')`, applied against real public routes rather than a bare service call. Falsified directly against product code (catch block temporarily made to rethrow, confirmed failure, reverted). Full non-slow suite: 660 passed, 0 failed, 3 skipped.
- 2026-08-15 **Decision: `T-5T03` (performance budget) closed — Phase 5 complete** — new `PublicPerformanceBudgetTest.php` (`slow`), plus real product changes: response caching (`CatalogQueryService::search()`, territory sidebar, object presenter), two genuine N+1 fixes (per-object `loadMissing()` cannot batch; three raw per-card aggregates batched via ad-hoc attributes), and a real bug fix (`badge_text` is translated, not a plain `placement_tiers` column). One relation (`placement.package.tier`) could not be safely eager-loaded via Eloquent at all — reproducibly broke and slowed a query already joining the same tables via `PlacementOrderingService::apply()` — resolved via a plain join query instead. A riskier shared-retrieval optimization for the object page's own nearby/similar blocks caused a real, caught regression and was reverted. Catalog/territory meet the ≤30-query budget; object page does not (68, documented gap, not silently loosened). Full non-slow suite: 660 passed, 0 failed, 3 skipped; full `slow`-group clean except `DemoVolumeSeederTest`'s own pre-existing, unrelated memory-limit flake.
- 2026-08-15 **Decision: Phase 6 planned — 16 tasks, three-wide** — decomposition derived from the codebase's actual gaps rather than from the specs alone, which changed three things. The `redirects` and `api_clients` tables and Sanctum already exist from the schema pass, so their tasks are model-and-wiring, not migrations. `territory_translations.full_slug_path` already exists and is maintained by `TerritoryAdministrator`, but nothing reads it and only one code path writes it — `T-6A01` makes it the read path and backfills the rest. `AnalyticsReport` already serves period-scoped filterable counts with export, so Track C is narrowed to the derived figures (most-viewed, CTR, new-owner/object counts) and surfacing the traffic-source data `TrafficSourceRecorder` already records but nothing displays. `spatie/laravel-sitemap` is named in the project's package list but is **not installed** — `T-6B04` adds it. Corrected a phantom spec reference in PLAN.md (`l1-analytics.md §5.5` does not exist; the section set is §5.1–§5.4 and §5.6).

## Blockers

<!-- Empty if none. Format: [severity] description -->

(none)

## Blocking Constraints

<!-- Anti-patterns discovered through real failures. MANDATORY reading. -->
<!-- Agent MUST explicitly acknowledge each constraint before working. -->

- **Engine bug:** `executor.js update-state` corrupts STATE.md fields on nearly every invocation (top-level `**Status:**`, `## Progress`). Always re-open STATE.md after `update-state`/`finalize` and manually verify both.
- **Public OSM tile servers are prohibited in production** (OSMF policy) — never `tile.openstreetmap.org`; use MapTiler, Stadia, or self-hosted.
- **Host port conflicts**: Postgres native→5433, web 8300, Mailpit 8325 (Win TCP 7915–8114 reserved; check first). `postgres:18+` data lives under a major-version subdir, not `.../data`.
- **No PHP/Composer on the host** — toolchain runs through `docker compose exec app …`. Never assume bare `php`/`composer` in a shell command.
- **The Windows bind mount is not a benchmark host** — benchmark inside the container, non-bind-mounted; wall-clock ms figures against this host should be measured and reported, never hard-asserted (confirmed again in `T-5T03`). **No Postgres FT dictionary for Georgian/Ukrainian** — trigram carries name search; escalates to Typesense (`l2-tech-stack.md` §5.7) if inadequate.
- **Superseded Next.js-era archives moved to `archives/tasks/v1-nextjs/`** — they occupied the exact filenames `archive-phases` writes to. Do not read them for context; do not move them back.
- **Domain invariants:** hiding a Filament action/Blade block is never an access control — Policies enforce scoped permissions server-side. Catalog ordering is placement-tier first, never relevance-first. **`objects.rating` does not exist** — add as a maintained aggregate later. **Strict authorization mode needs a bound Policy for every model a resource or relation manager touches**, including a purely read-only one — add it in the same change, or every unrelated page touching that resource 500s.
- **Eager-loading `placement.package.tier` (via `with()` or `Collection::load()`) reproducibly breaks and drastically slows any query already going through `PlacementOrderingService::apply()`'s own joins against `object_placements`/`placement_packages`/`placement_tiers`** — root cause not isolated; batch tier badge/border data via a plain, relation-free join query instead (`CatalogQueryService::attachCardAggregates()`'s own pattern). **`placement_tiers.badge_text` does not exist** — it is a translated attribute on `placement_tier_translations`, keyed by `locale`; `badge_colour`/`border_colour` are real, non-translated columns on `placement_tiers` itself.
- **Tooling quirks:** a composer script named `audit` is silently skipped (collides with Composer's own command); Rector/Pint disagree — `composer fix` after `composer rector`. Array-form scripts abort at the first non-zero step, so order matters. `process-timeout: 1800` (bumped from 900, then 300 before that) — the suite keeps outgrowing whatever ceiling was last set; when `composer test` itself gets killed mid-run by this wrapper (distinct from an actual test failure), bypass it: `php artisan config:clear --ansi && php -d memory_limit=1G vendor/bin/pest --exclude-group=slow` runs the identical suite without the wrapper.
- **Postgres role topology has a hard ceiling:** `booking` is table owner and bootstrap superuser — both bypass `GRANT`/`REVOKE`; enforce "no role can do X" with a `BEFORE`-trigger. `migrate:fresh` drops tables but not functions or triggers — `CREATE OR REPLACE` both.
- **Git hooks are versioned at `.githooks/`** — `git config core.hooksPath .githooks` once per clone, or the pre-commit gate silently never fires.
- **Tests run against real Postgres (`booking_testing`), not SQLite** — the schema has geography columns and partial indexes SQLite cannot represent. `phpunit.xml` + CI both point at it; local db via `docker/postgres/init/00-create-testing-database.sql` (volume recreate needed).
- **Running two heavy Pest suites concurrently against the same test database causes genuine Postgres deadlocks and dropped-table errors** (`RefreshDatabase`'s per-test transaction wrap does not protect against a concurrent process's own `migrate:fresh`/heavy seeding) — always run test suites sequentially, never in parallel background jobs against the same `booking_testing` database.
- **Spatie packages (permission, medialibrary) don't auto-run migrations** — publish explicitly, tag `Str::after(name,'laravel-')` + `-migrations`. **`astrotomic/laravel-translatable`** uses a `locale` string column matching `languages.code`, not a `language_id` FK. **`laravel-permission`'s cache**: `DatabaseSeeder`'s `WithoutModelEvents` suppresses the `saved` event it invalidates on — call `PermissionRegistrar::forgetCachedPermissions()` before `givePermissionTo()` in a seeder, or it reads stale/empty state.
- **`Object` is a reserved PHP class name** — model is `Object_`, with `$translationModel`/`$translationForeignKey` set explicitly. **`shouldBeStrict()` breaks astrotomic's optional `$this->x ?: default()` properties** — declare them as real null properties (`Concerns\TranslatableDefaults`).
- **`make:migration` timestamps don't know about FK dependencies** — a table created before the one it references will fail; check dependency order first.
- **Larastan types every `BelongsTo` as non-nullable** regardless of the FK's real nullability — a legitimate nullsafe (`?->`) on a genuinely-nullable relation gets flagged "unnecessary"; check the FK column directly instead of removing the nullsafe.
- **`composer test`/`test:coverage`/`test:arch`/`test:slow` all run at a raised `memory_limit=1G`** — the suite outgrew PHP's 128 MB default as it passed ~190 tests. `DemoVolumeSeederTest` can still fail on `ini_set('memory_limit', '128M')` when run after other heavy 50k-object seeders in the same `--group=slow` process (cumulative memory already above 128M) — a known, pre-existing environmental flake, not a regression signal.
- **Commit policy:** commit after each completed task, not only once a phase finishes; push still waits for full completion (per-task commits, not per-task pushes). Uncommitted work does not survive a machine switch — commit early, even mid-task.
- **A Workflow run's spawned agents can all fail at once with "session limit"** — an account-wide usage cap, not a per-agent error; progress may still have landed (verify via `git status` before assuming nothing happened), then retry via `Workflow({scriptPath, resumeFromRunId})` once the cap resets. Prefer 2–3 substantial tasks per batch — larger batches hit the cap more often.
- **A resolved `Translator` singleton keeps whatever `translation.loader` it was built with** — `astrotomic/laravel-translatable`'s `Locales` class depends on `TranslatorContract`, so resolving it (even just to sync the locale registry) locks the loader in; any `extend('translation.loader', …)` must run first in `boot()`, not after.
- **`ModerationScope` (global scope hiding unapproved `Object_` rows) silently breaks any query an owner needs to run against their own not-yet-approved object** — this bit both Filament's tenancy (default tenant route-binding, `User::getTenants()`/`canAccessTenant()`) and will bite every future cabinet query the same way unless explicitly stripped (`withoutGlobalScope(ModerationScope::class)`). The rule going forward: this scope belongs on public-facing/catalog queries only, never on a query resolving what an *authenticated, already-authorized* owner or staff member can reach about their own listing.
- **Filament's tenant-menu (and anywhere else reading a tenant's display label) calls `getAttributeValue('name')` directly, bypassing any model's overridden `getAttribute()`** — a translated attribute (astrotomic) or any other custom accessor built on `getAttribute()` renders blank there unless the model also implements `Filament\Models\Contracts\HasName::getFilamentName()`. `Object_` already does; any other model later given a Filament label/name needs the same check.
- **The Workflow tool is not guaranteed available — it is disabled entirely in some sessions/machines.** Check before relying on it; if unavailable, dispatch via the Agent tool or build directly.
- **A fresh environment's `composer install` can fail outright (exit 4, installs nothing) if `composer.lock` doesn't satisfy a `composer.json` constraint** — this can be a long-latent, pre-existing gap that only surfaces on a truly empty `vendor/`. Fix with a targeted `composer update <package> --with-all-dependencies`, not a broad `composer update`. Verify `vendor/` on any new machine before trusting `composer analyse`/`pest` results there.
- **`node_modules` is bind-mounted and shared between the Windows host and the Linux `app` container — running `pnpm install` from one side installs platform-native optional packages (e.g. `lightningcss-win32-x64-msvc`) that dangle as broken symlinks for the other.** The pre-commit hook's JS gate runs `pnpm` inside the container, so a host-side install can silently break the next commit. Run `pnpm install`/`pnpm run build` inside the container (`docker compose exec app …`) as the default; if a host-side install already happened, re-run `CI=true pnpm install --no-frozen-lockfile` inside the container to rebuild the correct platform binaries before committing.

## Session Continuity

**Reading order for a fresh session:** this file (position, decisions,
constraints) → `CLAUDE.md` (stack, conventions, engineering discipline) →
`.design/main/tasks/phase-6.md` (Phase 6, active — carries the task detail,
track ordering, and planning audit) → `.design/main/PLAN.md` only for
cross-phase context. Phases 1–5's own task files are archived at
`.design/main/archives/tasks/phase-{1,2,3,4,5}.md` for historical reference.

**Do not carry forward** anything about Next.js, TypeScript, Drizzle, Better Auth,
react-admin, or Vercel — that stack is superseded and preserved only at tag `v0.1.34`.
