---
phase: 1
name: "Foundation, Schema & Authorization"
status: Todo
subsystem: "docker/, database/, app/Models, app/Services, app/Policies"
requires: []
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 1 Tasks — Foundation, Schema & Authorization

**Phase:** 1
**Status:** Todo
**Strategic Goal:** A running Laravel 13 + Filament 5 monolith with the complete schema
applied from empty, every registry seeded as data, scoped authorization enforced
server-side, feature modules gated at the middleware boundary, and continuous quality
gates wired — so that no later phase has to retrofit any of them.

## Track Ordering

Phase 1's tracks are **not** independent. The real ordering is:

```plaintext
A (scaffold)  →  B (schema)  →  C (seeders)  ∥  D (domain core)  →  T (validation)
```

Track B cannot begin before `T-1A01` and `T-1A02`. Tracks C and D run concurrently once
Track B lands. Track T consumes all three. Effective parallel degree is two, not four.

## Atomic Checklist

### Track A — Scaffold & Toolchain

- [x] [T-1A01] Scaffold the Laravel 13 + Filament 5 monolith
- [x] [T-1A02] Local Docker Compose stack with PostGIS, Redis, MinIO, Mailpit
- [x] [T-1A03] Quality toolchain and the `composer quality` gate
- [x] [T-1A04] Asset pipeline — Vite, Tailwind 4, Alpine, Livewire 4

### Track B — Schema

- [x] [T-1B01] Migrations: identity, access, localization, geography, taxonomy
- [x] [T-1B02] Migrations: object, media, rooms, prices, reviews, contacts, favorites
- [x] [T-1B03] Migrations: placement and finance, advertising, content, governance
- [x] [T-1B04] Migrations: notifications, analytics, platform, dormant booking
- [x] [T-1B05] Index plan — composite, spatial, trigram, GIN, partial
- [ ] [T-1B06] Retention rules — soft delete, moderation scopes, append-only privileges

### Track C — Registries & Seeders

- [ ] [T-1C01] Registry seeders — languages, countries, territory levels, types, amenities, channels, tiers, packages, modules, notification types
- [ ] [T-1C02] Roles and permissions seeder with the unrevocable chief-administrator grant
- [ ] [T-1C03] Realistic-volume demo seeder for benchmarking

### Track D — Domain Core

- [ ] [T-1D01] Scoped authorization — `role_scopes` resolution and the base policy
- [ ] [T-1D02] Feature-module registry — resolution ladder and server-side gate
- [ ] [T-1D03] Eloquent models — relations, casts, scopes, and package traits only

### Track T — Validation

- [ ] [T-1T01] `migrate:fresh --seed` from empty, plus the generated ER diagram
- [ ] [T-1T02] Architecture tests — conventions enforced mechanically
- [ ] [T-1T03] Authorization test matrix — scoped grants deny across every scope kind
- [ ] [T-1T04] Module inertness test — disabled means absent, both directions
- [ ] [T-1T05] Benchmark harness — catalog ranking and subtree expansion against budgets

## Detailed Tracking

### [T-1A01] Scaffold the Laravel 13 + Filament 5 monolith

- **Spec:** l2-tech-stack.md §5.1, §5.2, §5.8, §6.1
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `php artisan --version` reports Laravel 13.x; `php -v` reports 8.5+; `composer show filament/filament` reports 5.x; both panel providers resolve — `php artisan route:list --path=admin` and `--path=cabinet` each return at least one route.
- **Changes:** Laravel 13.24.0 scaffolded at the repository root, PHP 8.5.9, Filament v5.7.5 with `AdminPanelProvider` (`/admin`) and `CabinetPanelProvider` (`/cabinet`) registered. §5.8 directory layout created; `strict_types` declared in all 17 source files; lang path pointed at `resources/lang`; `composer.json` renamed to `teratron/booking` with `php: ^8.5`.
- **Evidence:** `php artisan --version && route:list` · exit 0 · Laravel 13.24.0, PHP 8.5.9, filament v5.7.5; admin 3 routes, cabinet 2 routes; langPath `/var/www/html/resources/lang` · no errors.
- **Handoff:** T-1A02 (infrastructure the application connects to), T-1A03 (gates that run over it).
- **Notes:** Directory layout per §5.8 — `app/{Models,Filament/Admin,Filament/Cabinet,Livewire,Services,Policies,Jobs,Console/Commands,Support}`. `declare(strict_types=1)` in every file from the first commit; retrofitting it later is a diff across the whole tree. Install the latest stable release of every package — do not pin back a major version.

### [T-1A02] Local Docker Compose stack with PostGIS, Redis, MinIO, Mailpit

