# Fix specifications — 2026-08-31 sweep

> [!IMPORTANT]
> **N-01, N-02 and N-03 are blockers that take the front door, the owner
> cabinet, and an admin dashboard offline in the seeded state.** Plan these
> first. N-01 and N-02 share one root cause — a translated attribute read on a
> model whose `translations` relation was not eager-loaded, turned into a hard
> exception by `Model::shouldBeStrict(! isProduction())` — and want the same
> fix pattern applied at three call sites.
>
> The still-open findings from `qa-fix-specs-2026-08-26.md` (S-01, S-04, S-06,
> S-07, S-08) are **not restated here** — those specs stand unchanged. This
> document covers only what changed or is new.

One specification per material finding in `qa-simulation-2026-08-31.md`. Each
carries the change, the guard that keeps it fixed, and how to verify. Ordered by
the sequence to take them in.

## S-19 · Eager-load `translations` everywhere strict mode now throws

**Fixes:** N-01 (blocker, home page), N-02 (blocker, owner cabinet), N-05 (high,
`/api/v1/articles`). Also contains N-11.

**Problem.** Three independent code paths read a translated attribute
(`Banner::link_text`, `Object_::name`, `ArticleCategory::name`, `Review::author`)
on a model whose relation was never loaded. `AppServiceProvider:97` runs
`Model::shouldBeStrict(! $this->app->isProduction())`, so each is a 500 outside
production and an unbounded N+1 inside it.

**Change — four sites, same shape.**

1. **`HomePageController::show()`** — the `partners` query:

   ```php
   'partners' => Banner::query()
       ->whereHas('slot', fn ($q) => $q->where('key', 'home-partners'))
       ->where('is_active', true)
       ->with(['translations', 'media'])   // media: see N-11
       ->get(),
   ```

2. **`CabinetPanelProvider::resolveTenantUsing()`**:

   ```php
   ->resolveTenantUsing(fn (string $key): Object_ => Object_::query()
       ->withoutGlobalScope(ModerationScope::class)
       ->with('translations')
       ->where('id', $key)          // also fixes N-09 — no bigint cast on a string
       ->firstOrFail())
   ```

3. **Cabinet `ObjectsTable` base query** and **cabinet `ReviewsTable`** — add
   `->with('translations')` / `->with('author')` to `getEloquentQuery()` (or
   `modifyQueryUsing`) so the list columns never lazy-load. If the Filament
   **tenant menu** still throws (it renders `$tenant->name` from vendor chrome),
   add `translations` to a cabinet-scoped `Object_::$with`, or override the
   panel's `tenantMenu()` query.

4. **`ArticleController::index`** — `->with(['translations', 'category.translations'])`.

**Guard.**

- A home-page feature test that seeds a `home-partners` banner **with a
  translation** and asserts `GET /en` → 200. The empty-fixture test is what let
  N-01 ship.
- A cabinet feature test per resource: an owner with ≥1 object opens
  `/cabinet/{id}/{resource}` and gets 200 — asserted with the tenant menu
  actually rendered (full layout, not `isSimple`).
- An API test hitting `/api/v1/articles` with an article + category present.
- An architecture test (content scan) — a Blade partial that reads a
  `$translatedAttributes` accessor must be paired with a controller/table query
  that eager-loads `translations`. If that is not expressible, at minimum a
  smoke test that walks every public route and every panel resource index under
  `Model::shouldBeStrict(true)` and asserts no `LazyLoadingViolationException`.

**Verify.** `/en`, `/ru`, every `/cabinet/{id}/*`, and `/api/v1/articles` return
200 against the seeded volume with strict mode on.

## S-20 · Aggregate the SEO Health report in SQL

**Fixes:** N-03 (blocker).

**Problem.** `SeoHealthReport::rows()` is
`DB::table(translations)->join(base)->get()` with no bound, called four times per
entity type. For objects that is 105,600 rows per call, appended one-per-offender
to an array the page renders whole — 66 MB, 10.5 s.

**Change.** Replace the row enumeration with grouped counts computed in the
database:

```php
DB::table("{$spec['translations']} as tr")
    ->join("{$spec['table']} as base", 'base.id', '=', "tr.{$spec['fk']}")
    ->selectRaw("tr.locale,
        count(*) filter (where tr.seo_title is null or tr.seo_title = '')  as missing_title,
        count(*) filter (where tr.seo_indexable is false)                  as non_indexable,
        count(*) filter (where tr.seo_canonical_url is null)               as missing_canonical")
    ->groupBy('tr.locale')
    ->get();
```

Render the counts with a per-entity drill-down link to a **paginated** offender
list (a normal Filament table with `->paginate()`), not an inline dump.

**Guard.** A feature test asserting `/portal-admin/seo-health-dashboard`
responds under a fixed size ceiling (256 KB is generous) and inside 1 s with the
volume seeder applied.

**Verify.** The dashboard renders in well under a second at 52,800 objects; the
offender lists paginate.

## S-21 · Publish Filament assets as part of setup

