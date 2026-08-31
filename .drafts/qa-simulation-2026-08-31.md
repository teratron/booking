# Full-funnel QA simulation — 2026-08-31

> [!IMPORTANT]
> This is the fourth end-to-end sweep, run five days after
> `qa-simulation-2026-08-26.md`. **Six of that sweep's findings are fixed**
> (F-02, F-03, F-10, F-12, F-13, F-16) and **all three headline "never built"
> clusters now exist** — the placement-grant admin surface (S-16), staff/role
> administration (S-17), and geographic banner rendering (S-18). This pass
> found the *new* regressions that landed alongside those additions.
>
> **Two blockers take the whole surface down in the seeded state:**
> the public home page (`/en`, `/ru`) and the entire owner cabinet both return
> HTTP 500. A third — the SEO Health dashboard — returns a 66 MB response.
> Read N-01, N-02, N-03 before anything else.

Driven end to end against a running instance at realistic volume:
**52,800 objects · 105,600 object translations · 84,480 contact channels ·
6,270 territories · 3,001 users · 40 banners · 60 news · 3,000 reviews · 30 audit rows**
(`migrate:fresh --seed` + `DemoVolumeSeeder`).

Every finding was reproduced against the live application and traced to a named
file and line. Where a suspicion turned out to be correct behaviour it is
recorded under "Checked and cleared".

## Environment

| Component | Value |
| --- | --- |
| PHP | 8.5.9 (host, Herd `php85`), opcache on |
| Laravel / Filament | 13.29.0 / 5.7.6 |
| PostgreSQL | 18 + PostGIS, `pg_trgm`, `unaccent` — host port 5433 (binds on this machine) |
| Redis / MinIO / Mailpit | running (Docker) |
| App under test | `http://localhost:8090` — host `php artisan serve`, native filesystem |
| Panels | admin `portal-admin` · cabinet `cabinet` (config, not hardcoded) |

Two environment facts shaped the method:

- **The Docker HTTP path is unusable for measurement.** Every request through
  the container's nginx+php-fpm takes 3.5–8.7 s *warm* — even `/api/v1/status`
  (8.6 s) and `/en/about` (the framework floor). The cost is the Windows bind
  mount re-reading `vendor/` per request, not application logic. The catalog
  page 504s at 60 s. All numbers below are from the host path.
- **Filament panel assets had to be published by hand** before any panel screen
  would render — see N-04. This is itself a finding, not a local quirk.

## Severity summary

| # | Severity | Finding | Status vs 2026-08-26 |
| --- | --- | --- | --- |
| N-01 | **Blocker** | Home page `/en` and `/ru` return HTTP 500 (Banner translations lazy-load) | new regression |
| N-02 | **Blocker** | The entire owner cabinet returns HTTP 500 on every resource + dashboard | new regression |
| N-03 | **Blocker** | SEO Health dashboard returns a 66 MB response in 10.5 s | new / newly reachable |
| N-04 | **High** | Filament panel assets 404 on a clean install — no `filament:assets` in setup/CI | new |
| N-05 | **High** | `GET /api/v1/articles` returns HTTP 500 (ArticleCategory translations lazy-load) | new |
| F-06 | **High** | Catalog territory `<select>`: 6,280 options, 696 KB, **136 MB peak render** | not fixed, worse |
| F-07 | **High** | Territory page issues **182 queries** (budget 30); 8× catalog-stack loop | not fixed, worse |
| F-04 | **High** | "Add your object" CTA still dead-ends at `/cabinet/login` | not fixed |
| N-06 | Moderate | `public/robots.txt` static stub shadows the dynamic `RobotsController` | new |
| N-07 | Moderate | `/portal-admin/objects/create` renders a 2.3 MB page (unbounded Select) | F-01 partially eradicated |
| N-08 | Moderate | `interface-catalog-editor` returns 11 MB; `backup-administration` takes 7.4 s | new |
| N-09 | Moderate | Cabinet tenant key unvalidated → `/cabinet/{non-numeric}/…` 500s with SQL in body | new |
| F-08 | Moderate | Object page issues **87 queries** (budget 30); module resolution uncached | not fixed, worse |
| N-10 | Low | `feedback-overlay.blade.php` reads `$errors` outside a request context | new |
| N-11 | Low | `banner-creative.blade.php` emits `<img src="">` when a banner has no media | new |
| N-12 | Low | `/sitemap.xml` serves stale/empty until the hourly job first runs; no deploy-time regen | new |
| N-13 | Low | Footer "Popular destinations" list renders empty on the catalog page | new |

