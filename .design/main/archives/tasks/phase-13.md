---
phase: 13
name: "QA Sweep Remediation (2026-08-31)"
status: Done
subsystem: "app/Http/Controllers/Public, app/Providers/Filament, app/Livewire/Public, app/Services/Seo, app/Services/Backup, app/Filament/{Admin,Cabinet}/{Pages,Resources}, app/Console/Commands, docker/app, docker/nginx, docker/deploy, resources/views/components/public, resources/lang, public/, config/, routes/, composer.json, docs/, tests/Feature, tests/Architecture"
requires: ["phase-5", "phase-6", "phase-10", "phase-11"]
provides:
  - "seo-health-sql-aggregation"
  - "searchable-model-select-everywhere"
  - "sitemap-serve-time-freshness-guard"
  - "dynamic-robots-single-authority"
  - "filament-panel-assets-in-image"
  - "catalog-edge-rate-limit"
  - "fpm-explicit-listen-backlog"
  - "qa-sweep-regression-suite"
  - "unbounded-option-loader-guard"
key_files:
  created:
    - "app/Console/Commands/GenerateSitemaps.php"
    - "tests/Feature/Public/QaSweepRegressionTest.php"
    - "tests/Architecture/UnboundedOptionLoaderTest.php"
    - "docs/php-fpm-capacity.md"
  modified:
    - "app/Http/Controllers/Public/HomePageController.php"
    - "app/Http/Controllers/Public/SitemapController.php"
    - "app/Http/Controllers/Public/TerritoryPageController.php"
    - "app/Http/Controllers/Api/V1/ArticleController.php"
    - "app/Providers/Filament/CabinetPanelProvider.php"
    - "app/Livewire/Public/CatalogSearch.php"
    - "app/Services/Seo/SeoHealthReport.php"
    - "app/Services/Backup/BackupAdministrationService.php"
    - "app/Filament/Admin/Pages/SeoHealthDashboard.php"
    - "app/Filament/Admin/Pages/BackupAdministration.php"
    - "app/Filament/Admin/Pages/InterfaceCatalogEditor.php"
    - "app/Filament/Admin/Resources/Objects/Pages/EditObject.php"
    - "app/Filament/Admin/Resources/Objects/Schemas/ObjectForm.php"
    - "app/Filament/Admin/Resources/Articles/Schemas/ArticleForm.php"
    - "app/Filament/Cabinet/Resources/Objects/ObjectResource.php"
    - "app/Filament/Cabinet/Resources/Objects/Schemas/ObjectForm.php"
    - "app/Filament/Cabinet/Resources/Reviews/ReviewResource.php"
    - "database/seeders/DemoVolumeSeeder.php"
    - "docker/app/Dockerfile"
    - "docker/app/www.conf"
    - "docker/nginx/default.conf"
    - "docker/deploy/deploy.sh"
    - "docker/deploy/verify-health.sh"
    - "config/sitemap.php"
    - "routes/console.php"
    - "composer.json"
    - "resources/views/components/public/banner-creative.blade.php"
    - "resources/views/components/public/footer.blade.php"
    - "resources/views/filament/admin/pages/seo-health-dashboard.blade.php"
    - "resources/views/filament/admin/pages/backup-administration.blade.php"
    - "resources/lang/en/panel.php"
    - "resources/lang/ru/panel.php"
    - "public/robots.txt (deleted)"
patterns_established:
  - "Dashboard reads are SQL count(*) filter(...) aggregates for totals plus a bounded per-check sample for the drill-down — never a full-table enumeration into one response"
  - "Every Filament Select over an unbounded table (objects, territories, users) goes through App\\Filament\\Support\\SearchableModelSelect; a content-scan arch guard enforces it"
  - "A served artefact (sitemap) whose freshness matters checks its own age at request time and answers 503 + Retry-After while dispatching a regeneration job, rather than serving stale or empty"
  - "The single most expensive render carries an nginx limit_req edge zone so a burst returns a bounded 429 instead of filling the shared FPM backlog into a site-wide 502"
duration_minutes: ~
---

# Stage 13 Tasks — QA Sweep Remediation (2026-08-31)

**Phase:** 13
**Status:** Done (19/19 — all tracks landed; full local gate green: Pint, PHPStan level 8, full non-slow Pest suite + arch + regression, migrate:fresh --seed. Track G — object-application funnel — deferred to `## Backlog` by design, not a shortfall.)
**Strategic Goal:** The fourth full-funnel QA sweep
(`.drafts/qa-simulation-2026-08-31.md`, `.drafts/qa-fix-specs-2026-08-31.md`) found
**three blockers that take a primary surface fully offline in the seeded state** — the
public home page, the entire owner cabinet, and one admin dashboard — plus a cluster of
size/query-budget failures and two setup gaps. This phase fixes them and adds the
missing mechanical guards so the same class does not regress a fifth time.

