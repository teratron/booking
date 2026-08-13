# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-13
**Phase:** 3 — Commerce, Advertising & Platform Services
**Status:** Active

## Current Position

- **Task:** Phase 2 complete, pending archival. Track A, Track D, Track C, and Track B all Done; Track E's `T-3E01` (Article) also Done — 17/23. Track E's Workflow run hit the session's account-wide usage cap (all three agents failed) right as `T-3E01`'s agent reached its own final report — its real work had already landed and independently re-verified clean, so nothing was lost; `T-3E02`/`T-3E03` never started and are being built directly (no Workflow) instead. Commit policy changed mid-session: commit after each completed task now (explicit user instruction), push still held for full completion.
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral and unchanged by the pivot; the 3 L2 documents were rewritten. TZ coverage 134/134, registry parity clean.
- **Next Action:** `T-3E02` (News & promotions models, auto-archival job) next, built directly; then `T-3E03`. Track T validation after Track E closes (all five feature tracks converge on it). Run /magic.run main.

## Progress

```
Phase 3: [17/23] ██████░░ 74%
Overall: [2/7] ██░░░░░░ 29%
Plan:           [7 phases] Bootstrap/tentative; Phase 1 archived, Phase 2 done, Phase 3 in progress, 4-7 scoped
Implementation: [21/21] Phase 1 DONE · [25/25] Phase 2 DONE · [17/23] Phase 3 IN PROGRESS
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-13 **Decision: `T-3E01` (Article) kept and marked Done despite its authoring Workflow run reporting total failure — the failure was the account's session usage cap, not a defect, and the agent's real work had already landed on disk** — inspected the filesystem first rather than trusting the "3/3 agents failed" summary, found a complete, well-formed `Article`/`ArticleCategory`/`ArticleTag` implementation plus a 7-test suite already written, then ran the full independent-verification pass exactly as for any agent-authored task: `composer fix` (504 files, clean), `composer analyse` (0 errors, 402 files), the task's own tests (7/7), full suite (441 passed, up from 434, 0 failed). Nothing needed fixing. Separately, the user changed the commit policy mid-session: commit after every completed task from now on (push still waits for full phase-through-phase completion, per the original instruction, since only commits were mentioned). `T-3E02`/`T-3E03` never started (the cap hit before they could) and are being built directly rather than through another Workflow dispatch, which would likely fail identically before the cap's stated 2:50am (Europe/Chisinau) reset.
- 2026-08-13 **Decision: a severe, pre-existing Phase 2 bug fixed on discovery while researching Track E's moderation-status pattern, before any Track E work began** — `Object_` uses the `FiltersModeration` trait, whose global scope requires `moderation_status = 'approved'` on every default (non-back-office) query, but `ObjectLifecycleService::publish()` never set that column — only `status`. A freshly created object's `moderation_status` starts null, indistinguishable from "pending" to that scope, so an administrator-published object would have been invisible to any future public-facing query; every existing test had masked this by hand-setting `moderation_status: 'approved'` on its own fixtures rather than exercising the real `publish()` path. Confirmed via a new falsifying test (`tests/Feature/Admin/ObjectLifecyclePublicationTest.php`) before fixing: failed with the object genuinely absent from the default query, then passed once `publish()` was corrected to also set `moderation_status = 'approved'` — matching the same "administrator publishing is already the trusted act" invariant Track E's own spec states for articles/news. Full suite re-run clean afterward (434 passed, 0 failed, up from 433 — the one new test). Directly relevant to Track E: `NewsItem`/`Promotion` carry the identical nullable `moderation_status` pattern for administrator-authored rows, so this precedent is now the reference Track E's agents are pointed at to avoid reproducing the same gap.
- 2026-08-13 **Decision: Track D-remainder (dispatch pipeline, scheduled jobs, administrator broadcast) closes Track D — third consecutive clean three-agent sequential Workflow run, independently re-verified with no further defect found** (`composer fix` 474 files clean, `composer analyse` 0 errors/374 files, full suite 433 passed/3 skipped/0 failed matching the agents' own counts exactly). Two genuinely new schema additions this track (unlike prior tracks, which only built on top of pre-existing Phase 1 schema): a `notification_preferences` table (none existed before — recipient control over optional-class types only, per spec) and a `retry_count` column plus a `(notification_id, notification_channel_id)` uniqueness constraint on `notification_dispatches` (the idempotency contract had no schema-level backstop until now). The agents' own falsification passes caught real bugs before self-reporting done: a naive per-object broadcast loop that would have double-messaged an owner with two qualifying objects, and a microsecond-vs-second timestamp precision mismatch in one of the agents' own new test fixtures. One pattern noted, not changed: `NotificationBroadcast`'s territory Select eagerly loads every territory row for the "resort" target type — identical to the pre-existing pattern in `ObjectForm`/`AnalyticsReport` from earlier phases/tracks, so left as an established, if imperfect, codebase convention rather than patched in isolation in one new screen; a candidate finding for `T-3T04`'s panel-budget validation rather than an ad-hoc fix here.
- 2026-08-12 **Decision: Track B (banner registry, selection service, promotional labels) built as a three-agent sequential Workflow, one agent per task in dependency order, then independently re-verified** — this time re-verification found no additional defect (`composer fix` clean at 456 files, `composer analyse` 0 errors across 361 files, full suite 399 passed/3 skipped/0 failed, matching the agents' own reported counts exactly), but the agents' own falsification passes still caught three real, non-hypothetical bugs before self-reporting done: (1) `league/flysystem-aws-s3-v3` was never installed despite `.env` requiring the S3 disk this project's storage architecture depends on — the first real file-upload flow in the codebase is what surfaced it; (2) a test-isolation gap where `Storage::fake('public')` alone doesn't cover Livewire's default-disk staging step; (3) `staudenmeir/laravel-adjacency-list`'s ancestor-walk depth sign was the opposite of what the implementation first assumed (negative going up, not positive), silently inverting the region-vs-exact-node specificity ranking until caught by a failing assertion's actual (wrong) output. Also fixed in passing: Composer's `process-timeout` (900s) now routinely undercuts the suite's own growing runtime when invoked through the `composer test` script wrapper — bumped to 1800s; the underlying test run itself was never slow enough to be a real problem.
- 2026-08-12 **Decision: Track C (event ingestion, daily rollup/compaction, reporting) built via a single sequential Workflow agent, then independently re-verified rather than trusted on self-report** — `composer fix`/`composer analyse` came back clean, but a from-scratch `composer test` run found one real failure the agent's own report hadn't surfaced: `app/Models/StatDaily.php` imported `App\Jobs\AnalyticsRollupJob` solely for a `{@see}` docblock tag, tripping the "models are thin" architecture test (353 passed / 1 failed). The import was never functionally used — removed and the docblock reworded to plain prose; suite returned to 354 passed / 0 failed. Confirms the working pattern established earlier this phase: every task's output, whether self-authored or agent-authored, gets its own independent quality-gate run before being marked Done, not a pass-through of the producing agent's self-report.

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
`.design/main/tasks/phase-3.md` (active phase — 22 tasks, track ordering) →
`.design/main/PLAN.md` only for cross-phase context.

**Do not carry forward** anything about Next.js, TypeScript, Drizzle, Better Auth,
react-admin, or Vercel — that stack is superseded and preserved only at tag `v0.1.34`.