Fixed since 2026-08-26 and re-verified: **F-02, F-03, F-10, F-12, F-13, F-16, F-05**.
Built since 2026-08-26 and re-verified: **S-16, S-17, S-18**.

## Blockers

### N-01 · Home page `/en` and `/ru` return HTTP 500

**Where:** `app/Http/Controllers/Public/HomePageController.php:72` ·
`resources/views/components/public/banner-creative.blade.php:13` ·
`resources/views/public/home/show.blade.php:179`

**Repro:** open `/en` or `/ru` in the seeded state.

**Observed:** HTTP 500, `Illuminate\Database\LazyLoadingViolationException`:

```
Attempted to lazy load [translations] on model [App\Models\Banner]
  (View: …/components/public/banner-creative.blade.php)
```

**Root cause.** The three `forSlot()` banner slots eager-load their translation
(`BannerSelectionService::forSlot()` ends in `Banner::query()->with('translations')->find(...)`).
The **partners** collection does not:

```php
'partners' => Banner::query()
    ->whereHas('slot', fn ($q) => $q->where('key', 'home-partners'))
    ->where('is_active', true)->get(),        // no ->with('translations')
```

`home/show.blade.php:179` renders each partner through
`<x-public.banner-creative :banner="$partner" />`, whose line 13 reads
`$banner->link_text` — a translated attribute
(`Banner::$translatedAttributes = ['link_text']`) — triggering a lazy load that
`Model::shouldBeStrict(! isProduction())` (`AppServiceProvider:97`) turns into a
hard exception.

**Why the previous sweep did not catch it.** `DemoVolumeSeeder` seeded no
banners on 2026-08-26, so the `partners` collection was empty and the loop never
rendered. It now seeds 40 banners including the `home-partners` slot.

**Blast radius.** In production (`shouldBeStrict` off) the page renders but
issues one uncached translation query per partner banner on every home request —
an unbounded N+1 on the portal's most-hit page. In local, CI, and staging it is
a hard 500 on the front door.

**Fix:** `->with('translations')` on the `partners` query (and on the `media`
relation it also touches — see N-11). Add a home-page feature test that seeds a
`home-partners` banner and asserts 200; the empty fixture is what hid this.

### N-02 · The entire owner cabinet returns HTTP 500

**Where:** `vendor/filament/filament/.../tenant-menu.blade.php` (unguarded) ·
`app/Filament/Cabinet/Resources/Objects/Tables/ObjectsTable.php:39` ·
the cabinet Reviews table (`Review::author`) ·
`app/Providers/Filament/CabinetPanelProvider.php:72`

**Repro:** sign in as an object owner, open any cabinet resource
(`/cabinet/{ownedObjectId}/objects`, `/photos`, `/services`, `/promotions`,
`/reviews`, `/notifications`, `/statistics`, `/bump-object`).

**Observed:** HTTP 500 on every one of them, and on the dashboard. Only
`/cabinet/settings` (registered `isSimple: true`, so it renders no tenant chrome)
returns 200. Exceptions, by screen:

```
tenant-menu.blade.php   Attempted to lazy load [translations] on App\Models\Object_
ObjectsTable.php:39     Model->__get('name')  →  lazy load [translations]
Reviews table          Attempted to lazy load [author] on App\Models\Review
```

