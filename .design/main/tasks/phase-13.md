---
phase: 13
name: "QA Sweep Remediation (2026-08-31)"
status: Todo
subsystem: "app/Http/Controllers/Public, app/Providers/Filament, app/Livewire/Public, app/Services/Seo, app/Services/Modules, app/Filament/Admin/{Pages,Resources}, docker/app, docker/nginx, resources/views/components/public, public/, composer.json, README.md, tests/Feature, tests/Architecture"
requires: ["phase-5", "phase-6", "phase-10", "phase-11"]
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 13 Tasks — QA Sweep Remediation (2026-08-31)

**Phase:** 13
**Status:** Todo
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

`(A1 ∥ A2 ∥ A3 ∥ A4 ∥ B ∥ C ∥ D ∥ E ∥ F ∥ G) → T`

Every build track owns a non-overlapping file set. Track T (validation + full gate)
waits on all of them, and its first task must be seen to **fail** against the current
tree before the build tracks land.

Severity orders the tracks: **A first** (three `500` blockers), then **B** (a 66 MB
response is a blocker of a different shape), then C/D/E/F/G in parallel.

## Atomic Checklist

- [ ] [T-13A01] Home page: eager-load partner-banner translations; guard the creative `<img>`; fix the empty footer destinations block
- [ ] [T-13A02] Owner cabinet: eager-load tenant + table translations; guard a non-numeric tenant key
- [ ] [T-13A03] Public API: eager-load article category translations
- [ ] [T-13A04] Feedback overlay: render without a shared session error bag
- [ ] [T-13B01] SEO Health dashboard: aggregate in SQL, paginate the drill-down
- [ ] [T-13B02] Object create/edit: bind the remaining unbounded `Select` option lists to a server-side search
- [ ] [T-13B03] Interface-catalog editor + backup administration: bound the payload, move the disk probe off the request
- [ ] [T-13C01] Catalog: replace the full-registry territory `<select>` with a searchable server-backed picker
- [ ] [T-13C02] Territory page: resolve the subtree once, fetch all types in one pass
- [ ] [T-13C03] Object page: memoise and cache module resolution
- [ ] [T-13D01] Delete the static `public/robots.txt` shadowing the dynamic controller
- [ ] [T-13D02] Sitemap: serve-time freshness guard
- [ ] [T-13D03] Deploy sequence: regenerate sitemap artefacts after migrations
- [ ] [T-13E01] Publish Filament panel assets in the image build and the local setup
- [ ] [T-13E02] Health assertion covers a panel asset
- [ ] [T-13F01] Edge rate-limit on catalog/territory; explicit FPM listen backlog
- [ ] [T-13F02] Pre-launch concurrency benchmark harness
- [ ] [T-13G01] Public object-application form → pending `object_application`
- [ ] [T-13G02] Admin application queue: review, convert, or reject
- [ ] [T-13T01] Browser regression sweep — one assertion per finding
- [ ] [T-13T02] Architecture + size-ceiling guards (fail-first)
- [ ] [T-13T03] Full quality gate + migrate:fresh --seed

## Detailed Tracking

### [T-13A01] Home page — partner-banner translations, creative guard, footer block

- **Spec:** l2-tech-stack.md §5.9 (N+1 budget — conformance); l1-home-page.md §5
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** `HomePageController::show()` — add `->with(['translations', 'media'])` to the
  `partners` banner query. `banner-creative.blade.php` — wrap the `<img>` in
  `@if ($banner->getFirstMediaUrl('desktop_creative'))` so a creative-less banner
  renders nothing, not `<img src="">` (N-11). Fix the footer "Popular destinations"
  query that returns nothing for the resolved country (N-13).
- **Verify:** `php artisan test --filter=HomePage` — a new case seeds a `home-partners`
  banner **with a translation** and asserts `GET /en` and `/ru` return 200 under
  `Model::shouldBeStrict(true)` against `DemoVolumeSeeder` volume. Manual: `curl -s -o
  /dev/null -w '%{http_code}' http://<host>/en` → 200.
