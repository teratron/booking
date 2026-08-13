# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-13 08:39
**Phase:** 4 — Owner Cabinet
**Status:** Active

## Current Position

- **Task:** Phase 3 is fully Done (23/23, archived) and Phase 4 is now decomposed — 16 atomic tasks across four tracks (A cabinet foundation, B object management, C owner content, D statistics/bump/settings) plus Track T validation, written to `tasks/phase-4.md`. `T-4A01` (CabinetPanelProvider + owner-scoped resource base) is the hard gate every other task in this phase depends on, mirroring Phase 2's `T-2A02` role. Commit policy (explicit user instruction, still standing): commit after each completed task, push held for full phase-through-phase completion.
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral; the 3 L2 documents were rewritten for the pivot. TZ coverage 134/134, registry parity clean.
- **Next Action:** Execute T-4A01 CabinetPanelProvider, owner authentication, and the owner-scoped resource base contract via /magic.run main

## Progress

```
Phase 4: [0/16] ░░░░░░░░ 0%
Overall: [3/7] ███░░░░░ 43%
Plan:           [7 phases] Bootstrap/tentative; Phase 1-3 archived, Phase 4 decomposed, 5-7 scoped
Implementation: [21/21] Phase 1 DONE · [25/25] Phase 2 DONE · [23/23] Phase 3 DONE · [0/16] Phase 4 DECOMPOSED
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-13 **Decision: Phase 3 closed — Track T validation done (23/23 total), finalize run archived both Phase 2 and Phase 3 in one pass** — `T-3T01`–`T-3T03` (catalog ordering volume proof, analytics privacy invariants, notification completeness) built cleanly via a single sequential Workflow; `T-3T03` closed two real, confirmed gaps discovered by direct code reading before any test was written — three of the ten seeded notification types (`moderation_approved`/`rejected`, `revision_requested`, `object_status_changed`) were fully modeled and seeded but never actually triggered by `ModerationDecisionService`/`ObjectLifecycleService`, now wired. `T-3T04` hit the session cap a second time (a later reset than Track E's) after finding but not yet fixing a real regression: the admin nav sidebar's per-resource authorization check (`ScopeAuthorizer::authorize()`) had zero per-request memoization, so growing the panel to 20+ resources pushed `ActionJournalResource`'s list page to 41 queries against the 30-query ceiling — not a defect in that resource, a fixed per-navigation-item cost every resource pays that one simply had the least headroom to absorb. Fixed directly: a per-(user, permission) memo on `ScopeAuthorizer`, bound as a singleton so it survives the whole request; `ActionJournalResource` dropped to 1 query, every other resource and all three new custom pages (`CommerceReports` 20, `AnalyticsReport` 9, `NotificationBroadcast` 3) comfortably under budget afterward. Full suite: 474 passed, 3 skipped, 0 failed.
- 2026-08-13 **Decision: Track E (content publishing) closed by building `T-3E02`/`T-3E03` directly, no Workflow, after `T-3E01`'s dispatch hit the session cap** — both tasks went through the same independent-verification discipline as every agent-authored task this phase: falsifying test failures before trusting a fix, full suite green before marking Done (447 then 452 passed, both up cleanly from the prior checkpoint). Real, non-hypothetical issues caught this way, not by construction: a `Heroicon` constant that does not exist in the installed Filament version (caught by `composer analyse`'s own bootstrap crash); `promotions.starts_at` being a NOT NULL column made a null-check in `PromotionLifecycleService::publish()` provably dead code per Larastan, fixed alongside making the create form's field required with a sensible default so the constraint can never be violated; a test of my own asserting through the wrong (moderation-scoped) query for a still-pending draft. `T-3E03` also refactored `ArticleLifecycleService`'s (from `T-3E01`) and both of `T-3E02`'s lifecycle services to route cache invalidation through one new `ContentPublicationService::invalidate()` rather than three private copies — the literal "shared pipeline" the task's own name promised. All five feature tracks are now closed; only Track T validation remains for Phase 3.

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
  non-zero step, so order matters. `process-timeout: 1800` (bumped from 900,
  then 300 before that) — the suite keeps outgrowing whatever ceiling was
  last set; when `composer test` itself gets killed mid-run by this wrapper
  (distinct from an actual test failure), bypass it: `php artisan
  config:clear --ansi && php -d memory_limit=1G vendor/bin/pest
  --exclude-group=slow` runs the identical suite without the wrapper.
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
- **Commit policy changed mid-session (2026-08-13, explicit user instruction):** commit after each completed task now, not only once the entire phase-through-phase chain finishes. Push still waits for full completion, per the original instruction — the user only asked for per-task commits, not per-task pushes.
- **A Workflow run's spawned agents can all fail at once with "session limit" — that is an account-wide usage cap, not a per-agent error, and it does not mean zero progress happened.** One run's three sequential agents (Track E) all reported this identical failure; the first agent had in fact completed essentially all of its real work (files written, its own quality gates already run) and only failed at its final self-report step, right as the cap tripped — the two agents after it never got to start. Always inspect the actual filesystem (`git status`, read the files) before assuming a "failed" Workflow result means nothing landed; independently re-verify whatever did land exactly as if it had self-reported success, then fall back to direct (non-Workflow) implementation for whatever the cap actually blocked.
- **A resolved `Translator` singleton keeps whatever `translation.loader` it was built with** — `astrotomic/laravel-translatable`'s `Locales` class depends on `TranslatorContract`, so resolving it (even just to sync the locale registry) locks the loader in; any `extend('translation.loader', …)` must run first in `boot()`, not after.

## Session Continuity

**Reading order for a fresh session:** this file (position, decisions,
constraints) → `CLAUDE.md` (stack, conventions, engineering discipline) →
`.design/main/tasks/phase-4.md` (active phase, once `/magic.task` decomposes
it — currently frontmatter and scope only) → `.design/main/PLAN.md` only for
cross-phase context. Phase 3's own task file is archived at
`.design/main/archives/tasks/phase-3.md` for historical reference.

**Do not carry forward** anything about Next.js, TypeScript, Drizzle, Better Auth,
react-admin, or Vercel — that stack is superseded and preserved only at tag `v0.1.34`.