**Root cause.** `resolveTenantUsing()` returns
`Object_::withoutGlobalScope(ModerationScope::class)->findOrFail($key)` with no
`->with('translations')`. The moment any chrome or table column reads the
tenant's translated `name`, strict mode throws. The Filament **tenant menu** is
vendor chrome rendered on every full-layout cabinet page, so it takes down every
resource, not just the objects list.

**Why the previous sweep did not catch it.** 2026-08-26 reported "16/16 cabinet
screens render, 11–24 queries each". Between then and now the cabinet was
regressed on eager-loading (or strict mode began firing on this path). Either
way the owner cabinet — the entirety of `[TZ]` §29–§43 — is currently
inaccessible.

**Fix:** eager-load `translations` in `resolveTenantUsing()`, in the cabinet
`ObjectsTable` base query, and in the cabinet Reviews table (`author`). Where the
vendor tenant menu is the offender, override the panel's tenant-menu query or add
`translations` to `Object_::$with` for the cabinet context. Add a cabinet
feature test per resource that asserts 200 for an owner with ≥1 object — the
prior "16/16" claim passed because it never rendered the tenant menu against a
strict-mode model with unloaded translations.

### N-03 · SEO Health dashboard returns a 66 MB response in 10.5 s

**Where:** `app/Services/Seo/SeoHealthReport.php:198-206` (`rows()`)

**Repro:** open `/portal-admin/seo-health-dashboard` as any role that may reach it.

**Observed:** HTTP 200, **10,559 ms**, **75 queries**, **66,914,785 bytes** of HTML.

**Root cause.** `rows()` is an unbounded hydrate:

```php
return DB::table("{$spec['translations']} as tr")
    ->join("{$spec['table']} as base", 'base.id', '=', "tr.{$spec['fk']}")
    ->select([...])
    ->get();              // no limit, no pagination, no aggregation
```

It is called four times per entity type (missing-metadata, missing-title,
non-indexable, missing-canonical), each pulling **all 105,600 `object_translations`
rows** into PHP and appending one row per offender to a `$rows[]` array the page
renders in full.

**Fix:** aggregate in SQL (`count(*) … where seo_title is null`, grouped by
entity and locale) and render counts with a drill-down, or paginate the offender
list. The page must not enumerate every translation row of a 52,800-object
catalog.

## High

### N-04 · Filament panel assets 404 on a clean install

**Where:** `.gitignore:94-97` (correctly ignores `public/vendor/`,
`public/css/filament/`, `public/js/filament/`, `public/fonts/filament/`) ·
`composer.json` `scripts` (no `filament:assets`)

**Observed:** on a fresh checkout every Filament asset 404s —
`/css/filament/filament/app.css`, `/js/filament/*/*.js`, the Inter fonts — so
both panels render unstyled and dead (`filamentSchema is not defined`,
`filamentActionModals is not defined`, 26 console errors on the login page
alone). `composer.json`'s `post-update-cmd` runs
`vendor:publish --tag=laravel-assets` but never `filament:assets`; the `setup`
script, the CI workflow, the Dockerfiles, and `README.md` do not mention it
either.

**Fix:** add `@php artisan filament:assets` to `post-autoload-dump` (Filament
normally self-registers this via `filament:upgrade` — something in this project's
`post-autoload-dump` override displaced it) **and** to the `setup` script, and
name it in `README.md`. Guard with a smoke test that fetches
`/css/filament/filament/app.css` and asserts 200.

### N-05 · `GET /api/v1/articles` returns HTTP 500

**Where:** `app/Http/Resources/Api/V1/ArticleResource.php:30` ·
`app/Http/Controllers/Api/V1/ArticleController.php` (index eager-loads)

**Observed:** with a full-scope token, every `/api/v1/*` endpoint returns 200
**except** `/articles` → 500:

```json
{"message":"Attempted to lazy load [translations] on model [App\\Models\\ArticleCategory]…",
 "exception":"Illuminate\\Database\\LazyLoadingViolationException"}
```