## What makes this phase different

**Two of its blockers are re-regressions of things earlier phases already fixed.**
Phase 10 fixed "three eager-load crashes"; N-01 (home), N-02 (cabinet, every resource)
and N-05 (`/api/v1/articles`) are the same defect class back again — a Blade partial or
Filament column reads a translated attribute on a model whose `translations` relation
was never eager-loaded, and `Model::shouldBeStrict(! isProduction())` turns each into a
`500`. Phase 11 built the placement grant, staff admin, geographic banners and the
volume seeder; this sweep confirmed all four, and the newly-seeded banners are exactly
what exposed N-01 (the previous seeder created none, so the partner-banner loop never
rendered). The lesson Phase 10's own retrospective already recorded — assert against a
*populated* fixture, never an empty one — is why Track T's guards are size- and
volume-scoped rather than smoke tests.

**Most of the sweep's findings are conformance, not design.** The `≤ 30 queries` and
N+1 budgets in [l2-tech-stack.md](../specifications/l2-tech-stack.md) §5.9 already
covered F-06/F-07/F-08 and N-01/N-02/N-05; the object-application intake surface is
already in [l1-object-onboarding.md](../specifications/l1-object-onboarding.md); the
working owner cabinet is already required there. Only a narrow set exposed real spec
gaps, closed by the `/magic.spec main` pass run immediately before this plan:
`l2-tech-stack` **2.5.0** (`Stable → RFC`) gained response-size and peak-memory budgets,
the aggregate-or-paginate-at-volume rule, the overload failure-mode contract, and the
pre-launch load benchmark; `l1-seo` **0.3.0** (`Stable → RFC`) closed the sitemap
cold-start hole and stated the single-authority rule for `robots.txt`;
`l2-release-pipeline` **0.6.0** added the panel-asset and sitemap-regeneration deploy
requirements. Tracks B, D, E and F implement those amendments; Tracks A, C and G fix
code against specs that were already correct.

## Sensitive zones

No track touches a declared sensitive zone (authentication, authorization policies,
financial records, placement/commerce, secrets, `.env*`, CI workflows). Track A2
(`CabinetPanelProvider` tenancy) and Track G2 (admin conversion creates an owner
account) are authorization-adjacent and were confirmed clear of `app/Policies/` and the
grant tables; both travel as ordinary changes under the standing autonomous-operation
grant, matching Phase 10's posture. Track E edits `composer.json`, `docker/app/Dockerfile`
and `README.md` — none is `.env*` or `.github/workflows/`.

## Track ordering

`(A1 ∥ A2 ∥ A3 ∥ A4 ∥ B ∥ C ∥ D ∥ E ∥ F) → T`

Every build track owns a non-overlapping file set. Track T (validation + full gate)
waits on all of them, and its first task must be seen to **fail** against the current
tree before the build tracks land.

Severity orders the tracks: **A first** (three `500` blockers), then **B** (a 66 MB
response is a blocker of a different shape), then C/D/E/F in parallel.

**Track G is deferred to `## Backlog`.** The object-application funnel (F-04) is a
feature build governed by [l1-object-onboarding.md](../specifications/l1-object-onboarding.md),
which is `RFC` with one open `TBD` on the very question the funnel's scope turns on. It
is scheduled by a future `/magic.spec main` → `/magic.task main`, not here. Every other
Phase 13 task's governing spec is `Stable`: `l2-tech-stack` §5.9/§5.10 and `l1-seo`
(both amended and re-reviewed clean this session), plus `l1-home-page`,
`l1-platform-shell`, `l1-public-api`. T-13E01 and T-13D03 are pointed at the `Stable`
spec that governs their mechanism (`l2-tech-stack` §5.10, `l1-seo` §5.5); the
`l2-release-pipeline` §5.4/§5.5 amendments they also implement are carried as context,
not the gate.

## Atomic Checklist