- **Handoff:** feeds T-13T01.
- **Notes:** N-01 is the blocker; N-11 and N-13 are folded here because they touch the
  same two files.

### [T-13A02] Owner cabinet — tenant + table eager-loading, tenant-key guard

- **Spec:** l1-object-onboarding.md §5 (cabinet must work — conformance); l2-tech-stack.md §5.9
- **Status:** Todo
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
- **Notes:** blocker — currently only `/cabinet/settings` renders.

### [T-13A03] Public API — article category translations

- **Spec:** l1-public-api.md §5 (all listed endpoints resolve — conformance)
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** `ArticleController::index` — `->with(['translations', 'category.translations'])`.
- **Verify:** `php artisan test --filter=Api` — a case with an article + category
  present asserts `GET /api/v1/articles` (module enabled, full-scope token) returns 200.
- **Handoff:** feeds T-13T01.

### [T-13A04] Feedback overlay — no shared error bag dependency

- **Spec:** l1-platform-shell.md §5.5
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** `feedback-overlay.blade.php` — `$errors ?? new \Illuminate\Support\MessageBag`,
  or assert the component is only `@include`d inside a `web`-group render.
- **Verify:** a test that renders the component in isolation raises no
  `Undefined variable $errors`.
- **Handoff:** feeds T-13T01.

### [T-13B01] SEO Health dashboard — SQL aggregation

- **Spec:** l2-tech-stack.md §5.9 (response-size budget + no full-table enumeration — v2.5.0)
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** `SeoHealthReport::rows()` — replace the unbounded `join()->get()` (105,600
  `object_translations` rows, ×4) with grouped `count(*) filter (…)` aggregates per
  entity and locale; render counts with a paginated drill-down, not an inline dump.
- **Verify:** feature test — `/portal-admin/seo-health-dashboard` returns 200 under
  256 KB and 1 s with `DemoVolumeSeeder` applied. Manual: response was 66,914,785 bytes
  / 10.5 s before.
- **Handoff:** feeds T-13T02 (size-ceiling assertion).

### [T-13B02] Object create/edit — server-side option search

- **Spec:** l2-tech-stack.md §5.9 (v2.5.0); extends the 2026-08-26 S-01
- **Status:** Todo
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

### [T-13B03] Interface-catalog editor + backup administration

- **Spec:** l2-tech-stack.md §5.9 (v2.5.0)
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** `InterfaceCatalogEditor` — paginate or lazy-load the interface-string catalog
  (11 MB → bounded). `BackupAdministration` — move the synchronous backup-destination
  reachability probe out of the render into a cached snapshot the `backup:monitor`
  scheduled command refreshes, with a manual "re-check now" action dispatching a job.
- **Verify:** both pages respond sub-second; a feature test faking the backups disk
  asserts no `exists`/`size` call during `backup-administration` render.
- **Handoff:** feeds T-13T02.

### [T-13C01] Catalog — searchable server-backed territory picker

- **Spec:** l2-tech-stack.md §5.9 (v2.5.0); the 2026-08-26 S-06
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** `CatalogSearch` + the catalog Blade — replace the 6,280-`<option>`
  `<select id="catalog-territory">` (696 KB, 136 MB peak render) with a searchable,
  country-scoped, server-backed destination picker; where the full tree matters, load
  it lazily one level at a time.
- **Verify:** feature test — `/en/catalog` under 100 KB and inside the 400 ms
  cache-miss budget with the volume seeder; peak render memory measured under a 128 MB
  limit passes.
- **Handoff:** feeds T-13T01, T-13T02.

### [T-13C02] Territory page — one subtree resolve, one object pass

- **Spec:** l2-tech-stack.md §5.9 (≤ 30 queries — conformance); the 2026-08-26 S-07
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** `TerritoryPageController` — resolve the recursive territory subtree once per
  request and share the id set across blocks; fetch all types' objects in one windowed
  pass partitioned by `object_type_id`, then group in PHP; eager-load shared relations
  once across the combined result.