`ArticleResource:30` reads `$article->category->name` (translated). The index
loads `category` but not `category.translations`. The debug payload also leaks
the class, file path and partial trace because `APP_DEBUG=true` locally — a
generic 500 in production, but still a 500.

**Fix:** `->with(['category.translations', 'translations'])` in
`ArticleController::index`. Add an API feature test hitting `/articles` with data
present (an empty table hides it).

### F-06 · Catalog territory `<select>` — unchanged, now a memory risk

`/en/catalog`: **696 KB**, **1.7 s warm**, **6,280 `<option>` elements** in one
`<select id="catalog-territory">`, **peak 136 MB** to render. Was 641 KB on
2026-08-26. The filter drawer (new, see "Checked and cleared") relocated the
control but did not bound it. 136 MB exceeds a common 128 MB php-fpm pool — the
same failure mode as the old F-01 blocker, now on a public page. See
`qa-fix-specs-2026-08-26.md` S-06; still applies verbatim.

### F-07 · Territory page — 182 queries (was 159)

`/en/md/territory-1` cold: **182 queries**, 2.0 s, 174 KB. The per-object-type
loop in `TerritoryPageController` still runs the whole catalog stack once per
active type — the query log shows exactly `8×` of every shape (count, objects
select, translations, object_types, amenities, contact_channels, media,
reviews). The count grew with the type registry (8 types now). See
`qa-fix-specs-2026-08-26.md` S-07; still applies.

### F-04 · "Add your object" CTA still dead-ends

Footer CTA still links to `http://localhost:8090/cabinet/login`, which offers no
registration and `ObjectResource::canCreate()` is still `false`. No public
application route. See S-04; still applies.

## Moderate

### N-06 · `public/robots.txt` shadows the dynamic controller

`public/robots.txt` (24 bytes, the Laravel default stub, dated Aug 7) is served
as a static file before routing, so `RobotsController::__invoke()` — which emits
`Disallow: /portal-admin`, `Disallow: /cabinet`, a `Sitemap:` line, and the
`seo.robots_extra` admin setting — is unreachable dead code. Crawlers get
`User-agent: *` / `Disallow:` (allow everything) with no sitemap reference.

**Fix:** delete `public/robots.txt`. Add a feature test asserting `/robots.txt`
contains the `Sitemap:` line and the two panel `Disallow` entries.

### N-07 · `/portal-admin/objects/create` renders a 2.3 MB page

**2,327,301 bytes**, 2.4 s, 10 queries. The F-01 disease (eager `Select`
option hydration) survives on the object create/edit forms —
`news-items/create` (the 2026-08-26 example) is fixed at 252 KB, but
`objects/create` is not, and `objects/{id}/edit` is 1 MB. See S-01; sites list
needs re-auditing against the current form schemas.

### N-08 · Two more oversized / slow admin screens

- `/portal-admin/interface-catalog-editor` → **11,019,122 bytes**, 1.6 s.
- `/portal-admin/backup-administration` → **7,359 ms** (2 queries) — a
  synchronous backup-destination reachability probe inside the web request.
  Move it to a cached health snapshot the scheduler refreshes.

### N-09 · Cabinet tenant key is not validated

`CabinetPanelProvider:72` — `resolveTenantUsing(fn (string $key) =>
Object_::…->findOrFail($key))`. A non-numeric `$key` makes Postgres throw
`SQLSTATE[22P02] invalid input syntax for type bigint` **before** `findOrFail`
can raise `ModelNotFoundException`, so `/cabinet/abc/objects` returns HTTP 500
with the SQL statement in the body (`APP_DEBUG`) instead of 404. A tampered
tenant segment should 404.

**Fix:** `Object_::…->where('id', $key)->firstOrFail()` after an `is_numeric`
guard, or cast the route parameter with a `where('tenant', '[0-9]+')` constraint.

### F-08 · Object page — 87 queries (was 77)

`/en/o/{slug}` cold: **87 queries**, 1.3 s. `modules` (2×) and `module_settings`
(2×) still resolved uncached; `media` fetched 4×. See S-08; still applies.