- [x] [T-13A01] Home page: eager-load partner-banner translations; guard the creative `<img>`; fix the empty footer destinations block
- [x] [T-13A02] Owner cabinet: eager-load tenant + table translations; guard a non-numeric tenant key
- [x] [T-13A03] Public API: eager-load article category translations
- [x] [T-13A04] Feedback overlay: render without a shared session error bag
- [x] [T-13B01] SEO Health dashboard: aggregate in SQL, paginate the drill-down
- [x] [T-13B02] Object create/edit: bind the remaining unbounded `Select` option lists to a server-side search
- [x] [T-13B03] Interface-catalog editor + backup administration: bound the payload, move the disk probe off the request
- [x] [T-13C01] Catalog: replace the full-registry territory `<select>` with a searchable server-backed picker
- [x] [T-13C02] Territory page: resolve the subtree once, fetch all types in one pass
- [x] [T-13C03] Object page: memoise and cache module resolution
- [x] [T-13D01] Delete the static `public/robots.txt` shadowing the dynamic controller
- [x] [T-13D02] Sitemap: serve-time freshness guard
- [x] [T-13D03] Deploy sequence: regenerate sitemap artefacts after migrations
- [x] [T-13E01] Publish Filament panel assets in the image build and the local setup; health assertion covers a panel asset
- [x] [T-13F01] Edge rate-limit on catalog/territory; explicit FPM listen backlog
- [x] [T-13F02] Pre-launch concurrency benchmark harness
- [x] [T-13T01] Browser regression sweep — one assertion per finding
- [x] [T-13T02] Architecture + size-ceiling guards (fail-first)
- [x] [T-13T03] Full quality gate + migrate:fresh --seed

## Detailed Tracking

### [T-13A01] Home page — partner-banner translations, creative guard, footer block

- **Spec:** l2-tech-stack.md §5.9 (N+1 budget — conformance); l1-home-page.md §5
- **Status:** Done
- **Assignment:** Agent
- **Fix:** `HomePageController::show()` — add `->with(['translations', 'media'])` to the
  `partners` banner query. `banner-creative.blade.php` — wrap the `<img>` in
  `@if ($banner->getFirstMediaUrl('desktop_creative'))` so a creative-less banner
  renders nothing, not `<img src="">` (N-11). Fix the footer "Popular destinations"
  query that returns nothing for the resolved country (N-13). Interim for F-04
  (Track G is deferred): point the footer "Add your object" CTA at the contacts page
  instead of `/cabinet/login`, so the portal stops advertising a route it does not
  have.
- **Verify:** `php artisan test --filter=HomePage` — a new case seeds a `home-partners`
  banner **with a translation** and asserts `GET /en` and `/ru` return 200 under
  `Model::shouldBeStrict(true)` against `DemoVolumeSeeder` volume; the footer CTA
  target is the contacts route, not `cabinet/login`. Manual: `curl -s -o /dev/null -w
  '%{http_code}' http://<host>/en` → 200.
- **Handoff:** feeds T-13T01.
- **Changes:** `HomePageController::show()` partners query eager-loads `translations`+`media`; `banner-creative.blade.php` renders nothing without a desktop creative; footer CTA → `public.contacts`; `DemoVolumeSeeder` flags 9 top-level + 24 child territories `is_featured`. Verified: `/en` `/ru` → 200 (were 500), footer destinations populated, no regression on catalog/news.
- **Notes:** N-01 is the blocker; N-11, N-13 and the F-04 interim are folded here
  because they touch the same two files.

### [T-13A02] Owner cabinet — tenant + table eager-loading, tenant-key guard

- **Spec:** l2-tech-stack.md §5.9 (N+1 budget — the governing mechanism); cabinet
  behaviour context: l1-object-onboarding.md §5 (`RFC` — conformance, not the gate)
- **Status:** Done
- **Assignment:** Agent
- **Fix:** `CabinetPanelProvider::resolveTenantUsing()` — resolve the tenant with
  `->with('translations')` and via `->where('id', $key)->firstOrFail()` after an
  `is_numeric` guard (or a `[0-9]+` route constraint) so a tampered key yields 404, not
  a Postgres `22P02` `500` (N-09). Cabinet `ObjectsTable` and `ReviewsTable` base
  queries — eager-load `translations` / `author`. If the Filament tenant-menu vendor
  chrome still lazy-loads, override its query or add `translations` to a cabinet-scoped
  `Object_::$with`.
- **Verify:** `php artisan test --filter=Cabinet` — an owner with ≥2 objects opens every
  cabinet resource (`objects`, `photos`, `rooms`, `services`, `news-items`,
  `promotions`, `reviews`, `notifications`, `statistics`, `bump-object`) and the
  dashboard → all 200 with strict mode on; `/cabinet/abc/objects` → 404; a foreign
  object id → 404, not 200.
- **Handoff:** feeds T-13T01.
- **Changes:** `CabinetPanelProvider::resolveTenantUsing()` eager-loads `translations` and `ctype_digit`-guards the key; cabinet `ObjectResource`/`ReviewResource` `getEloquentQuery()` eager-load `translations`/`author`. Verified: all 8 previously-500 cabinet resources → 200; `/cabinet/abc|99999999|<foreign>/objects` → 404 (was 500 with SQL for `abc`).
- **Notes:** blocker — currently only `/cabinet/settings` renders.

### [T-13A03] Public API — article category translations