- **Verify:** feature/bench test — a cold territory page stays within the 30-query
  budget **with more object types than the seeded 8** (the guard must not pass only at
  8). Manual: was 182 queries.
- **Handoff:** feeds T-13T01, T-13T02.

### [T-13C03] Object page — cache module resolution

- **Spec:** l2-tech-stack.md §5.9 (≤ 30 queries — conformance); the 2026-08-26 S-08
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** `ModuleResolver` — memoise resolution per request keyed by module key +
  context; behind that, cache the resolved state in Redis with explicit invalidation in
  the single administrator write path.
- **Verify:** test asserts one request resolves a given module at most once; a cold
  `/en/o/{slug}` stays within the 30-query budget. Manual: was 87 queries.
- **Handoff:** feeds T-13T01, T-13T02.

### [T-13D01] Delete the static robots.txt

- **Spec:** l1-seo.md §3.4/§6 (single authority — v0.3.0)
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** `rm public/robots.txt` (the Laravel default stub shadowing the
  `RobotsController` route).
- **Verify:** feature test — `GET /robots.txt` body contains `Sitemap: `,
  `Disallow: /{admin path from config}`, `Disallow: /{cabinet path from config}`;
  changing `seo.robots_extra` in portal settings is reflected on the next request.
- **Handoff:** feeds T-13T01.

### [T-13D02] Sitemap — serve-time freshness guard

- **Spec:** l1-seo.md §3.4/§5.5 (v0.3.0)
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** `SitemapController` — when the stored artefact is missing or older than
  `config('sitemap.max_age_hours', 6)`, dispatch `GenerateSitemapsJob` and return 503 +
  a short `Retry-After` rather than serving stale/empty XML.
- **Verify:** feature test — no artefact → `/sitemap.xml` returns 503 and a job is
  queued; fresh artefact → 200 with `<sitemap>` children.
- **Handoff:** feeds T-13T01.

### [T-13D03] Deploy sequence — regenerate sitemap after migrations

- **Spec:** l2-release-pipeline.md §5.5 step 6 (v0.6.0)
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** add a sitemap-regeneration step to the deploy script (the compose deploy
  hook / `docker/deploy/`), after migrations, before leaving maintenance mode.
- **Verify:** the deploy script invokes `GenerateSitemapsJob` (or `php artisan` the
  equivalent) at the right position; a script-level assertion or the deploy dry-run
  shows the step.
- **Handoff:** none.

### [T-13E01] Publish Filament panel assets — image + local setup

- **Spec:** l2-release-pipeline.md §5.4 (v0.6.0); l2-tech-stack.md §5.10
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** `docker/app/Dockerfile` `production` target — run
  `php artisan filament:assets` after the vendor + assets copy. `composer.json` —
  add `@php artisan filament:assets` to `post-autoload-dump` and the `setup` script.
  `README.md` — name it in the zero-to-running section.
- **Verify:** a fresh `composer install` + `docker build` produces
  `public/{css,js,fonts}/filament/`; `GET /css/filament/filament/app.css` → 200; the
  panel login page emits zero `ReferenceError` in the console (browser test).
- **Handoff:** feeds T-13E02, T-13T01.

### [T-13E02] Health assertion covers a panel asset

- **Spec:** l2-release-pipeline.md §5.4/§5.5 (v0.6.0)
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** extend the release health check to fetch a Filament panel asset, not only
  the application health route, so an image built without the assets fails the
  assertion.
- **Verify:** a CI/smoke assertion — an image missing the Filament assets is reported
  unhealthy by the health check.
- **Handoff:** none.

### [T-13F01] Edge rate-limit + explicit listen backlog

- **Spec:** l2-tech-stack.md §5.9 (overload contract — v2.5.0)
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** `docker/nginx/*.conf` — `limit_req` + `limit_conn` zones on `/en/catalog`
  and the territory route, returning 429 + `Retry-After`. `docker/app/www.conf` — an
  explicit `listen.backlog` (a deliberate value, e.g. 1024) plus
  `pm.process_idle_timeout`.