## Low

- **N-10** — `feedback-overlay.blade.php` reads `$errors` in a context where the
  session error bag is not shared (seen in the test log:
  `Undefined variable $errors (View: …/feedback-overlay.blade.php)`). Wrap in
  `$errors ?? new \Illuminate\Support\MessageBag` or render only inside the web
  middleware group.
- **N-11** — `banner-creative.blade.php:13` emits `<img src="">` when a banner
  has no `desktop_creative` conversion, producing a broken-image icon. Guard the
  `<img>` behind `@if ($banner->getFirstMediaUrl('desktop_creative'))`.
- **N-12** — `/sitemap.xml` serves "whatever the job last wrote"
  (`SitemapController` docblock) with no freshness bound and no deploy-time or
  on-demand trigger. On a fresh deploy it 404s until the hourly job first runs;
  in this environment it served an empty `<sitemapindex>` (from pre-seed
  scheduler runs) until regenerated by hand. Generation itself is correct against
  volume — 52,800 object URLs across `object-1.xml`…`object-6.xml`, 6,270
  territories, 20 children, index intact. Run `GenerateSitemapsJob` at the end of
  deploy, and/or have `SitemapController` 503 + dispatch when the artefact is
  older than N hours.
- **N-13** — the catalog footer's "Popular destinations" block renders an empty
  `<list>` (the query returns nothing for the resolved country context).

## Three-way reconcile — plan ↔ specification ↔ design

| Class | Item |
| --- | --- |
| **Regression** | N-01 home page, N-02 owner cabinet — both worked on 2026-08-26 |
| **Regression** | F-06/F-07/F-08 query & payload counts all grew |
| **Missing feature** | `[TZ]` §100 password recovery — no `password/forgot/reset` route, no link on either login screen |
| **Missing feature** | `[TZ]` §13 promotions index — `/en/promotions` 404s |
| **Missing feature** | `[TZ]` §110 amenities/services admin — no Filament resource; adding an amenity needs a migration |
| **Missing feature** | `[TZ]` §103 object-list territory filter — a `territory_id` column and a `move_territory` bulk action exist, no `SelectFilter` |
| **Drift** | `[TZ]` §101 dashboard — 2 of 6 financial metrics; "Recorded payments" window hardcoded to 30 days |
| **Drift** | Object types — 8 of 17 named catalog sections seeded (recreation bases, cottages, campings, bars, ski resorts, … absent) |
| **Improvement — kept** | Placement grant / staff admin / banner rendering (S-16/17/18) all built since the last sweep |
| **Divergence — confirm with client** | `StaffPolicy` makes staff/role administration **chief-administrator-only**. `[TZ]` §121 implies a country administrator manages users; a documented "future amendment" note sits in the policy. Confirm whether scoped delegation is in scope for launch. |
| **Divergence — confirm with client** | Five launch languages in `[TZ]` §1 vs two (en, ru) built — the standing project decision, unchanged |

The `[TZ]` §26–§60 slice (territory / pricing detail) was **not swept** this
pass, same as 2026-08-26.

## Presentation fidelity

**Not assessed against the Figma source** — the Figma MCP is rate-limited to
zero on the Starter plan and stayed exhausted across the day boundary. Judged
against platform convention, internal consistency and captured screenshots only:

- Public pages render but every image is a broken-placeholder box (the seeder
  creates media rows without files — expected in seed, not a defect).
- The catalog and object pages are heavily washed in the amber brand colour;
  cards are functional (title, territory, view count, contact chips, Details,
  "Vacancies available"). Grid-vs-list clipping (F-09) was not re-confirmed —
  the new drawer defaults the catalog to list mode.
- The Filament panels, once assets were published (N-04), render with correct
  chrome, sidebar groups, and header actions.
- The 2026-08-26 Figma findings (F-09 card layout in a grid cell, F-14
  booking-engine UI present in Figma but correctly omitted from the build) were
  **not re-verified against source** this pass.