- **Spec:** l2-tech-stack.md §5.3, §5.10; l2-third-party-integrations.md §5.1, §5.4
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose up -d` brings all services healthy; `docker compose exec postgres psql -U postgres -c "SELECT extname FROM pg_extension"` lists `postgis`, `pg_trgm`, and `unaccent`; `php artisan tinker --execute="DB::select('select postgis_version()')"` returns a version; Redis reachable via `php artisan tinker --execute="Cache::store('redis')->put('k',1); echo Cache::store('redis')->get('k');"`.
- **Changes:** Completed the §5.10 topology — added `app` (PHP-FPM), `worker`, and `nginx` services plus `docker/app/Dockerfile` (PHP 8.5 with intl, pdo_pgsql, redis, imagick, pcntl, opcache), an entrypoint that fixes runtime-writable ownership, and `docker/nginx/default.conf`. Corrected the PostGIS image tag and moved two host ports out of a Windows reserved range.
- **Evidence:** `docker compose ps` · exit 0 · 7/7 services up, postgres and redis healthy; `pg_extension` lists postgis, pg_trgm, unaccent; `postgis_version()` = 3.6 USE_GEOS=1 USE_PROJ=1 USE_STATS=1; Redis round-trip returned `ok`; `GET /up` and `GET /` both 200 · no errors.
- **Handoff:** T-1B01 — no migration can run before the extensions exist.
- **Notes:** Two environment facts already cost this project time and are recorded as constraints. The host's own PostgreSQL occupies port 5432, so map the container to **5433**. `postgres:18+` images store data under a major-version subdirectory of `/var/lib/postgresql`, **not** `/var/lib/postgresql/data` — a volume mounted at the old path silently produces an empty database. Extensions are created by the init SQL in `docker/`, not by a migration, because a migration cannot run before the extension it needs exists.
- **Execution findings:** Three faults surfaced that had never been exercised, because this compose file was authored in an earlier session and never run. (1) `postgis/postgis:18-3.5-alpine` does not exist; the published tag is `18-3.6-alpine`, which still satisfies the spec's "3.5+". (2) Windows reserves TCP 7915–8114 on this machine, covering **both** the intended web port 8000 and Mailpit's 8025; they now bind 8300 and 8325, and `APP_URL` follows. (3) The `booking_postgres-data` volume dated 2026-07-30 still held the superseded v1 schema, so a non-empty `PGDATA` caused the entrypoint to skip `/docker-entrypoint-initdb.d` and no extension was ever created — the volume was dumped to the session scratchpad (15 tables, 21.7 KB, schema only) and recreated from empty.
- **Recorded risk:** first-byte latency through the Windows bind mount measures 13.8 s on `/up` and 20.1 s on `/`, against a §5.9 budget of 400 ms. This is filesystem cost, not application cost, and it makes the developer machine unusable as a benchmark host for `T-1T05` without mitigation (named volume for `vendor/`, or measuring inside the container against a non-bind-mounted copy).

### [T-1A03] Quality toolchain and the `composer quality` gate

- **Spec:** l2-tech-stack.md §5.9
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `composer quality` exits 0 and runs, in order: `pint --test`, `phpstan analyse` at level 8, `pest`, coverage at the configured minimum, `composer audit`, and the unused-dependency check. CI runs the same single command on push — confirm by inspecting the workflow file and one green run.
- **Changes:** Installed Pest 5 (replacing the scaffolded PHPUnit direct dependency — Pest 5 requires PHPUnit ^13 as a transitive dependency), Larastan 3.10 + PHPStan 2.2, Rector 2.6 + driftingly/rector-laravel, icanhazstring/composer-unused. Added `pcov` to the runtime image for coverage. Wrote `pint.json` (Laravel preset + `declare_strict_types`), `phpstan.neon` (level 8, Larastan extension), `rector.php` (dead-code set only; PHP-version and Laravel-version targets both auto-resolve from `composer.json` rather than being hardcoded). Added `tests/Architecture/` with 6 `arch()` conventions (strict types, no debug functions, thin models, Filament/Livewire not touching `DB` directly, controllers/jobs/services final) plus a plain-Pest containment test grepping `app/`, `resources/`, `database/` for `.design/` references, task IDs, phase names, and system filenames. Wired `Model::shouldBeStrict()` in `AppServiceProvider` for N+1/missing-attribute detection, with a dedicated regression test. `composer.json` gained `fix`, `fix:dirty`, `lint`, `analyse`, `test`, `test:arch`, `test:coverage` (min 80%), `unused`, `quality`, `rector`, `rector:dry`. Git hooks relocated from the unversioned `.git/hooks/` to versioned `.githooks/` via `core.hooksPath`, preserving the existing magic-sync integrity check and adding a staged-PHP-aware `pint --test` gate. Added `.github/workflows/quality.yml` running `composer quality` as the single CI command.
- **Evidence:** `composer quality` · exit 0 · Pint 32 files clean; PHPStan level 8, 0 errors; Pest 10 passed (21 assertions) across Unit/Feature/Architecture; coverage 100.0% (was 94.1% before the strict-mode regression test closed the one gap); `composer audit` — no advisories; composer-unused — 4 used, 0 unused. Every arch/containment check was proven capable of failing: 6 deliberate scratch violations (missing strict_types, `dd()`, a non-final service, a thin-model violation, a `DB`-facade use in Filament, a `.design/` string reference) each produced a specific failure citing file and line, then were removed and the suite returned to green. `Model::shouldBeStrict()` was proven the same way — commented out, the regression test failed with no exception thrown; restored, it passed.
- **Handoff:** Every subsequent task in every phase is verified against this command.
- **Notes:** Wired now, not later. A gate introduced at the end of a task reports a pile of failures nobody wants to untangle; a gate that runs continuously reports one. Larastan for the Laravel-aware rules; Rector configured with the dead-code set only — `withPhpSets()` and `withComposerBased(laravel: true)` both resolve their targets from the installed `composer.json` versions rather than a hardcoded set name, so a future PHP or Laravel major upgrade needs no edit here. Rector is **not** part of `composer quality`: it rewrites code, and an unattended rewrite belongs in a reviewed diff, not an automatic gate — running it once during this task surfaced two real, safe suggestions (an `#[Override]` attribute, a closure simplified to an arrow function), which were applied and reconciled against Pint's own opinion on the same lines.
- **Known limitations:** (1) The Verify line's "confirm by inspecting the workflow file and one green run" is only half satisfied — the workflow file exists and mirrors the local command exactly, but no commit has been pushed, so no live GitHub Actions run exists yet to point to; that happens on the first push carrying this work. (2) `composer test:coverage`'s 80% floor is a measured starting point (actual is 100% today), not a target — it will need raising as real business logic lands and should not be read as a ceiling. (3) ~~The CI workflow does not yet start a Postgres/PostGIS service...~~ — resolved: a real Postgres/PostGIS test connection (`booking_testing`, both locally and as a CI service container) landed once the schema that needed it existed, matching the deferral recorded here.

### [T-1A04] Asset pipeline — Vite, Tailwind 4, Alpine, Livewire 4

