# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-14 21:20
**Phase:** 5 — Public Site
**Status:** Active

## Current Position

- **Task:** `T-5A01`–`T-5A05` are Done — 5/18. `T-5A02` (404 page + static legal pages) just closed, rendering inside the `T-5A01` shell via Laravel's own `errors/404.blade.php` convention view; legal page bodies live in the interface translation catalog rather than a new content model. `T-5A06` (map) is the only remaining independent Track A task; every Track B/C/D task can now build on the card component, the shell layout, and the error/legal surfaces.
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral; the 3 L2 documents were rewritten for the pivot. TZ coverage 134/134, registry parity clean.
- **Next Action:** Execute T-5A06 Clustered map — MapLibre GL JS and tile provisioning via /magic.run main

## Progress

```
Phase 5: [5/18] ██░░░░░░ 28%
Overall: [4/7] █████░░░ 57%
Plan:           [7 phases] Bootstrap/tentative; Phase 1-4 complete & archived, Phase 5 in progress, 6-7 scoped
Implementation: [21/21] Phase 1 DONE · [25/25] Phase 2 DONE · [23/23] Phase 3 DONE · [16/16] Phase 4 DONE · [5/18] Phase 5 IN PROGRESS
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-14 **Decision: `T-5A01` (public layout shell) closed** — built against the real Figma home-page frame (node 225:3619) for visual language, then extended past what the mockup shows wherever the specification requires more (popular destinations, object categories, and legal links in the footer; the feedback overlay entirely — no Figma frame depicts it). New `PublicShellDataProvider` caches navigation/languages/countries under one tag, invalidated by write hooks mirroring the existing `Banner` pattern. New `ResolvePublicLocale` middleware establishes the `/{lang}/...` route grammar `l1-seo.md` names, validating the segment against the real active-language registry; the no-segment resolution fallback (session/Accept-Language/primary) is deliberately deferred until a page is reachable without an explicit segment. `LocaleSwitchResolver` swaps only the `lang` route parameter so switching language preserves position. Two settings gaps closed: the header/footer now read the real `portal.name`/`contact_email`/`contact_phone` settings instead of hard-coding Figma's placeholder text, and three new social-link settings were added for the footer icons (downloaded and committed as real static assets, not invented SVGs). Full non-slow suite: 605 passed, 0 failed, 3 skipped (up from 596).

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
- **The Workflow tool is not guaranteed available — it is disabled entirely in some sessions/machines.** Check before relying on it; if unavailable, dispatch via the Agent tool or build directly. A prior machine's detailed task report (even for uncommitted work) is still valuable context to hand to whatever approach replaces it.
- **Uncommitted work does not transfer across a machine switch — commit early, even mid-task, when work might continue elsewhere.** One task's fully-built, fully-verified-but-uncommitted code was nearly lost this way; it survived only by accident (an unrelated automated tool's broad `git add -A` on the original machine happened to sweep it in before the switch). Do not rely on that happening again.
- **A fresh environment's `composer install` can fail outright (exit 4, installs nothing) if `composer.lock` doesn't satisfy a `composer.json` constraint** — this can be a long-latent, pre-existing gap that only surfaces on a truly empty `vendor/`. Fix with a targeted `composer update <package> --with-all-dependencies`, not a broad `composer update`. Verify `vendor/` on any new machine before trusting `composer analyse`/`pest` results there.

## Session Continuity

**Reading order for a fresh session:** this file (position, decisions,
constraints) → `CLAUDE.md` (stack, conventions, engineering discipline) →
`.design/main/tasks/phase-5.md` (active phase — decomposed, nothing started) →
`.design/main/PLAN.md` only for cross-phase context. Phases 1–4's own task files
are archived at `.design/main/archives/tasks/phase-{1,2,3,4}.md` for historical
reference.

**Do not carry forward** anything about Next.js, TypeScript, Drizzle, Better Auth,
react-admin, or Vercel — that stack is superseded and preserved only at tag `v0.1.34`.