**Fixes:** N-04 (high).

**Problem.** `public/{css,js,fonts}/filament/` are gitignored (correctly), and
nothing regenerates them. A clean `git clone` + `composer install` + `migrate`
yields two panels with no CSS/JS.

**Change.**

1. Add to `composer.json`:

   ```json
   "post-autoload-dump": [
       "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
       "@php artisan package:discover --ansi",
       "@php artisan filament:assets --ansi"
   ],
   ```

   (Filament auto-registers `filament:upgrade` in `post-autoload-dump` by
   default; this project's override dropped it — restoring an explicit
   `filament:assets` is the minimal fix.)

2. Add `@php artisan filament:assets` to the `setup` script and the Docker build
   stage that already runs `npm run build`.

3. Name it in `README.md`'s "zero to running" section.

**Guard.** A smoke test: `GET /css/filament/filament/app.css` → 200, and the
panel login page emits **zero** console `ReferenceError`s. (The second half
needs a browser test — the login page's Alpine expressions
`filamentSchema(...)` throw when the JS bundle is absent.)

**Verify.** A fresh checkout renders a styled, interactive panel with no manual
`artisan` step.

## S-22 · Delete the static `public/robots.txt`

**Fixes:** N-06 (moderate).

**Problem.** `public/robots.txt` (the Laravel default stub) is served as a
static file, shadowing `RobotsController`. The controller's panel `Disallow`
lines, `Sitemap:` reference, and `seo.robots_extra` setting are all dead.

**Change.** `rm public/robots.txt`. It is not needed — the route
`Route::get('/robots.txt', RobotsController::class)` already covers it.

**Guard.** A feature test:

```php
$response = $this->get('/robots.txt');
$response->assertOk()
    ->assertSee('Disallow: /'.config('booking.panels.admin.path'), false)
    ->assertSee('Disallow: /'.config('booking.panels.cabinet.path'), false)
    ->assertSee('Sitemap: ', false);
```

**Verify.** `/robots.txt` returns the dynamic body, and changing
`seo.robots_extra` in portal settings is reflected on the next request.

## S-23 · Re-audit the unbounded Filament `Select` sites

**Fixes:** N-07 (moderate). Extends S-01, which is **partially done** —
`news-items/create` is fixed (252 KB); `objects/create` is not (2.3 MB) and
`objects/{id}/edit` is 1 MB.

**Change.** Re-run S-01's audit against the **current** form schemas. Convert
every `->options(fn () => Model::query()…->get()…)` over an unbounded table on
`ObjectForm`, `EditObject`'s action schemas, and any relation manager to the
`->getSearchResultsUsing()` / `->getOptionLabelUsing()` pair already used at
`FinancialRecordForm.php:45-51`. `PlacementPackage` (4 rows), object types,
amenities, languages, countries stay as-is — the rule is table growth.

**Guard.** Promote S-01's proposed architecture test to actually failing: scan
every class under `app/Filament/**/Schemas/` and `app/Filament/**/Tables/` for
`->options(` whose closure body contains `->get()` or `->pluck()` on a model
whose table is not in a small allow-list. Pair with a feature test asserting
`objects/create` responds under 256 KB and 128 MB.

**Verify.** `objects/create` and `objects/{id}/edit` respond in tens of
kilobytes.

## S-24 · Move the backup-destination probe out of the web request

**Fixes:** N-08 (moderate) — the 7.4 s `/portal-admin/backup-administration`.

**Problem.** The page performs a synchronous reachability/size/integrity check
against the backup destination disk on every render.

**Change.** Have the existing `backup:monitor` scheduled command (already runs
`dailyAt('03:00')`) write its result to a `settings` row or a cache key, and
have the page read that snapshot with a "last checked N minutes ago" line and a
manual "re-check now" action that dispatches the check as a job.

**Guard.** A feature test asserting the page issues no filesystem call to the
backups disk during render (fake the disk, assert no `exists`/`size` calls).

**Verify.** The page renders in the same sub-second range as its siblings.

Also under N-08: `interface-catalog-editor` returns 11 MB — apply the same
treatment as S-23 (it is almost certainly inlining the full interface-string
catalog); audit its schema and paginate or lazy-load.

## S-25 · Guard the sitemap artefact's freshness

**Fixes:** N-12 (low).

**Problem.** `SitemapController` serves the last-written artefact with no
freshness bound; a fresh deploy 404s until the hourly job first runs, and a
stale artefact (here: an empty index from pre-seed runs) is served indefinitely.

**Change.**

1. Dispatch `GenerateSitemapsJob` at the end of the deploy pipeline (after
   `migrate`, before the health check).
2. In `SitemapController::serve()`, if the artefact is missing **or** older than
   `config('sitemap.max_age_hours', 6)`, dispatch `GenerateSitemapsJob` and
   return `503` with a short `Retry-After` rather than serving stale/absent XML.

**Guard.** A feature test: with no artefact on disk, `/sitemap.xml` → 503 and a
`GenerateSitemapsJob` is queued; with a fresh artefact, → 200 with `<sitemap>`
children.