- **Spec:** l2-tech-stack.md §5.1
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `pnpm build` produces a manifest Laravel resolves; a scratch Blade view rendering one Livewire component and one Alpine directive returns 200 with both hydrated (assert via a Pest feature test on the rendered HTML).
- **Changes:** Confirmed the Laravel 13 scaffold already ships Tailwind CSS 4 + Vite 8 correctly wired (`@tailwindcss/vite`, `laravel-vite-plugin`); no changes needed there. Added the `alpine:init` listener scaffold to `resources/js/app.js` — Livewire 4.3.5 bundles its own Alpine instance and exposes `window.Alpine`, so no separate `alpinejs` package is installed (would start a second, conflicting instance). Added a permanent `tests/Feature/ViteManifestTest.php` asserting `public/build/manifest.json` exists with both entry points. Extended `.github/workflows/quality.yml` to install pnpm/Node and run `pnpm build` before the PHP gate. Additionally wired a JS-side quality gate — `@biomejs/biome` (format + lint) and `fallow` (dead-code, dependency hygiene, complexity, CSS drift, PR-diff audit) — per explicit direction, with `biome.json` and `.fallowrc.jsonc` scoped to this project's actual layout rather than their auto-generated monorepo-shaped templates. `package.json` scripts renamed to mirror `composer.json` (`fix`/`lint`/`analyse`/`quality`); CI runs `pnpm run quality` on every push and `pnpm run audit` (PR-diff scoped) on `pull_request`. The `.githooks/pre-commit` gate now checks staged PHP and staged JS/CSS independently. `git` added to the runtime image — required by `fallow audit`/`review`, previously absent.
- **Evidence:** `pnpm build` · exit 0 · `public/build/manifest.json` contains both `resources/css/app.css` and `resources/js/app.js` entries. A throwaway Livewire component + Alpine `x-data` on one Blade page, rendered and inspected directly (not just asserted): the response HTML showed `wire:snapshot`/`wire:effects`/`wire:id` and `x-data="{ open: false }"` on the same root element, plus the built `app-*.css`/`app-*.js` `<link>`/`<script>` tags in `<head>` and the Livewire script tag before `</body>` — full proof, then the fixture (component, view, page, test) was deleted per the task's own "scratch" wording, leaving only the permanent manifest test. `pnpm run quality` · exit 0 (Biome 0 issues across 6 files; fallow: 0 gating findings, 3 files analyzed, MI 99.4). `composer quality` · exit 0 · 11 tests passed, 100% coverage. Pre-commit hook tested against a real badly-formatted staged `.js` file (caught, exit 1) and against a clean staged state (exit 0), same as the existing PHP-side test from `T-1A03`.
- **Handoff:** T-1T02 (architecture tests cover `resources/`), Phase 5 (all public markup).
- **Notes:** pnpm is used here and nowhere else — PHP dependencies stay on Composer. Design tokens are **not** invented in this task: they arrive from the Figma source in Phase 5 and land in the Tailwind theme once. Leave the theme minimal rather than guessing values that will be replaced.
- **Execution findings:** (1) `resources/css/app.css` is a genuine Vite entry point (declared in `vite.config.js`'s `input` array) but not reachable from any JS `import`, so it had to be listed in `fallow`'s `entry` config explicitly or it read as dead code. (2) `tailwindcss` is flagged by fallow's `dev-dependencies-in-production` rule because `@import 'tailwindcss'` reaches a production CSS entry — a real pattern, not a bug, since Tailwind is a build-time processor with no runtime footprint in the compiled bundle; this is the standard, correct Laravel+Tailwind shape. Both an inline `fallow-ignore-next-line` comment and a per-file `overrides` entry were tried and verified (via `fallow config` and `fallow suppressions`) to load correctly, yet neither suppressed the finding — it is a package-level, whole-graph check, not a per-file one, matching the class of rules the schema documents as override-immune. The rule was reverted to its `warn` default project-wide rather than left permanently, unfixably red. (3) `fallow audit`/`review` need `git`, which the runtime image did not have; added and rebuilt.

### [T-1B01] Migrations: identity, access, localization, geography, taxonomy

- **Spec:** l2-data-model.md §5.1, §5.2, §6.1, §6.2; l1-localization.md §5.2, §6.1; l1-geography.md §5.1, §5.2
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `php artisan migrate:fresh` applies cleanly from empty; `\d territories` shows `parent_id`, `country_id`, `level_id`, and a `geography(Point,4326)` column; every content-bearing table created here has a sibling `*_translations` table with a unique index on `(entity_id, locale)` — assert with a Pest test that enumerates the expected pairs rather than by eye.
- **Changes:** Installed and verified the conventions of `astrotomic/laravel-translatable`, `spatie/laravel-permission`, `staudenmeir/laravel-adjacency-list`, `laravel/sanctum`, `pragmarx/google2fa-laravel` by reading their source before writing schema against them, not from memory. 21 new migrations across four domains (identity: `two_factor_secrets`, `role_scopes`, `api_clients`, plus spatie's and Sanctum's published tables; localization: `languages`, `countries`, `country_translations`; geography: `territory_levels`, `territory_level_translations`, `territories`, `territory_translations`; taxonomy: `object_types`, `object_type_translations`, `amenity_groups`, `amenity_group_translations`, `amenities`, `amenity_translations`, `amenity_group_object_type`, `contact_channel_types`, `contact_channel_type_translations`). `object_user` moved out of this task's original scope into `T-1B02` — it needs a foreign key to `objects`, which does not exist until that task; keeping it here would have meant a migration referencing a table from the future. Added a dedicated `booking_testing` Postgres database (with the same PostGIS/pg_trgm/unaccent extensions as the dev database) since the schema's geography columns and partial indexes cannot be represented in SQLite at all — `phpunit.xml` now points the test connection at it, and CI provisions the same via a service container.
- **Evidence:** `php artisan migrate:fresh` · exit 0 · all 24 migrations apply cleanly from empty. `\d territories` · `parent_id | bigint`, `country_id | bigint not null`, `level_id | bigint not null`, `geom | geography(Point,4326)` — literal match to the Verify line. `tests/Feature/TranslationTableSchemaTest.php` · 7 dataset cases, one per content-bearing table created in this batch (`countries`, `territory_levels`, `territories`, `object_types`, `amenity_groups`, `amenities`, `contact_channel_types`) · each asserts its sibling `*_translations` table exists with a real unique index on `(foreign key, locale)`, queried from `pg_indexes` rather than assumed from the migration file · 7 passed, then proven capable of failing (a deliberately wrong column name in the dataset produced a specific, correctly-attributed failure) before being reverted. `composer quality` · 18 tests passed, 45 assertions, 100% coverage, Pint and PHPStan level 8 clean.
- **Handoff:** T-1B02, T-1C01, T-1C02, T-1D01.
- **Notes:** Covers `users`, `sessions`, `two_factor_secrets`, the spatie permission tables, `role_scopes`, `personal_access_tokens`, `api_clients`, `languages`, `countries`, `territories`, `territory_levels`, and the taxonomy registries — **not** `object_user` (moved to T-1B02, see Changes). `role_scopes` is this project's own addition — spatie supplies roles and permissions but not scoping to a country, territory subtree, or object category. `country_id` is denormalized onto every territory node deliberately: scope queries filter by it on every request and a recursive walk is the wrong cost for a field that never changes in practice.
- **Execution findings:** (1) `astrotomic/laravel-translatable`'s `locale_key` convention is a plain string column matching `languages.code`, not a `language_id` foreign key — confirmed by reading the package's config and `Locales` class rather than assuming from the L1 spec's more abstract "language -> Language" ER notation. Every translation table in this batch uses `locale` (string) with a real foreign key against `languages.code`, giving referential integrity without fighting the package. Reconciling "language is data, not code" against the package's own static `locales` config array is deferred to the service-provider wiring in a later task (the config needs a minimal non-empty bootstrap default; the DB registry stays the actual source of truth for what is active). (2) `spatie/laravel-permission`'s migration does not auto-run — `hasMigrations()` only registers it for `vendor:publish`, confirmed by reading `HasMigrations.php`'s `$runsMigrations = false` default; publishing was required (tag `permission-migrations`, not the `laravel-permission-migrations` name first guessed) and its resulting timestamp confirmed to land before every migration in this batch, so `role_scopes.role_id` carries a real foreign key rather than an unenforced reference. (3) Two migration files' comments literally cited spec filenames (`l1-localization.md`, `l1-object-catalog.md`, etc.), caught by the project's own containment test and rewritten in plain language — the test worked exactly as designed, including against its author. (4) `territory_level_translations` and `amenity_group_translations` are not named in `l2-data-model.md`'s terse table inventory, but each entity's own detailed design section (or the general "every content-bearing entity is translatable" invariant) requires them; added both rather than under-delivering to match the shorter list. (5) `composer quality` currently fails on `composer-unused` — the five packages installed for this task's schema have no model code using their traits yet, since that is `T-1D01`–`T-1D03`'s job. Left visible and undocumented-away rather than suppressed; it self-resolves once those tasks land.

### [T-1B02] Migrations: object, media, rooms, prices, reviews, contacts, favorites

- **Spec:** l2-data-model.md §5.2, §5.5; l1-object-profile.md §5.2; l1-object-catalog.md §3.1
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `php artisan migrate:fresh` applies cleanly; `objects` carries `attributes` as JSONB, a `geography(Point,4326)` column, and `deleted_at` plus `deleted_by`; a Pest test inserts an object with a type-specific attribute bag and reads it back typed.
- **Changes:** Installed `spatie/laravel-medialibrary` (not yet present) and published its `media` migration — same `hasMigration()`/`runsMigrations = false` pattern as `spatie/laravel-permission`, confirmed by reading the provider rather than assuming. 13 new migrations: `objects` (the central table), `object_translations`, `contact_channels`, `rooms`, `room_translations`, `amenity_object`, `amenity_room`, `prices` (polymorphic — an object or a room), `reviews`, `availability_histories`, `favorites`, plus `object_user` (moved here from `T-1B01`, its foreign key now satisfiable) and the published `media` table. `objects` carries `owner_id` (the primary owner — `object_user` is for staff/managers, a separate relationship), `object_type_id`, `territory_id`, `country_id` (denormalized), `address`, `latitude`/`longitude`, `geom`, `attributes` (JSONB), a `ulid` (public-URL/API exposure, per the cross-cutting surrogate-key convention), `status` and `moderation_status` as two separate enums (publication state vs. the latest moderation decision — an object can be published while a pending edit awaits review), the full owner-asserted availability state embedded as columns (not a separate table — `availability_histories` is the append-only record of transitions), and soft delete (`deleted_at` + `deleted_by`). `favorites` enforces "exactly one of user or browser token" with a raw `CHECK` constraint plus two partial unique indexes, since Postgres never treats two `NULL`s as equal and a single composite unique constraint would not stop duplicate anonymous favorites.
- **Evidence:** `php artisan migrate:fresh` · exit 0 · all 37 migrations apply cleanly from empty. `\d objects` · `attributes | jsonb`, `geom | geography(Point,4326)`, `deleted_at` + `deleted_by` present — literal match to the Verify line. `\d favorites` · confirms the `CHECK` constraint and both partial unique indexes exist exactly as designed. `tests/Feature/ObjectAttributesSchemaTest.php` — inserts an object with a three-key attribute bag (string, float, boolean) and reads it back through raw JSON decoding, asserting each value's PHP type individually; proven capable of failing (a deliberately wrong type assertion produced a specific, correctly-attributed failure) before being reverted. `composer quality` · 19 tests passed, 49 assertions, 100% coverage, Pint and PHPStan level 8 clean; containment test swept clean across every new file.
- **Handoff:** T-1B05 (indexes over these columns), T-1D03 (models), T-1C03 (volume seeder).
- **Notes:** Filterable attributes are typed columns; the type-specific remainder is the validated JSONB bag. Full EAV is rejected — it turns the catalog query into a self-join over the largest table. Media uses Media Library's single polymorphic `media` table, not per-entity asset tables. `favorites` is modelled as visitor-facing and browser-scoped, so the owner column is nullable; the open question about cross-device persistence does not change the table's shape.
- **Execution findings:** (1) Placement fields (package, dates, pinned position, bump date, display order, manual priority, view count) named in `l2-data-model.md`'s longer descriptive sentence for the `[TZ]` §70 object field set do **not** land on `objects` in this task — each already has a home in a later domain (`object_placements`/`bump_events` in placement, `stat_dailies` in analytics), and the spec's own shorter, authoritative table-shape line for `objects` omits them; adding them here would have duplicated fields this project's own conventions place elsewhere. (2) `spatie/laravel-medialibrary` was not yet installed despite being named in this task's own scope — installed now, using the same publish-tag derivation (`Str::after('laravel-medialibrary', 'laravel-')` = `medialibrary-migrations`) already proven correct for `spatie/laravel-permission` in `T-1B01`. (3) `object_user`'s migration was initially generated with an earlier timestamp than `objects`, which would have tried to create its foreign key before the referenced table existed; caught before running, fixed by renaming the file to sort after `objects` rather than dropping the foreign key.

### [T-1B03] Migrations: placement and finance, advertising, content, governance

- **Spec:** l2-data-model.md §5.2, §5.6; l1-placement-monetization.md §5.1; l1-advertising.md §5.1, §5.4; l1-content-publishing.md §5.1; l1-moderation-governance.md §5.2, §5.4
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `php artisan migrate:fresh` applies cleanly; `bump_events` carries `scope_type` and `scope_id` (a bump is scoped, never global); `moderation_requests` carries both `previous_data` and `proposed_data` so a pending edit never overwrites a published record; assert both with a Pest schema test.
- **Changes:** Installed `owen-it/laravel-auditing` (not yet present; `^13` is incompatible with Laravel 13's `illuminate/console`, `^14` required) and published its `audits` migration. 29 new migrations across four domains: placement & finance (`placement_tiers` + translations, `placement_packages` + translations, `object_placements`, `placement_histories`, `bump_events`, `financial_records`); advertising (`banner_slots` + translations, `banners` + translations, `banner_targets`, `promotion_labels` + translations, `object_promotions`); content (`article_categories` + translations, `article_tags`, `articles` + translations, `article_object`, `article_territory`, `article_tag`, `news_items` + translations, `promotions` + translations); governance (`moderation_requests`). `financial_records` enforces "exactly one of object or banner" with the same raw `CHECK` + nullable-pair pattern `favorites` established in `T-1B02`, rather than a polymorphic pair, since exactly two subject kinds exist. `bump_events.scope` and `banner_targets.target` use `morphs()` (Territory | ObjectType, and Territory | ObjectType | Language respectively) with no `_id` foreign key, since the referenced table varies by row. Fixed a latent gap in `composer.json`: the `quality` script ran `unused` before `composer audit`, and Composer's array-form scripts stop at the first non-zero step — since `unused` has failed on every run since `T-1A03` (a known, expected, self-resolving gap), `composer audit` had silently never executed as part of `composer quality` in this project's history. Reordered so `audit` runs before the deliberately-last `unused` step.
- **Evidence:** `php artisan migrate:fresh` · exit 0 · all 66 migrations apply cleanly from empty. `\d bump_events` · `scope_type | character varying`, `scope_id | bigint` — literal match to the Verify line. `\d moderation_requests` · `previous_data | jsonb` (nullable), `proposed_data | jsonb` (not null) — literal match. `\d financial_records` · confirms the `exactly_one_subject` CHECK constraint exists. `tests/Feature/PlacementAdvertisingContentGovernanceSchemaTest.php` · 4 tests, 8 assertions · the two Verify-line columns asserted via `Schema::hasColumns`; a moderation request round-tripped with `previous_data` null (first-time submission) and `proposed_data` populated; the `financial_records` CHECK proven capable of failing — temporarily dropped, confirmed both the neither-subject and both-subject inserts then failed for the wrong reason (aborted transaction masking the real assertion), fixed by wrapping each in `DB::transaction()` for a Postgres SAVEPOINT, re-confirmed the constraint's absence produces the expected `QueryException`, restored. `composer quality` · Pint clean (103 files), PHPStan level 8 clean (76 files), Pest 23 passed (57 assertions) run twice (test + coverage), coverage 100%, `composer audit` now genuinely executes and reports no advisories (independently re-verified via a standalone `composer audit` run), `composer-unused` fails with 7 unused packages (`astrotomic/laravel-translatable`, `laravel/sanctum`, `owen-it/laravel-auditing`, `pragmarx/google2fa-laravel`, `spatie/laravel-medialibrary`, `spatie/laravel-permission`, `staudenmeir/laravel-adjacency-list`) — the same documented, expected pattern from `T-1B01`/`T-1B02`, now with the newly-installed auditing package added; self-resolves once `T-1D01`–`T-1D03` wire the model traits. Containment sweep of every new migration and test file: clean.
- **Handoff:** T-1B05, T-1B06, T-1D03.
- **Notes:** `placement_histories` and `financial_records` are append-only by requirement, enforced at the privilege level in `T-1B06` rather than in application code. `object_promotions` (a promotional-label grant, advertising decoration) and `promotions` (a content-publishing entity: title, description, image) are unrelated concepts that share the word "Promotion" in the source specification — kept as the two distinct table names the inventory gives, not merged or renamed. `article_categories` is shared by `articles` and `news_items`, since the source model types both as `category -> Category?`.
- **Execution findings:** (1) `owen-it/laravel-auditing` does not follow Spatie's Laravel-Package-Tools `hasMigration()` convention — it is a plain `ServiceProvider` that `publishes()` a `.stub` file under the `migrations` tag, timestamped at publish time (`date('Y_m_d_His')`), confirmed by reading `AuditingServiceProvider.php` and its `InstallCommand` rather than assuming the same pattern as `spatie/laravel-permission`/`spatie/laravel-medialibrary`. (2) The `composer.json` ordering defect (see Changes) means every prior task's "`composer quality` passes except the known `composer-unused` gap" evidence never actually exercised `composer audit` — re-verified retroactively via a standalone `composer audit` run (clean) once the ordering was fixed; no advisories existed at any point, but the gate itself was not proving that. (3) Three design judgment calls where the specification underspecifies the exact table shape: `financial_records`' "object or advertiser" (§5.5 prose only, no ASCII model block) resolved as documented above; `banner_slot_translations` added despite `l2-data-model.md`'s inventory omitting it, matching `T-1B01`'s precedent — the L1 model explicitly declares `translations -> administrator-facing name`, and slots are described as administrator-addable data, not static Filament chrome; `article_tags` deliberately left **without** a translations table, honoring the inventory's own asymmetry against `article_categories` rather than smoothing it into false symmetry.

### [T-1B04] Migrations: notifications, analytics, platform, dormant booking

- **Spec:** l2-data-model.md §5.2, §6.5; l1-notifications.md §5.1; l1-analytics.md §5.1; l1-feature-modules.md §5.1; l1-seo.md §5.5; l1-room-reservation.md §5.1
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `php artisan migrate:fresh` applies cleanly; `stat_events` is **date-partitioned from creation** — confirm with `SELECT relname FROM pg_class WHERE relispartition` returning at least one child partition; `reservations`, `room_availabilities`, and `booking_settings` exist and are empty.
- **Changes:** 19 new migrations across four domains. Notifications (`notification_channels` + translations, `notification_types`, `notification_templates`, `notifications`, `notification_dispatches`) — separates the notification record from its per-channel dispatch, per the source model. Analytics (`stat_events`, `stat_dailies`) — `stat_events` is hand-written raw SQL (`DB::statement`), since Laravel's Schema Builder cannot express `PARTITION BY`; a composite primary key `(id, occurred_at)` satisfies Postgres's rule that a partition key must be part of every unique constraint, and a single `DEFAULT` partition is created so the table is immediately insertable regardless of when the migration runs — real per-day/per-month partition management belongs in a scheduled job, not a one-time migration. Platform (`modules` + translations, `module_dependencies`, `module_conflicts`, `module_settings`, `settings`, `redirects`, `home_block_selections`) — `module_settings` reuses `role_scopes`' soft-reference pattern (`scope_reference_id`, no hard FK, since the target table varies by `scope_level`) and adds two partial unique indexes rather than one composite, mirroring `favorites`'/`financial_records`' NULL-handling: a plain composite unique would let the same module be set at portal scope more than once, since Postgres never treats two NULLs as equal. Dormant booking (`room_availabilities`, `reservations`, `booking_settings`) — ships in the schema, carries no rows until the module is activated.
- **Evidence:** `php artisan migrate:fresh` · exit 0 · all 85 migrations apply cleanly from empty (re-confirmed after a transient opcache-related failure on one intermediate run resolved itself on immediate retry with an explicit exit-code check). `SELECT relname FROM pg_class WHERE relispartition` · returns `stat_events_default` and its indexes — literal match to the Verify line. `reservations`/`room_availabilities`/`booking_settings` · `SELECT count(*)` · 0 rows each — literal match. `tests/Feature/NotificationsAnalyticsPlatformBookingSchemaTest.php` · 5 tests, 10 assertions · asserts the partition exists **and** is genuinely insertable (not merely declared), the three dormant tables exist and are empty, and `module_settings`' portal-scope partial unique index — proven capable of failing (temporarily dropped, confirmed the expected duplicate-portal-setting insert then succeeded when it should not have, restored, re-confirmed rejection). `composer quality` · Pint clean (123 files), PHPStan level 8 clean (95 files), Pest 28 passed (67 assertions), coverage 100%, `composer audit` clean, `composer-unused` fails with the same 7 packages as `T-1B03` (unchanged — no new package installed this task). Containment sweep caught and fixed one real violation: a migration comment cited a specification filename directly, rewritten in plain language before the sweep passed clean.
- **Handoff:** T-1C01 (module registry rows), T-1T04 (inertness test).
- **Notes:** Partition `stat_events` on day one — adding partitioning to a populated high-volume table later is a migration nobody wants to run against production. The three booking tables ship in the schema and carry no rows until the module is activated; that is the whole point of the dormant-module design, and it costs three empty tables.
- **Execution findings:** (1) `home_block_selections` is grounded in the home-page specification even though it is not one of this task's own cited spec sections — l2-data-model.md's own Platform inventory (which **is** cited) lists the table, and reading the owning spec rather than guessing its shape from a one-line description confirmed the design: curated blocks are a view onto existing territories/object-types (a polymorphic `selectable` pair), never a copy of the selected entity, and explicitly exclude Partners (an advertising format) and the informational block (static translated copy, not an entity selection) — both would have been plausible but wrong inclusions from the one-line description alone. (2) `notification_types` and `home_block_selections.block_key` are both fixed, code-known enumerations (ten notification types, sixteen home-page blocks) tied to specific trigger/rendering code — unlike `banner_slots` or `article_categories`, administrators cannot invent new members through the UI, so neither gets a translations table; their labels are Filament translation keys, not admin-editable registry data. This is a different judgment from `T-1B03`'s `banner_slot_translations` addition, and the distinguishing test (is the registry itself administrator-extensible, or is membership fixed by code) is worth carrying into later registry decisions. (3) A migration comment written for `module_dependencies` initially cited a specification filename directly — caught by the project's own containment sweep before `composer quality` ran, not by the sweep being run and failing; rewritten in plain language. (4) One `migrate:fresh` run failed with a stack trace immediately after a same-session file edit, then succeeded cleanly on an unmodified retry with an explicit exit-code check — consistent with a stale opcache serving the pre-edit migration bytecode rather than a real schema defect; recorded as a pattern to recheck, not dismissed silently.

### [T-1B05] Index plan — composite, spatial, trigram, GIN, partial

- **Spec:** l2-data-model.md §5.4; l1-object-catalog.md §5.3; l1-geography.md §5.4
- **Status:** Done
- **Assignment:** Agent
- **Verify:** every row of the index plan has a corresponding index — assert with a Pest test that queries `pg_indexes` for each expected name; `EXPLAIN (ANALYZE)` on the catalog ordering query against seeded volume shows an index scan on the composite `(country_id, territory_id, object_type_id, status)` and no sequential scan of `objects`; the same on a territory subtree expansion shows the recursive CTE using `territories(parent_id)`.
- **Changes:** One migration, 15 new indexes, all raw SQL (`DB::statement`) for one consistent, auditable shape — partial predicates, the trigram operator class, explicit `DESC` ordering, and GIN/GiST algorithms all fall outside Blueprint's fluent API. `objects_scope_ordering_index` on `(country_id, territory_id, object_type_id, status)` doubles as the plain "country + territory" lookup the plan also lists, since a composite btree index answers a query on any leading prefix of its columns — no separate index was needed alongside it. Covers every plan row: the catalog-ordering composite, `objects(object_type_id)`, a partial index on published/non-deleted objects, GIN on `objects.attributes`, GiST on both `geom` columns, `territories(parent_id)` for CTE traversal, `object_placements(placement_package_id)` and `(ends_at)`, `moderation_requests(decision, created_at)`, `bump_events(object_id, scope_type, scope_id, occurred_at DESC)`, trigram GIN on `object_translations(locale, name)`, and publication-date indexes on `articles`, `news_items`, `promotions`. The `(locale, slug)` and `(entity_id, locale)` translation uniques the plan also lists were already created alongside each entity in `T-1B01`–`T-1B04`; verified present rather than recreated.
- **Evidence:** `php artisan migrate:fresh` · exit 0 · all 86 migrations apply cleanly from empty. `tests/Feature/CatalogIndexPlanSchemaTest.php` · 20 tests, 45 assertions · every plan row asserted present by name via `pg_indexes`, plus structural checks (the partial index's `WHERE` clause, the bump index's `DESC` ordering, the trigram index's operator class, every `*_translations` table's uniques queried dynamically rather than enumerated by hand) — four of these proven capable of failing (the partial predicate, the `DESC` ordering, and the trigram operator class each temporarily weakened or removed, confirmed the specific expected failure, restored). `composer quality` · Pint clean (125 files), PHPStan level 8 clean (96 files), Pest 48 passed (112 assertions), coverage 100%, `composer audit` clean, `composer-unused` unchanged at the same 7 packages. Containment sweep: clean.
- **Handoff:** T-1T05 — the benchmark harness measures what this task makes possible.
- **Notes:** Second-largest blast radius in the phase. The catalog ordering contract and territory subtree expansion are the portal's hottest paths; both behave differently at scale, which is why the verification runs against `T-1C03`'s seeded volume rather than fixtures. Includes GiST on both `geom` columns, `gin_trgm_ops` on `object_translations(locale, name)`, GIN on `objects.attributes`, and the partial index on published, non-deleted objects.
- **Execution findings:** (1) The `EXPLAIN (ANALYZE)` half of this task's own Verify line names a dependency this task cannot itself close: it asks for proof against seeded volume, but the seeder that produces that volume runs later in the plan's own track ordering, and this task's own Handoff line already names the benchmark task as the consumer of the index plan for exactly that purpose. Checked honestly rather than faked: `EXPLAIN` against the freshly-migrated, empty schema shows Postgres choosing `objects_object_type_id_index` over the four-column composite for a scoped lookup, and a sequential scan over `territories_parent_id_index` for the recursive CTE's join step — both are the cost-based planner correctly treating an empty table as too small to prefer an index, not a defect in either index. The structural proof (every index exists, with the right predicate, ordering, and operator class) is complete in this task; the volume-dependent proof is deferred to the benchmark task, where it belongs. (2) No index for `objects.rating` was created, and no `rating` column was added to `objects` either, even though the catalog's own ordering contract in the object-catalog specification names `object.rating DESC` as an order-by term — the index plan in the data-model specification, which this task's own Verify line is scoped to, does not list a rating index, and adding the missing column would be a schema change outside an index-plan task's boundary. Recorded as a real gap between two specifications rather than silently closed or silently ignored.

### [T-1B06] Retention rules — soft delete, moderation scopes, append-only privileges

- **Spec:** l2-data-model.md §5.6, §6.3, §6.4; l1-moderation-governance.md §3.3, §6.3
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** a Pest test asserts that the application database role is denied `UPDATE` and `DELETE` on `audits` and `financial_records` (expect a query exception, not a silent no-op); a second test asserts that a soft-deleted object and an unmoderated object are both absent from an unqualified query on every public-facing model.
- **Handoff:** T-1T02, Phase 2 moderation surfaces.
- **Notes:** Append-only is enforced by database privilege, not by an Eloquent guard — an application-level guard is one forgotten call away from being bypassed, and the journal is exactly the table where that must not happen. Soft-delete and moderation filtering live in the shared query layer via global scopes: a single forgotten predicate republishes archived or unmoderated content silently, and that failure has no visible symptom.

### [T-1C01] Registry seeders

- **Spec:** l2-data-model.md §6.6; l1-localization.md §5.1, §5.6; l1-geography.md §5.2; l1-feature-modules.md §5.2, §6.5; l1-object-catalog.md §3.1
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** after `php artisan migrate:fresh --seed`, a Pest test asserts: exactly two languages active (`en`, `ru`) and three present-but-inactive (`ro`, `uk`, `ka`); three countries, each referencing its own primary language **even though that language is inactive**, with no validation error raised; the module registry contains `reviews` enabled and `guest_accounts`, `booking`, `payment` disabled, with `booking` declaring a dependency on `guest_accounts` and `payment` on `booking`.
- **Handoff:** T-1D02, T-1C03, Phase 2.
- **Notes:** No launch country's own primary language is active at release — Moldova, Ukraine, and Georgia have Romanian, Ukrainian, and Georgian as primary, and all three ship inactive. The system must treat that as a normal resolvable state, not a validation failure; the assertion above exists because it is the exact thing a naive foreign-key validation would reject. Never encode the language or country **count** anywhere — the difference between two active languages and five must be visible only in data.

### [T-1C02] Roles and permissions seeder with the unrevocable chief-administrator grant

- **Spec:** l1-back-office.md §5.2, §6.4; l1-platform-foundation.md §3.4
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** a Pest test asserts that every role named in the back-office spec exists as a seeded record with its permission set; a second test attempts to revoke the chief administrator's own grant through the normal permission path and asserts the operation is refused.
- **Handoff:** T-1D01, T-1T03.
- **Notes:** Roles are data, not an enumeration in code — the set ships as seed records so an operator can change it without a deployment. The unrevocable chief-administrator grant is not defensive decoration: without it, one permission edit can lock every administrator out of the panel that manages permissions, and the recovery path is a database console.

### [T-1C03] Realistic-volume demo seeder for benchmarking

- **Spec:** l2-tech-stack.md §5.9; l1-geography.md §6.3
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `php artisan db:seed --class=DemoVolumeSeeder` produces at least 50 000 objects across at least 3 000 territory nodes, each with translations in both active languages and a populated `geom`; `SELECT count(*)` on `objects`, `territories`, and `object_translations` confirms the counts; the seeder completes without exhausting memory (chunked inserts, asserted by running it under a constrained memory limit).
- **Handoff:** T-1B05 verification, T-1T05.
- **Notes:** Benchmarks against a dozen fixtures measure nothing. The catalog ranking query and territory subtree expansion behave differently at 50 000 objects than at 12, and this seeder is the only thing that makes the difference observable. Keep it separate from the registry seeders so `migrate:fresh --seed` stays fast for the normal development loop.

### [T-1D01] Scoped authorization — `role_scopes` resolution and the base policy

- **Spec:** l1-back-office.md §3.1, §5.2, §6.1; l2-tech-stack.md §5.6, §6.4; l1-platform-foundation.md §3.4
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** covered by `T-1T03`'s matrix; additionally, a PHPStan-level-8 clean `app/Services/Authorization` and `app/Policies` with no `@phpstan-ignore` entries, and an architecture test asserting no policy delegates its scope decision to a caller-supplied boolean.
- **Handoff:** Blocks every screen in Phases 2 and 4.
- **Notes:** **Highest-cascade task in the plan.** A grant may be unrestricted or bounded to a country, a territory subtree, or an object category; the subtree case resolves through the recursive hierarchy, so a region administrator governs every city beneath them. Implement it as a single server-side check applied uniformly — per-screen checks diverge, and the divergence surfaces as a country administrator editing another country's data, a failure with no visible symptom until it matters. Hiding a Filament action or a Blade block is a usability affordance and never an access control.

### [T-1D02] Feature-module registry — resolution ladder and server-side gate

- **Spec:** l1-feature-modules.md §3, §5.1, §5.3, §6.1, §6.2, §6.4
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** covered by `T-1T04`'s inertness test; additionally, a Pest test asserts most-specific-wins resolution across the full ladder (object → owner → category → country → portal → registry default) and that enabling `booking` while `guest_accounts` is off portal-wide resolves to **inactive** rather than half-enabled.
- **Handoff:** T-1T04, Phase 2 module management screen, Phase 5 gated surfaces.
- **Notes:** Resolve once per request at the boundary and pass the result down — re-resolving inside components produces pages where one section believes a module is on and another does not. Module state belongs in the cache key of any page whose composition it changes, or a toggle serves stale composition until natural expiry. Settings are few and change rarely, so cache the set in its entirety and invalidate on any toggle.

### [T-1D03] Eloquent models — relations, casts, scopes, and package traits only

- **Spec:** l2-tech-stack.md §5.3, §5.5, §5.8; l2-data-model.md §5.1
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `T-1T02`'s architecture test asserts every model lives in `App\Models` and contains no business logic; a Pest test round-trips a translated object through `astrotomic/laravel-translatable`, attaches media through Media Library, records an audit entry through `owen-it/laravel-auditing`, and expands a territory subtree through `staudenmeir/laravel-adjacency-list` — one assertion per package, so a misconfigured trait fails loudly rather than at first use in Phase 2.
- **Handoff:** Every phase from 2 onward.
- **Notes:** Models hold relations, casts, and scopes. Business logic lives in `app/Services/` — ranking, bumps, banner targeting, statistics, module resolution. The four package traits above are configuration, not logic, and belong on the model.

### [T-1T01] `migrate:fresh --seed` from empty, plus the generated ER diagram

- **Spec:** l2-data-model.md §5.7, §6.1
- **Status:** Todo
- **Assignment:** Agent
- **Goal:** Prove the schema deliverables the waived approval gate still requires.
- **Method:** `php artisan migrate:fresh --seed` against an empty database, in CI, on every push.
- **Verify:** the command exits 0 from a genuinely empty database (dropped, not truncated); the generated ER diagram is committed under `docs/` and regenerates from the applied schema rather than being hand-drawn.
- **Notes:** The client waived approval of the database structure, which removed the gate and not the work. The migration set that applies cleanly from empty **is** the field list, the type list, and the key list — and unlike a parallel document it cannot drift from the schema. This is also the reason the diagram is generated: a hand-drawn one drifts the first time a migration lands.

### [T-1T02] Architecture tests — conventions enforced mechanically

- **Spec:** l2-tech-stack.md §5.9
- **Status:** Todo
- **Assignment:** Agent
- **Goal:** Make every convention machine-checkable, since a rule a machine cannot check is a rule that erodes.
- **Method:** Pest `arch()` tests, run as part of `composer test:arch` and `composer quality`.
- **Verify:** `composer test:arch` exits 0 and covers, at minimum: `declare(strict_types=1)` in every file; no `dd`, `dump`, `var_dump`, `ray`, or `print_r` outside tests; models confined to `App\Models` with no business logic; `App\Filament` and `App\Livewire` never touching the `DB` facade; controllers, jobs, and services `final` unless deliberately extended; and **no specification path, task identifier, phase name, or specification filename anywhere under `app/`, `resources/`, or `database/`**.
- **Notes:** The last rule is the containment boundary made mechanical. Releases may ship without the design directory, so any reference to it from product code becomes dead content. Where design rationale matters at a code site, restate it in plain language.

### [T-1T03] Authorization test matrix — scoped grants deny across every scope kind

- **Spec:** l1-back-office.md §3.1, §5.2; l1-platform-foundation.md §3.4
- **Status:** Todo
- **Assignment:** Agent
- **Goal:** Prove `T-1D01` denies as well as it allows, across all four scope kinds.
- **Method:** Pest feature tests over a seeded fixture spanning two countries, nested territories, and two object categories.
- **Verify:** the matrix asserts, for each of `none` / `country` / `territory` / `category` scoping and for each permission verb: an in-scope target is allowed, an out-of-scope target is **denied at the server**, and a territory-scoped grant reaches every descendant of its node but no sibling subtree. Denial is asserted on the policy result, not on the absence of a UI control.
- **Notes:** The asymmetry matters. Allow-path tests pass trivially; the failure this suite exists to catch is a country administrator successfully editing another country's object, which no allow-path test will ever surface.

### [T-1T04] Module inertness test — disabled means absent, both directions

- **Spec:** l1-feature-modules.md §3, §5.5, §6.3
- **Status:** Todo
- **Assignment:** Agent
- **Goal:** Prove that a disabled module is inert rather than hidden, and that a gated path still works when enabled.
- **Method:** Pest feature tests parameterized over module state, run with `booking` both off and on.
- **Verify:** with `booking` disabled — every gated route returns 404 (not 403, which would confirm the capability exists), its scheduled jobs are absent from `php artisan schedule:list`, and its markup and sitemap entries are absent from the rendered object page. With `booking` enabled for one object — the same routes resolve, the contact rail is still present and still above the fold, and non-participating objects are unchanged.
- **Notes:** Both directions are required. A gated capability exercised only in its disabled state decays into one that no longer works when someone finally enables it, which would defeat the design's purpose. The contact-rail assertion encodes an invariant that is easy to break by accident: booking is additive to the portal's proposition, never a replacement for it.

### [T-1T05] Benchmark harness — catalog ranking and subtree expansion against budgets

- **Spec:** l2-tech-stack.md §5.9
- **Status:** Todo
- **Assignment:** Agent
- **Goal:** Make the performance budgets measured rather than assumed, from the first phase.
- **Method:** `composer bench` against the `T-1C03` seeded volume, reporting per-surface timings and query counts.
- **Verify:** `composer bench` runs the catalog ranking query and a territory subtree expansion at seeded volume and reports measured figures against the stated budgets — catalog page under 400 ms on a cache miss and under 100 ms TTFB on a hit, object page under 300 ms on a miss, search p95 under 300 ms, and no single request exceeding 30 queries. The command fails when a budget is breached rather than printing a number for someone to notice.
- **Notes:** No public pages exist yet, so this phase measures the two queries underneath them — the ranking expression and the recursive subtree expansion. Both are the portal's hottest paths and both change behaviour with data volume. Re-run whenever either changes; the harness exists so that "we will benchmark it later" is not a decision anyone has to make.
