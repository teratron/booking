# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-14 08:50
**Phase:** 4 — Owner Cabinet
**Status:** Active — paused by explicit user instruction (see below), not blocked

## Current Position

- **Task:** Track A (`T-4A01`, `T-4A02`), Track B (`T-4B01`–`T-4B05`), and Track C (`T-4C01`, `T-4C02`) are all Done — 9/16. Remaining: Track D (`T-4D01` statistics, `T-4D02` bump, `T-4D03` settings/notification preferences, `T-4D04` staleness surfacing — all four require only `T-4A01`, no ordering between them) and Track T (`T-4T01`–`T-4T03` validation, requires everything else). **Session paused here on explicit user instruction** — after this checkpoint's commit, push, and PR update, work stops until the user resumes (possibly from a different machine; this file plus the committed history is the full handoff). Commit policy: commit after each completed task/batch. Push policy: push at natural checkpoints (this pause point, and previously after `T-4A01`) rather than only at full completion — also an explicit, standing instruction from the user, superseding the original "push only once everything is done."
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral; the 3 L2 documents were rewritten for the pivot. TZ coverage 134/134, registry parity clean.
- **Next Action:** Resume with T-4D01 (statistics) via /magic.run main — see Current Position above for the template files and remaining track order.

## Progress

```
Phase 4: [9/16] █████░░░ 56%
Overall: [3/7] ███░░░░░ 43%
Plan:           [7 phases] Bootstrap/tentative; Phase 1-3 archived, Phase 4 in progress, 5-7 scoped
Implementation: [21/21] Phase 1 DONE · [25/25] Phase 2 DONE · [23/23] Phase 3 DONE · [9/16] Phase 4 IN PROGRESS
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-14 **Decision: paused Phase 4 mid-track on explicit user instruction, after Track C closed, to let the user check remaining weekly token budget before continuing (possibly on a different machine)** — pushed the branch and updated the PR at this checkpoint rather than holding until full completion, a standing policy change from earlier in this same session. See the Workflow session-limit constraint below for the batch-size lesson this pause point's own retries confirmed.
- 2026-08-14 **Decision: `T-4C01`/`T-4C02` (owner news/promotions, reviews) closed Track C** — `T-4C01` reused the exact moderation-routing shape `T-4B01`'s `ObjectEditService` established, adapted for creation rather than editing (a `ContentSubmissionOutcome` value object parallels `ObjectEditOutcome`), and found two real pre-existing gaps blocking the screens from being reachable at all: `NewsItemPolicy`/`PromotionPolicy` had no ownership-based authorization path (only the staff scope-table path present in every admin-side Policy), and the `object_owner` role had zero `content.*` permissions — both fixed. `T-4C02` built a `Review` model and Policy from scratch (none existed) whose `update`/`delete` abilities are refused unconditionally for every actor including the review's own object owner — reviews are never editable/deletable through this policy, proven by two falsifying tests (cross-owner, and same-owner-but-protected-field). Full non-slow suite: 550 passed, 0 failed, 3 skipped (up from 536).
- 2026-08-13 **Decision: `T-4B02`–`T-4B04` (media, rooms & prices, services) closed Track B, and surfaced a genuine cross-cutting regression** — adding `Room` as a new `Translatable` model made `TranslatableEntityRegistry`'s reflection-based discovery pick it up, and the existing `TranslationCompletenessReport` crashed querying a `needs_review` column `room_translations` never had (it predates that convention). Fixed with a migration mirroring an exact precedent an earlier phase already established for the identical gap class on a different table set — now a standing constraint below for any future new `Translatable` model.

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
- **A Workflow run's spawned agents can all fail at once with "session limit" — that is an account-wide usage cap, not a per-agent error, and it does not mean zero progress happened.** Sometimes an agent completes its real work and only fails at its own final self-report step (files land, verifiable via `git status`) — seen repeatedly in this project. Sometimes the cap hits before any agent produces anything at all (Phase 4's Track D/T batch: 6 tasks queued, zero files landed). Always inspect the actual filesystem before assuming a "failed" result means nothing happened; independently re-verify whatever did land, then either fall back to direct implementation or simply retry the identical batch via `Workflow({scriptPath, resumeFromRunId})` once the cap's own stated reset time has passed — this has now succeeded cleanly multiple times. **Batch size matters**: a 6-task sequential batch hit the cap immediately with zero completions twice in a row; the identical batch reduced to 2 tasks succeeded on the first retry. No proven cause, but until one is found, prefer 2–3 substantial tasks per Workflow batch for this kind of work over larger ones.
- **A resolved `Translator` singleton keeps whatever `translation.loader` it was built with** — `astrotomic/laravel-translatable`'s `Locales` class depends on `TranslatorContract`, so resolving it (even just to sync the locale registry) locks the loader in; any `extend('translation.loader', …)` must run first in `boot()`, not after.
- **`ModerationScope` (global scope hiding unapproved `Object_` rows) silently breaks any query an owner needs to run against their own not-yet-approved object** — this bit both Filament's tenancy (default tenant route-binding, `User::getTenants()`/`canAccessTenant()`) and will bite every future cabinet query the same way unless explicitly stripped (`withoutGlobalScope(ModerationScope::class)`). The rule going forward: this scope belongs on public-facing/catalog queries only, never on a query resolving what an *authenticated, already-authorized* owner or staff member can reach about their own listing. Every Track B/C/D resource querying `Object_` (or anything scoped through it) must check this before assuming a draft/pending fixture will behave like an approved one in its own tests.
- **Filament's tenant-menu (and anywhere else reading a tenant's display label) calls `getAttributeValue('name')` directly, bypassing any model's overridden `getAttribute()`** — a translated attribute (astrotomic) or any other custom accessor built on `getAttribute()` renders blank there unless the model also implements `Filament\Models\Contracts\HasName::getFilamentName()`. `Object_` already does; any other model later given a Filament label/name needs the same check.

## Session Continuity

**Reading order for a fresh session:** this file (position, decisions,
constraints) → `CLAUDE.md` (stack, conventions, engineering discipline) →
`.design/main/tasks/phase-4.md` (active phase — decomposed, T-4A01 Done) →
`.design/main/PLAN.md` only for cross-phase context. Phase 3's own task file
is archived at `.design/main/archives/tasks/phase-3.md` for historical
reference.

**Do not carry forward** anything about Next.js, TypeScript, Drizzle, Better Auth,
react-admin, or Vercel — that stack is superseded and preserved only at tag `v0.1.34`.