- **Verify:** a bounded overload run against catalog yields 429s and no 502 on an
  unrelated route (`/en/news` stays 200); `nginx -t` passes; the FPM pool reports the
  configured backlog.
- **Handoff:** feeds T-13F02, T-13T01.

### [T-13F02] Pre-launch concurrency benchmark harness

- **Spec:** l2-tech-stack.md §5.9 (pre-launch load-benchmark gate — v2.5.0); `[TZ]` §18
- **Status:** Todo
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

### [T-13G01] Public object-application form → pending record

- **Spec:** l1-object-onboarding.md §5 (intake surface — conformance); the 2026-08-26 S-04
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** a public, rate-limited, Turnstile-protected form capturing the object
  basics, the owner's contact details, and the proposed territory + type → an
  `object_application` record in a pending state (never a live object, never a user
  account). Migration + Livewire component + route. Repoint the footer CTA at it.
- **Verify:** feature test — `POST /{lang}/object-application` stores a pending row and
  is rate-limited; the footer "Add your object" CTA target returns a page a logged-out
  visitor can act on (no login wall).
- **Handoff:** feeds T-13G02, T-13T01.

### [T-13G02] Admin application queue — review, convert, reject

- **Spec:** l1-object-onboarding.md §5; `[TZ]` §104 (create an object from an owner's application)
- **Status:** Todo
- **Assignment:** Agent
- **Fix:** a Filament resource listing pending `object_application` rows; a "convert"
  action creating the object + owner account in one operation and linking them; a
  "reject with reason" action; applicant notification on both outcomes.
- **Verify:** feature test drives both outcomes — convert creates the object and the
  owner account and links them; reject notifies and leaves no object.
- **Handoff:** feeds T-13T01.
- **Notes:** authorization-adjacent (creates an owner account) — confirmed clear of
  `app/Policies/`; travels as an ordinary change. Object creation stays staff-gated;
  the application is a request, not a write.

### [T-13T01] Browser regression sweep — one assertion per finding

- **Goal:** Verify every finding's repro is closed, against seeded volume, strict mode on.
- **Method:** Pest browser + feature tests: `GET /en` and `/ru` → 200; every
  `/cabinet/{id}/{resource}` + dashboard → 200; `GET /api/v1/articles` → 200;
  `/portal-admin/seo-health-dashboard` bounded; `/en/catalog` under 100 KB;
  `/en/md/{territory}` ≤ 30 queries; `/en/o/{slug}` ≤ 30 queries; `/robots.txt` carries
  `Sitemap:`; a fresh instance serves a non-empty sitemap; `GET /css/filament/filament/app.css`
  → 200; a bounded catalog overload → 429 not 502; the footer CTA → an actionable page.
- **Status:** Todo
- **Handoff:** gate for T-13T03.

### [T-13T02] Architecture + size-ceiling guards (fail-first)

- **Goal:** Make the regressed class mechanically catchable.
- **Method:** an `arch()` / content-scan test that fails when a Filament schema or
  table `->options()` closure resolves an unbounded model via `->get()`/`->pluck()`; a
  feature test per heavy surface asserting the response byte size does not grow with
  seeded row count; an N+1 assertion across the exercised public + panel surfaces. Each
  guard MUST be seen to **fail against the current tree** before the build tracks land,
  then pass after.
- **Status:** Todo
- **Handoff:** gate for T-13T03.

### [T-13T03] Full quality gate + migrate:fresh --seed

- **Goal:** No regression introduced; the sweep's findings all closed.
- **Method:** `composer quality` and `pnpm run quality` green;
  `php artisan migrate:fresh --seed` clean from empty; the N+1 detector clean on the
  exercised surfaces; T-13T01 and T-13T02 green.
- **Status:** Todo
- **Handoff:** phase close.