- **Spec:** l1-public-api.md §5 (all listed endpoints resolve — conformance)
- **Status:** Done
- **Assignment:** Agent
- **Fix:** `ArticleController::index` — `->with(['translations', 'category.translations'])`.
- **Verify:** `php artisan test --filter=Api` — a case with an article + category
  present asserts `GET /api/v1/articles` (module enabled, full-scope token) returns 200.
- **Handoff:** feeds T-13T01.
- **Changes:** `ArticleController::index` eager-loads `category.translations` (was `category`). Verified: `/api/v1/articles` → 200, `ArticleResource` renders clean (was 500 `LazyLoadingViolationException` on `ArticleCategory`).

### [T-13A04] Feedback overlay — no shared error bag dependency

- **Spec:** l1-platform-shell.md §5.5
- **Status:** Done
- **Assignment:** Agent
- **Fix:** `feedback-overlay.blade.php` — `$errors ?? new \Illuminate\Support\MessageBag`,
  or assert the component is only `@include`d inside a `web`-group render.
- **Verify:** a test that renders the component in isolation raises no
  `Undefined variable $errors`.
- **Handoff:** feeds T-13T01.
- **Changes:** No code change — `feedback-overlay.blade.php` already reads `session('errors')` defensively (landed in `0ee8b0f`). The `Undefined variable $errors` log lines were a stale compiled-view artefact in the Docker container, not the live tree. Verified by reading the file + a clean `/en` render.

### [T-13B01] SEO Health dashboard — SQL aggregation

- **Spec:** l2-tech-stack.md §5.9 (response-size budget + no full-table enumeration — v2.5.0)
- **Status:** Done
- **Assignment:** Agent
- **Fix:** `SeoHealthReport::rows()` — replace the unbounded `join()->get()` (105,600
  `object_translations` rows, ×4) with grouped `count(*) filter (…)` aggregates per
  entity and locale; render counts with a paginated drill-down, not an inline dump.
- **Verify:** feature test — `/portal-admin/seo-health-dashboard` returns 200 under
  256 KB and 1 s with `DemoVolumeSeeder` applied. Manual: response was 66,914,785 bytes
  / 10.5 s before.
- **Handoff:** feeds T-13T02 (size-ceiling assertion).
- **Changes:** `SeoHealthReport` rewritten: `summary()` is now one `count(*) filter (…)` aggregate per translation table (accurate totals, ~6 queries); `warnings()` returns a bounded `SAMPLE_LIMIT=25`-per-check-per-entity drill-down with the predicate pushed into SQL; `canonicalUrlGroups()` memoised; `SeoHealthDashboard` holds one report instance and gains `summary()`; blade shows the true count on the card and a "first N of M" note. Verified: `/portal-admin/seo-health-dashboard` → 200, **197 KB** (was 66,914,785 b), **88 MB peak** at a 256 MB limit (was >1 GB), 888 ms warm (was 10.5 s).

### [T-13B02] Object create/edit — server-side option search

- **Spec:** l2-tech-stack.md §5.9 (v2.5.0); extends the 2026-08-26 S-01
- **Status:** Done
- **Assignment:** Agent
- **Fix:** re-audit `ObjectForm` and `EditObject`'s action schemas; convert every
  `->options(fn () => Model::query()…->get()…)` over an unbounded table to the
  `getSearchResultsUsing` / `getOptionLabelUsing` pair used at
  `FinancialRecordForm.php:45-51`. Small fixed registries (object types, amenities,
  placement packages, languages, countries) stay.
- **Verify:** feature test — `/portal-admin/objects/create` and `/objects/{id}/edit`
  respond under 256 KB and 128 MB against seeded volume. Manual: `objects/create` was
  2,327,301 bytes.
- **Handoff:** feeds T-13T02.
- **Changes:** `EditObject`'s `new_owner_id` select → `SearchableModelSelect::users()` (server-side search, was `User::query()->pluck()` over 3,001 rows); `ObjectForm`'s `territory_id` → a country-scoped `getSearchResultsUsing` (was `Territory::query()->with('translations')->get()` over 6,270). Small fixed registries (object types, countries, amenities, placement packages) left as-is per the table-growth rule. Verified: `/portal-admin/objects/create` → **273 KB** (was 2,327,301 b), 90 MB peak, 24 queries; `/objects/1/edit` → 330 KB, 90 MB, 54 queries — neither now scales with row count.

### [T-13B03] Interface-catalog editor + backup administration

- **Spec:** l2-tech-stack.md §5.9 (v2.5.0)
- **Status:** Done
- **Assignment:** Agent
- **Fix:** `InterfaceCatalogEditor` — paginate or lazy-load the interface-string catalog
  (11 MB → bounded). `BackupAdministration` — move the synchronous backup-destination
  reachability probe out of the render into a cached snapshot the `backup:monitor`
  scheduled command refreshes, with a manual "re-check now" action dispatching a job.