## Load results

Two rigs were used. **Rig A** — host `artisan serve` (single PHP process,
serialises), for the accurate single-request cost. **Rig B** — the Docker
`nginx` + `php-fpm` stack (`pm.max_children=32`) with
`opcache.validate_timestamps=0` and `memory_limit=512M` set for the run, for a
concurrency attempt. The QA overrides were removed and the app container
force-recreated to its shipped state afterwards.

**Bottom line up front:** the single-user costs below are trustworthy and
platform-independent (query counts, payload sizes, peak memory). The
**concurrency numbers are not** — Rig B's Windows bind mount serialises
concurrent file I/O and caps *every* endpoint, including a zero-query
framework-floor page, at ~5 rps. A production-representative concurrency
benchmark is still owed and needs a Linux host with the code on local disk.

### Single-user baselines

| Surface | Rig A (host) p50 | Rig B (fpm, warm) p50 | queries | size / peak |
| --- | --- | --- | --- | --- |
| `/en/about` (floor) | 0.33 s | 0.76 s | 0 | 31 KB |
| `/en/news` | 0.40 s | 0.83 s | 24 | 40 KB |
| `/en/blog` | 0.42 s | 0.87 s | 24 | 46 KB |
| `/en/o/{slug}` | 0.52 s | 0.92 s | **87** | 77 KB |
| `/en/md/territory-1` | 0.72 s | 1.19 s | **182** | 174 KB |
| `/en/catalog` | 1.70 s | 2.45 s | 14 | 696 KB / **136 MB** |
| `/en/map/pins` (country) | 0.40 s | — | — | 35 KB (was 2.1 MB) |
| `/api/v1/status` | 0.31 s | 0.58 s | — | — |

Every page misses its budget: catalog cache-miss `<400 ms` is 6× over at a
single user; `≤30 queries/request` is 87 (object) and 182 (territory).

### Concurrency ramp — Rig B, `ab`, external container (bounded runs)

| Endpoint | c=4 | c=8 | c=16 | c=24 |
| --- | --- | --- | --- | --- |
| `/en/about` (0 queries, floor) | 3.6 rps · p50 0.9 s | 4.7 · 1.5 s | 5.0 · 3.1 s | 4.6 · 4.9 s |
| `/en/news` | 2.7 rps · p50 1.2 s | 3.7 · 2.0 s | 4.6 · 3.3 s | 3.8 · 6.1 s |
| `/en/o/{slug}` | 2.5 rps · p50 1.4 s | 3.4 · 2.3 s | 3.1 · 7.3 s | 3.0 · 7.5 s |
| `/en/catalog` | 0.8 rps · p50 4.0 s | 0.95 · 7.0 s | — | — |

Failed / non-2xx across every **bounded** run up to c=24: **0**. p99 crossed the
2 s budget at c=4 on every endpoint.

### Why these numbers are not the application's ceiling

`/en/about` issues **zero queries**, renders no banner, and touches almost no
Blade — yet it also plateaus at **~5 rps around c=8** and never climbs, while
`booking-app-1` holds only **~1 of 8 vCPU** (streamed `docker stats`: p50 117 %,
max 145 %). A framework-floor page on an idle 8-core VM should do 30+ rps. It does
5.

That means **Rig B's own file I/O serialises concurrent requests** — the Windows
Docker bind mount (gRPC-FUSE / virtiofs) collapses to near-serial under
simultaneous `include`/`stat` load, even with `opcache.validate_timestamps=0`.
The "~5 rps ceiling" and the apparent "knee at c≈12–16" are **artefacts of the
rig**, not the app. An earlier reading of "bottleneck: application CPU" is
**retracted** — during the catalog ramp `booking-app-1` did briefly show ~390 %
(bigger render), but the floor-page result rules CPU out as the general
constraint here.