## S-26 · Minor hardening

**Fixes:** N-09 (cast guard — folded into S-19 site 2), N-10, N-11, N-13.

- **N-10** — `feedback-overlay.blade.php`: replace `$errors` with
  `$errors ?? new \Illuminate\Support\MessageBag`, or assert the component is
  only ever `@include`d inside a view rendered through the `web` group. Add a
  test that renders the overlay in isolation.
- **N-11** — `banner-creative.blade.php`: wrap the `<img>` in
  `@if ($banner->getFirstMediaUrl('desktop_creative'))` so a creative-less
  banner renders nothing, not a broken image. (`BannerSelectionService` already
  contracts "render no element at all, never an empty frame".)
- **N-13** — the catalog footer "Popular destinations" query returns nothing for
  the resolved country; either fix the query's country binding or hide the block
  when empty (the same rule N-11 applies to banners).

## S-27 · Run the concurrency benchmark on real hardware, and cap the expensive route

**Fixes:** the Phase 8 coverage gap plus one rig-independent failure mode (see
`qa-simulation-2026-08-31.md` → Load results).

**Problem — the benchmark is still owed.** Neither local rig can measure the
app's concurrency ceiling: host `artisan serve` is single-process, and the Docker
rig's Windows bind mount serialises concurrent file I/O — a zero-query
framework-floor page plateaus at ~5 rps at c ≈ 8 while the container uses ~1 of 8
vCPU. So the app's real bottleneck (CPU vs a lock vs Redis vs something else) and
its throughput knee are **unknown**. `[TZ]` §18 requires a load test against
catalog and territory pages before launch; the earlier `composer bench` covers
the *query* cost, not concurrency.

**Problem — one real failure mode did surface.** A sustained arrival rate above
the pool's service rate overflows php-fpm's `listen.backlog` (default 511):
nginx then gets `connect() failed (111: Connection refused)` and returns **HTTP
502 in ~3 ms** rather than queuing. Observed: `ab -t 40 -c 16` → 25,176 fast
502s. Graceful queuing has a finite depth; past it the portal fast-fails, and a
burst on the single most expensive route (`/en/catalog`, 696 KB / 136 MB peak /
S-06) can fill the shared backlog and 502 every other page.

**Change.**

1. **Add an nginx `limit_req` + `limit_conn` on `/en/catalog`** (and, once it
   exists, the catalog Livewire update endpoint), sized so a catalog burst
   cannot consume the whole `listen.backlog`. Return `429` with a short
   `Retry-After`, not a 502. This is worth doing now, ahead of S-06, because it
   bounds the blast radius of the known-heavy page.
2. **Raise `listen.backlog` explicitly** in `docker/app/www.conf` (e.g. `1024`)
   and set `pm.process_idle_timeout`, so a transient spike queues instead of
   fast-failing — a deliberate value, not the vendor default.
3. **Run the real benchmark** on the provisioned production instance size: `k6`
   or `wrk`, the ladder c = 4/8/16/32/64, against `/en/catalog`,
   `/en/md/territory-1`, `/en/o/{slug}`, `/api/v1/objects`. Record the actual
   knee, the first resource to saturate (`docker stats` / `pg_stat_activity`
   wait events / Redis `INFO`), p99 vs the §18 budgets, and hold at 80 % of the
   knee for 10 min for a leak check. **Re-size `pm.max_children` from that
   measurement**, not from the current memory-only formula — the formula stays
   the upper clamp.
4. The capacity lever remains per-request cost: land S-06 / S-07 / S-08 and
   re-run step 3.

**Guard.** Extend `composer bench` with a concurrency pass that asserts (a) zero
non-2xx under a bounded c = 32 run and (b) p95 within the §18 budget on the
provisioned instance. Pre-launch checklist, not the per-commit gate.

**Verify.** On production-representative hardware: the measured knee is at or
above the launch concurrency target, a 2× peak burst on `/en/catalog` yields
`429`s (never 502s on unrelated routes), and the 10-minute hold shows no memory
creep.

## Not defects — record and confirm with the client

- **Staff/role administration is chief-administrator-only** (`StaffPolicy`
  hard-codes `hasRole('chief_administrator')`, with a docblock noting scoped
  delegation is "left to a future specification amendment"). `[TZ]` §121 reads
  as though a country administrator manages users. Confirm whether delegated
  staff management is in launch scope, or whether chief-only is the accepted
  launch posture.
- **The typed-catalog-within-a-territory route** (`/{lang}/{country}/{settlement}/{type}`,
  `[TZ]` §5.1) returned 404 for every object-type slug tried
  (`hotels`, `apartments`). Confirm the expected slug form (translation slug vs
  `object_types.slug` vs numeric) and add a route test, or confirm the feature's
  status.
- **`.env.production`** (F-11 from 2026-08-26) was not re-checked; if it still
  carries `APP_ENV=local` / `APP_DEBUG=true`, S-11 still applies.