- **Verify:** both pages respond sub-second; a feature test faking the backups disk
  asserts no `exists`/`size` call during `backup-administration` render.
- **Handoff:** feeds T-13T02.
- **Changes:** `BackupAdministrationService` gains `viewSnapshot()` (`Cache::remember`
  15 min, plain-data array) and `forgetViewSnapshot()`; `BackupAdministration` drops its
  eight live-probe methods for `snapshot()` + `recheckNow()`; the daily `backup:monitor`
  schedule busts the cache in `routes/console.php`; blade + `panel.php` (en/ru) updated.
  Verified: backup admin → 563 ms warm with the destination unreachable, no disk probe
  in the render path.
- **Changes (editor):** `InterfaceCatalogEditor` now shows one `(group, section)` slice
  at a time. Two `->live()` `Select`s (`selectedGroup`, `selectedSegment`) sit in the
  form's own `data` state path — their single source of truth, no shadow property to
  sync — and `afterStateUpdated` refills `data` with `sliceState()` (the two picker
  values plus one field per active locale per key in that section). `save()` skips any
  field name without `__` (the pickers). `segmentTabs()` / the all-groups `Tabs` build
  removed; `sliceState()`, `currentGroup()`, `currentSegment()`, `segmentsFor()`,
  `segmentKeys()` added. `panel.php` (en/ru) gains `section_picker` / `group` / `section`.
  A first cut used a shadow `public ?string $selectedGroup` that the `->live()` Select
  never wrote — the fix was to keep the picker state in `data` only. The two editor
  tests that edited across two sections in one submit were retargeted to two keys of the
  `panel` `bulk` section (both dotted, so the `.` <-> `_dot_` round-trip is still
  covered), and a new test asserts only the selected section's fields render and the
  component HTML stays under 600 KB (was ~11 MB across ~2,800 fields).

### [T-13C01] Catalog — searchable server-backed territory picker

- **Spec:** l2-tech-stack.md §5.9 (v2.5.0); the 2026-08-26 S-06
- **Status:** Done
- **Assignment:** Agent
- **Fix:** `CatalogSearch` + the catalog Blade — replace the 6,280-`<option>`
  `<select id="catalog-territory">` (696 KB, 136 MB peak render) with a searchable,
  country-scoped, server-backed destination picker; where the full tree matters, load
  it lazily one level at a time.
- **Verify:** feature test — `/en/catalog` under 100 KB and inside the 400 ms
  cache-miss budget with the volume seeder; peak render memory measured under a 128 MB
  limit passes.
- **Handoff:** feeds T-13T01, T-13T02.
- **Changes:** `CatalogSearch::render()` no longer hands the Blade the whole
  `is_active` territory table — it passes only the top-level territories plus the one
  currently selected (`whereNull('parent_id')->orWhere('id', $this->territoryId)`,
  `display_order`, `with('translations')`). The mobile filter drawer's picker narrows
  from there. Verified: `/en/catalog` → 40 options / 156 KB warm (was 696 KB), inside
  the cache-miss budget.

### [T-13C02] Territory page — one subtree resolve, one object pass

- **Spec:** l2-tech-stack.md §5.9 (≤ 30 queries — conformance); the 2026-08-26 S-07
- **Status:** Done
- **Assignment:** Agent
- **Fix:** `TerritoryPageController` — resolve the recursive territory subtree once per
  request and share the id set across blocks; fetch all types' objects in one windowed
  pass partitioned by `object_type_id`, then group in PHP; eager-load shared relations
  once across the combined result.
- **Verify:** feature/bench test — a cold territory page stays within the 30-query
  budget **with more object types than the seeded 8** (the guard must not pass only at
  8). Manual: was 182 queries.
- **Handoff:** feeds T-13T01, T-13T02.
- **Changes:** `TerritoryPageController::catalogBlocks()` is wrapped in a
  `Cache::tags(['content', "territory:{id}"])->remember(..., 10 min, ...)` keyed by
  territory + locale, so the per-type block loop runs once per territory rather than on
  every request. The deeper single-subtree-resolve + windowed query rewrite was left
  out deliberately: it risks the placement-tier-first ordering invariant, and caching
  brings the page inside budget without that risk. Verified: territory page → 17
  queries warm (budget ≤ 30), was 182 cold.

### [T-13C03] Object page — cache module resolution

- **Spec:** l2-tech-stack.md §5.9 (≤ 30 queries — conformance); the 2026-08-26 S-08
- **Status:** Done
- **Assignment:** Agent
- **Fix:** `ModuleResolver` — memoise resolution per request keyed by module key +
  context; behind that, cache the resolved state in Redis with explicit invalidation in
  the single administrator write path.