Postgres stayed clean throughout every run — CPU p50 ~30 % (max ~60 %),
connections ≤ 16 of the 32-worker pool, and **no lock/wait events** sampled in
`pg_stat_activity`. The database is not the near-term constraint; beyond that,
the app's true concurrency ceiling and real bottleneck **cannot be measured on
either local rig** (Rig A is single-process, Rig B is bind-mount-bound). This
needs a Linux host with the code on local disk (a baked image or a non-bind
volume) — the run `[TZ]` §18 asks for.

### One real failure mode did surface

An **unbounded, time-bounded** run (`ab -t 40 -c 16`, i.e. a sustained arrival
rate above the pool's service rate) produced **25,176 fast non-2xx in 40 s** — HTTP
502 returned in ~3 ms. Once php-fpm's `listen.backlog` (default 511) fills, nginx
gets `connect() failed (111: Connection refused)` and fails the request
immediately rather than queuing it. So graceful queuing has a finite depth;
past it the failure mode is a **502 storm, not slow success**. This is
rig-independent behaviour and is a capacity-planning input: put an nginx
`limit_req` / `limit_conn` in front of `/en/catalog` (→ S-27) so a catalog burst
cannot fill the shared backlog and 502 every other page.

### Sustained hold & recovery

`/en/news`, c=8, 60 s, 231 bounded requests: **3.83 rps steady, 0 failed**, p50
1.98 s / p99 2.90 s — no throughput decay, no error onset, PG connections flat at
15. After load stopped: `booking-app-1` returned to 0.01 % CPU / ~106 MiB RSS
(**no leak**); single-user latency was briefly ~2× elevated (cold workers
re-populating opcache + `pm.max_requests=500` recycling) and settled to baseline
within ~60 s. **Recovers cleanly.**

## Checked and cleared

| Area | Result |
| --- | --- |
| **F-02 action journal** | 200 with 30 seeded audit rows, `old_values`/`new_values` render. Blocker fixed (S-02). |
| **F-03 map null pins** | `/map/pins?type=null&q=null` returns the same non-empty payload as no filters. Fixed (S-03). |
| **F-10 amenity filters** | The filter drawer shows amenity groups (General → Wi-Fi, Parking; Catering; …) with no type selected. Fixed (S-10). |
| **F-12 map payload** | Country-wide viewport is 35,318 bytes (was 2,151,356). Fixed (S-12). |
| **F-13 seeder coverage** | `DemoVolumeSeeder` now seeds 84,480 contacts, 40 banners, 60 news, 3,000 reviews, 30 audits, articles, promotions. Fixed (S-13). |
| **F-16 feedback throttle** | `/feedback` is `throttle:5,1`; `/{lang}/country` is `throttle:30,1`. Fixed (S-15). |
| **F-05 SEO-specialist grant** | `seo_specialist` → 403 on `/portal-admin/modules` and `/portal-admin/languages`; `RoleSeeder` comments document the `system.*` split. Fixed (S-05). |
| **S-16 placement grant** | `EditObject` exposes `grant_placement` / `pin_placement` / `unpin_placement` → `PlacementLifecycleService`. A service-level grant wrote `object_placements` + `placement_histories` + an audit row. Built. |
| **S-17 staff admin** | `StaffResource` + `StaffAccountService` (`createAccount`, `updateContacts`, `deactivate`, `restore`, `saveEdit`) + `RoleGrantsRelationManager`. Chief-administrator-only by deliberate `StaffPolicy`. Built. |
| **S-18 banner rendering** | `forSlot()` now called from `TerritoryPageController`, `ObjectPageController`, `CatalogSearch` with territory + category context. Built (but see N-01 — the shared `banner-creative` partial is where the home page breaks). |
| **Placement tiers** | Exactly 4 (`[TZ]` §111). |
| **API authorization** | No token → 401. Bogus / revoked token → 401. Narrow (`objects:read`) token → 200 on `/objects`, **403** on all other resources. Country-scoped token → 404 on an out-of-country object (`/objects/{ua-id}`). Draft object → 404. Non-existent → 404. Per-token rate limit (`rate_limit_per_minute=3`) → `200 200 200 429`. Default `throttle:api-token` = 60/min with `X-RateLimit-*`. Module off → every route 404. |
| **Public authorization** | Guests: 302→login on every panel route, 200 only on `/en` and `/*/login`. Owner / object-staff: 403 on all `/portal-admin/*`. Cross-tenant: `/cabinet/{unowned}/objects` → 404 (not leaked); wrong role → 403. |
| **Locale / path edges** | `/xx`, `/EN`, `/ru-RU/catalog`, `/en%00`, `/en/../etc/passwd`, `/en/catalog/../../etc` all 404 cleanly. `page=abc`, `page=0`, inverted price range, `ratingMin=99` all degrade to an empty result set, never an error. |
| **Country landing** | `/{lang}/{country}` 301s to that country's first top-level territory (deliberate). |
| **Nested / typed territory** | `/en/md/territory-1/territory-11` (real 2-level path) → 200. `/en/md/territory-1/{type-slug}` → 404 — the typed-catalog-within-a-territory route did not resolve for any tried slug; **flag for a follow-up check** (may be a slug-form mismatch, may be the feature). |

## Not covered

Mandatory honest list of what this pass did **not** exercise:

- **Presentation fidelity vs Figma** — MCP quota exhausted (see Presentation
  section). No screen was diffed against the design source.
- **Admin panel as chief_administrator** — the account's mandatory 2FA
  (`[TZ]` §100, an intended control) was not completed in the harness, so
  screens gated to the chief (Staff CRUD, backup **restore**, final deletion,
  irreversible-migration declaration) were verified at route/service level only,
  not click-through. A non-chief 94-permission superadmin was used for the rest.
- **Owner cabinet flows** — availability toggle, review moderation from the
  cabinet, object edit → moderation-request creation: all blocked by N-02.
- **Moderation approve/reject through the UI** — the 2026-08-26 sweep drove this
  end to end and it was clean; not re-driven here.
- **Load at production-representative absolute latency** — the concurrency ramp,
  breaking point, bottleneck and leak-hold were all run (see Load results), on
  the Docker php-fpm rig with opcache tuned for the run. Its per-request latency
  is ≈2× a tuned bare-metal Linux host, so the *rps ceilings* are conservative
  and the *knee concurrency* is indicative, not a launch SLO. A run on the
  actual production instance size is still owed before launch (`[TZ]` §18 asks
  for exactly that).
- **Search p95 `<300 ms`** (`[TZ]` §14 — the Typesense escalation trigger) — the
  Postgres full-text search path was not isolated and load-tested on its own.
- **Destructive scenarios** — bulk delete, object merge, ownership transfer,
  impersonation, mail/SMS sends: authorised but de-prioritised under time.
- **`composer quality` / `pnpm quality`** — not run this pass (2026-08-26
  reported green: Pint clean, PHPStan L8 clean, audits clean).
- **`[TZ]` §26–§60** (territory / pricing detail) — the standing unverified
  slice, carried over.
- **`.env.production` content** (F-11) — not re-checked; gitignored/untracked, low.

## Environment left behind

The seeded database, the running Docker infra, and the host `artisan serve` on
`:8090` are left up for follow-up. Changes made during the pass:

- `.env` `APP_URL` was temporarily pointed at `http://localhost:8090` for
  browser testing and **restored** to `http://booking.test`.
- Filament assets were published to `public/{css,js,fonts}/filament/` (gitignored).
- Sitemap artefacts regenerated under `storage/app/public/sitemaps/`.
- The `api` module was enabled then reset to its default (disabled).
- QA accounts created: `qa+{role}@example.com` (password `qa-Password-1`) for
  every role, plus `qa+superadmin@example.com` (a non-system `qa_superadmin`
  role holding all 94 permissions). `qa+object_owner@example.com` owns objects
  1 and 2.
- Object #17600 carries a QA placement grant (package 1, one-year term) from the
  S-16 functional check — the only catalog-ranking side effect.