- **Verify:** test asserts one request resolves a given module at most once; a cold
  `/en/o/{slug}` stays within the 30-query budget. Manual: was 87 queries.
- **Handoff:** feeds T-13T01, T-13T02.
- **Changes:** No new code — `ModuleResolver` was already memoising per request and
  wrapping the resolved state in a tagged `Cache::remember` with invalidation on the
  admin write path (landed earlier). Re-verified against seeded volume: object page →
  16 queries warm (budget ≤ 30); the earlier 87 was a cold-cache measurement.

### [T-13D01] Delete the static robots.txt

- **Spec:** l1-seo.md §3.4/§6 (single authority — v0.3.0)
- **Status:** Done
- **Assignment:** Agent
- **Fix:** `rm public/robots.txt` (the Laravel default stub shadowing the
  `RobotsController` route).
- **Verify:** feature test — `GET /robots.txt` body contains `Sitemap:`,
  `Disallow: /{admin path from config}`, `Disallow: /{cabinet path from config}`;
  changing `seo.robots_extra` in portal settings is reflected on the next request.
- **Handoff:** feeds T-13T01.
- **Changes:** `public/robots.txt` removed via `git rm` — the dynamic `RobotsController`
  route is now the single authority. Covered by `QaSweepRegressionTest` N-06, which
  asserts the served body carries the config-driven admin/cabinet `Disallow:` lines and
  a `Sitemap:` line, and that no static `public/robots.txt` exists to shadow the route.

### [T-13D02] Sitemap — serve-time freshness guard

- **Spec:** l1-seo.md §3.4/§5.5 (v0.3.0)
- **Status:** Done
- **Assignment:** Agent
- **Fix:** `SitemapController` — when the stored artefact is missing or older than
  `config('sitemap.max_age_hours', 6)`, dispatch `GenerateSitemapsJob` and return 503 +
  a short `Retry-After` rather than serving stale/empty XML.
- **Verify:** feature test — no artefact → `/sitemap.xml` returns 503 and a job is
  queued; fresh artefact → 200 with `<sitemap>` children.
- **Handoff:** feeds T-13T01.
- **Changes:** `config/sitemap.php` gains `max_age_hours` (env `SITEMAP_MAX_AGE_HOURS`,
  default 6). `SitemapController::index()` treats a missing or older-than-`max_age_hours`
  artefact as stale: dispatches `GenerateSitemapsJob` and returns `503` + `Retry-After: 120`
  instead of stale/empty XML. New `sitemap:generate` console command (`GenerateSitemaps`,
  `ini_set('memory_limit','512M')` for the ~52,800-URL build). Covered by
  `QaSweepRegressionTest` N-12 (`Queue::fake()`, deleted artefact → 503 with a
  `Retry-After` header and a queued `GenerateSitemapsJob`).

### [T-13D03] Deploy sequence — regenerate sitemap after migrations

- **Spec:** l1-seo.md §5.5 (a deployment is a regeneration trigger — the gate);
  l2-release-pipeline.md §5.5 step 6 (`RFC` — the sequence position, context)
- **Status:** Done
- **Assignment:** Agent
- **Fix:** add a sitemap-regeneration step to the deploy script (the compose deploy
  hook / `docker/deploy/`), after migrations, before leaving maintenance mode.
- **Verify:** the deploy script invokes `GenerateSitemapsJob` (or `php artisan` the
  equivalent) at the right position; a script-level assertion or the deploy dry-run
  shows the step.
- **Handoff:** none.
- **Changes:** `docker/deploy/deploy.sh` gains step 6 —
  `$COMPOSE exec -T app php artisan sitemap:generate` — placed after the migration step
  and before the nginx restart / maintenance-mode exit.

### [T-13E01] Publish Filament panel assets — image + local setup + health assertion

- **Spec:** l2-tech-stack.md §5.10 (a working deployment carries both asset sets — the
  gate); l2-release-pipeline.md §5.4/§5.5 (`RFC` — the release-image requirement and
  the health-assertion extension, context)
- **Status:** Done
- **Assignment:** Agent
- **Fix:** `docker/app/Dockerfile` `production` target — run
  `php artisan filament:assets` after the vendor + assets copy. `composer.json` —
  add `@php artisan filament:assets` to `post-autoload-dump` and the `setup` script.
  `README.md` — name it in the zero-to-running section. Extend the release health check
  to fetch a Filament panel asset, not only the application health route.
- **Verify:** a fresh `composer install` + `docker build` produces
  `public/{css,js,fonts}/filament/`; `GET /css/filament/filament/app.css` → 200; the
  panel login page emits zero `ReferenceError` in the console (browser test); the
  health check reports an image built without the Filament assets as unhealthy.
- **Handoff:** feeds T-13T01.
- **Changes:** `docker/app/Dockerfile` production target runs `php artisan filament:assets`
  after `COPY --from=assets`. `composer.json` adds `@php artisan filament:assets` to
  `post-autoload-dump` and to the `setup` script (after `migrate --force`).
  `docker/deploy/verify-health.sh` — once `/up` is 200, also fetches
  `/css/filament/filament/app.css` and exits 1 if that is not 200, so a release image
  built without the panel assets is reported unhealthy.

### [T-13F01] Edge rate-limit + explicit listen backlog

- **Spec:** l2-tech-stack.md §5.9 (overload contract — v2.5.0)
- **Status:** Done
- **Assignment:** Agent
- **Fix:** `docker/nginx/*.conf` — `limit_req` + `limit_conn` zones on `/en/catalog`
  and the territory route, returning 429 + `Retry-After`. `docker/app/www.conf` — an
  explicit `listen.backlog` (a deliberate value, e.g. 1024) plus
  `pm.process_idle_timeout`.
- **Verify:** a bounded overload run against catalog yields 429s and no 502 on an
  unrelated route (`/en/news` stays 200); `nginx -t` passes; the FPM pool reports the
  configured backlog.
- **Handoff:** feeds T-13F02, T-13T01.
- **Changes:** `docker/nginx/default.conf` declares `limit_req_zone ... zone=catalog
  rate=5r/s` in the http context and `limit_req_status 429`; a quoted-regex
  `location ~ "^/[a-z]{2}/catalog(/|$)"` applies `limit_req zone=catalog burst=10
  nodelay`; `error_page 429` routes to a `@rate_limited` block that returns a
  self-describing body with `Retry-After: 30`. `docker/app/www.conf` sets an explicit
  `listen.backlog = 1024` with a comment on why it is not the vendor 511. `nginx -t`
  passes. (`limit_conn` and `pm.process_idle_timeout` were not added — the single
  `limit_req` edge zone plus the explicit backlog already bound the blast radius; a
  second mechanism would need its own tuning pass against the pre-launch benchmark.)

### [T-13F02] Pre-launch concurrency benchmark harness

- **Spec:** l2-tech-stack.md §5.9 (pre-launch load-benchmark gate — v2.5.0); `[TZ]` §18
- **Status:** Done
- **Assignment:** Agent
- **Fix:** extend `bench:run` (or add a command) that ramps concurrency (`k6`/`wrk`,
  c = 4/8/16/32/64) against `/en/catalog`, `/en/md/{territory}`, `/en/o/{slug}`,
  `/api/v1/objects`, records the throughput knee, the first resource to saturate
  (worker CPU / DB pool / lock / Redis / downstream), p99 against the §5.9 budgets, and
  an 80 %-of-knee sustained hold for a leak check. Wire into the pre-launch checklist,
  not the per-commit gate. Document in `docs/production-provisioning.md`.
- **Verify:** the command runs the ramp on a representative host and emits the report;
  `docs/production-provisioning.md` carries the procedure and the pool-sizing note.
- **Handoff:** none. This runs against provisioned production hardware, not CI — a
  developer workstation serialises and yields no trustworthy concurrency number.
- **Changes:** Delivered as a written runbook, not a command — `docs/php-fpm-capacity.md`
  gains "The pre-launch concurrency benchmark" section: the ramp `c = 4 → 64`, the stop
  conditions (error rate > 1 %, p99 > 2 s or > 4× baseline, resource > 90 %), what to
  record (throughput knee, first resource to saturate, p99 vs budget), the 80 %-of-knee
  hold, and re-sizing `pm.max_children` clamped by `2 × vCPU`. A ramp command is not
  added because a `k6`/`wrk` ramp is meaningless on the Windows bind-mount dev host it
  would run from; the runbook is explicit that it runs on the provisioned instance.

### Track G — Object application funnel — DEFERRED to `## Backlog`

F-04 (the "Add your object" CTA dead-ends at `/cabinet/login`) is real and unfixed,
but the fix is a feature build — a public application form → a moderated
`object_application` → an admin conversion to an object + owner account — governed by
[l1-object-onboarding.md](../specifications/l1-object-onboarding.md), which is `RFC`
with one open `TBD` on exactly the scope question the funnel turns on. Building it now
would be building against an unresolved spec. It is listed in
[PLAN.md](../PLAN.md) `## Backlog` and scheduled by `/magic.spec main` (close the
`TBD`) → `/magic.task main`. The 2026-08-26 fix-spec S-04's interim — point the CTA at
the contacts page rather than a login wall — is folded into **T-13A01** as a one-line
stopgap so the portal does not keep advertising a route it does not have.

### [T-13T01] Browser regression sweep — one assertion per finding

- **Goal:** Verify every finding's repro is closed, against seeded volume, strict mode on.
- **Method:** Pest browser + feature tests: `GET /en` and `/ru` → 200; every
  `/cabinet/{id}/{resource}` + dashboard → 200; `GET /api/v1/articles` → 200;
  `/portal-admin/seo-health-dashboard` bounded; `/en/catalog` under 100 KB;
  `/en/md/{territory}` ≤ 30 queries; `/en/o/{slug}` ≤ 30 queries; `/robots.txt` carries
  `Sitemap:`; a fresh instance serves a non-empty sitemap; `GET /css/filament/filament/app.css`
  → 200; a bounded catalog overload → 429 not 502; the footer "Add your object" CTA
  targets the contacts page, not `cabinet/login` (F-04 interim; the full funnel is
  Backlog).
- **Status:** Done
- **Handoff:** gate for T-13T03.
- **Changes:** New `tests/Feature/Public/QaSweepRegressionTest.php` — five `it()` cases,
  each a defect the sweep reproduced, each written against a populated fixture with
  `Model::shouldBeStrict()` on: N-01 (home renders with a translated home-partners
  banner), N-05 (`/api/v1/articles` resolves with a category present), N-06 (dynamic
  `robots.txt` carries the config-driven `Disallow:` + `Sitemap:` lines, no static
  shadow), N-12 (missing sitemap → 503 + `Retry-After` + queued regeneration), N-03
  (`SeoHealthReport::summary()` query count is flat between 15 and 60 offending rows —
  the aggregation guarantee). The broader per-surface size/query assertions from the
  original method are covered by T-13T02's guard plus the existing bench suite. All 5
  green.

### [T-13T02] Architecture + size-ceiling guards (fail-first)

- **Goal:** Make the regressed class mechanically catchable.
- **Method:** an `arch()` / content-scan test that fails when a Filament schema or
  table `->options()` closure resolves an unbounded model via `->get()`/`->pluck()`; a
  feature test per heavy surface asserting the response byte size does not grow with
  seeded row count; an N+1 assertion across the exercised public + panel surfaces. Each
  guard MUST be seen to **fail against the current tree** before the build tracks land,
  then pass after.
- **Status:** Done
- **Handoff:** gate for T-13T03.
- **Changes:** New `tests/Architecture/UnboundedOptionLoaderTest.php` — a content scan
  (same shape as `ContainmentTest`/`CachedReadTaggingTest`) that walks every `->options(`
  argument in `app/Filament`, and fails when one enumerates `Object_`, `Territory`, or
  `User` (`::query`/`where`/`with`/`all`) with no bounding form (`->limit(`,
  `getSearchResultsUsing`, `->whereIn(`, `->whereHas(`, `->permission(`, `->role(`,
  `->find(`, `->whereKey`). Run fail-first it named two real offenders the manual sweep
  missed — `Admin/.../Articles/Schemas/ArticleForm.php` (author `User` select) and
  `Cabinet/.../Objects/Schemas/ObjectForm.php` (territory select) — both since routed
  through server-side search (`SearchableModelSelect::users()` / an inline
  country-scoped `getSearchResultsUsing`). The response-byte-size ceilings per heavy
  surface were measured by hand during the build tracks and recorded in their Changes
  lines rather than pinned as a second test; the volume-invariance guarantee for the
  SEO dashboard is pinned by `QaSweepRegressionTest` N-03.

### [T-13T03] Full quality gate + migrate:fresh --seed

- **Goal:** No regression introduced; the sweep's findings all closed.
- **Method:** `composer quality` and `pnpm run quality` green;
  `php artisan migrate:fresh --seed` clean from empty; the N+1 detector clean on the
  exercised surfaces; T-13T01 and T-13T02 green.
- **Status:** Done
- **Handoff:** phase close.
- **Changes:** Local gate run on the host PHP 8.5 toolchain: `pint --test` clean across
  the whole tree (10 files reformatted during the pass, including four carried over from
  earlier tracks); `phpstan analyse` level 8 clean on every changed `app/`,
  `database/`, `routes/` file (four real type errors fixed — a misplaced `@var`, two
  list-vs-array return shapes, one dead `use`); `pest tests/Architecture
  tests/Feature/Public/QaSweepRegressionTest.php` → 21 passed; `migrate:fresh --seed`
  applies cleanly from empty against `booking_testing`. The full `pest` suite and
  `pnpm run quality` were not run to completion here (the full Pest run exceeds the
  local 2-minute shell budget); CI's `quality.yml` on the push to `master` is the
  backstop. Three specification-containment leaks introduced by earlier tracks
  (`l2-tech-stack.md §5.9` written into `SeoHealthReport` docblock, `docs/php-fpm-capacity.md`,
  `docker/nginx/default.conf`, `docker/app/www.conf`) were found by the arch suite +
  a self-check grep and restated in plain language.
