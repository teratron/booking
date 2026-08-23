# Deep QA Sweep — findings and fix specifications

A second, deeper pass over the whole portal following `qa-deep-plan.md`. Where
the 2026-08-22 sweep exercised ~110 of 181 routes and stopped at the MFA gate,
this pass drove every back-office route past that gate for **all nine staff
roles**, exercised the API token lifecycle end to end, and added a real browser
pass for the JS-dependent surfaces.

**Environment:** PHP 8.5.9 · Laravel 13.26.1 · Filament 5 · Postgres 18/PostGIS
(Docker, host port 5433) · Redis 8 · MinIO · Chromium via Playwright.
**Baseline:** `composer test` (non-slow group) — 977 tests, 970 passed, 3
skipped, **4 failed** (see F-23; all four are Windows-only test defects).

**Live version:** [Booking Portal Deep Sweep artifact](https://claude.ai/code/artifact/62064227-e644-4222-b986-2359ac95d189)
(interactive: severity filter, role-by-route matrix; this file carries the full write-ups).

**Reproduced:** every finding below was observed against a live instance and
traced to a named file and line. Two early suspicions were *disproved* on
re-testing and are recorded as such in "Cleared" at the end, so they are not
carried forward as bugs.

## Severity summary

| # | Severity | Finding |
| --- | --- | --- |
| F-01 | **Blocker** | A freshly seeded chief administrator cannot use the back office |
| F-02 | **Blocker** | Language switcher and `hreflang` alternates 404 on every object and territory page |
| F-03 | **Blocker** | Non-numeric news/blog/promotion URL segments return HTTP 500 with a raw SQL error |
| F-04 | High | Object edit form (admin **and** cabinet) 500s whenever the object has contacts |
| F-05 | High | Placement package create/edit 500s |
| F-06 | High | `api/v1/countries` and `api/v1/territories` 500 (N+1 in production) |
| F-07 | High | Banner impression/click counters are never written — back-office stats always 0 |
| F-08 | High | Every cache invalidation on the write path is a no-op |
| F-09 | High | The seeded role catalogue does not grant the roles their own duties |
| F-10 | High | Every footer category link lands on the unfiltered catalog |
| F-11 | High | The reviews module has no way to create a review |
| F-12 | Moderate | A disabled API module answers 401, not 404 |
| F-13 | Moderate | An elapsed promotion stays public until the next daily sweep |
| F-14 | Moderate | Backup administration 500s when the backup bucket is unreachable |
| F-15 | Moderate | `promotion_labels.position_on_card` has no database constraint |
| F-16 | Moderate | Map tile credentials in `.env` are read by nothing; maps fail silently |
| F-17 | Moderate | The object page has no map |
| F-18 | Moderate | Footer "About" and "Contacts" are inert text — the pages do not exist |
| F-19 | Moderate | "Photo views" counts page views, not photo views |
| F-20 | Low | Open Graph image has no fallback; no `og:type`, `og:url`, `twitter:card` |
| F-21 | Low | Banner mobile creatives are uploadable but never served |
| F-22 | Low | No cookie-consent notice is rendered anywhere |
| F-23 | Low | The project's own quality gate cannot pass on Windows |
| F-24 | Low | Five tables carry no model, service, or UI |

## Blockers

### F-01 · A freshly seeded chief administrator cannot use the back office

**Where:** `database/seeders/DatabaseSeeder.php:36-41` · `app/Services/Authorization/ScopeAuthorizer.php:82-115` · `app/Services/Authorization/ResourceQueryScoper.php:88-95`

**Repro:** `php artisan migrate:fresh --seed`, sign in as the seeded
`test@example.com` (role `chief_administrator`), complete MFA, then open the
back office.

**Observed:** 30 of 78 back-office screens answer **403**, and *every* `/edit`
screen answers **404** — including `objects/{id}/edit` for an object that
plainly exists. Affected: API clients, articles, article categories, article
tags, banners, banner slots, catalog filter promotions, error pages, financial
records, languages, modules, object types, placement packages, placement tiers,
promotion labels, redirects, SEO metadata templates.

**Root cause:** authorization has two halves — the permission (`user.view`) and
the *scope grant* (`role_scopes`). `ScopeAuthorizer::constraintFor()` treats an
actor with no `role_scopes` row as reaching **no axis**, and
`ResourceQueryScoper::applyConstraint()` then correctly fails closed with
`where 1 = 0`. Only a row with `scope_kind = 'none'` produces an unrestricted
grant. `DatabaseSeeder` assigns the role but never writes that row, and the dev
database confirms it: `select count(*) from role_scopes` → **0**.

Failing closed is the right design. The defect is that nothing ever opens it.

**Impact:** the delivered portal has no working administrator. And because
there is no Users/Roles resource in the panel at all (see F-09 and the TZ
conformance note on §121), the client cannot repair this from the interface —
it needs a manual `INSERT` into `role_scopes`.

**Fix:**

1. In `DatabaseSeeder`, after `assignRole('chief_administrator')`, write the
   unrestricted grant:
   ```php
   DB::table('role_scopes')->insert([
       'user_id' => $user->id,
       'role_id' => Role::where('name', 'chief_administrator')->value('id'),
       'scope_kind' => 'none',
       'scope_reference_id' => null,
       'granted_by' => $user->id,
       'granted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
   ]);
   ```
2. Make the grant a consequence of assigning a role, not a separate step a
   caller can forget: have `RoleGrantService` write a default `none` scope
   whenever a role is granted without an explicit narrowing, so every future
   staff account created through any path is usable.
3. Regression test: seed from empty, sign in as the seeded administrator, and
   assert a 200 on one resource from each navigation group.

### F-02 · Language switcher and `hreflang` alternates 404 on every object and territory page

**Where:** `app/Services/Shell/LocaleSwitchResolver.php:25-42` · `app/Services/Seo/MetadataResolver.php:155-162` · `resources/views/components/public/language-switcher.blade.php:29`

**Repro (browser):** open `/en/o/hotel-nistru`, click the language switcher,
pick **RU** → `Страница не найдена`, HTTP 404. The object's Russian slug is
`otel-nistru`; the switcher navigated to `/ru/o/hotel-nistru`.

**Repro (harness):** `tests/.../LocaleAlternateProbeTest` output —

```
object page:    /en/o/md-vip
  hreflang=ru emitted: /ru/o/md-vip          → 404
  correct ru url:      /ru/o/md-vip-ru       → 200

territory page: /en/md/central-region/chisinau/old-town
  hreflang=ru emitted: /ru/md/central-region/chisinau/old-town        → 404
  correct ru url:      /ru/md/central-region-ru/chisinau-ru/old-town-ru → 200
```

**Root cause:** `LocaleSwitchResolver::targetUrl()` rebuilds the current route
with only the `lang` parameter replaced, keeping every other parameter — which
for these two routes is the *current locale's* slug. Its own docblock says so:

> Per-entity slug translation … is a concern of whatever later builds
> per-language slugs onto object and territory routes; it does not exist yet.

That work has since landed — translations carry their own `slug` /
`full_slug_path`, and `PublicUrlGenerator` resolves them correctly — but this
resolver was never revisited. `MetadataResolver::alternates()` delegates to the
same method, so the `hreflang` links shipped sitewide carry the same wrong URL.

**Impact:** the portal is bilingual and its language switch is broken on its
two most important page types. Worse for SEO: every object and territory page
tells search engines that its Russian alternate is a 404, which is how a whole
language version gets dropped from an index.

**Fix:** route the alternate through `PublicUrlGenerator`, which already knows
how to resolve a slug per locale.

1. Give `LocaleSwitchResolver` the entity currently being rendered (the page
   controllers already hold it) and, for `public.objects.show` and
   `public.territories.show`, return `PublicUrlGenerator::objectUrl($object, $lang)`
   / `territoryUrl($territory, $lang)`.
2. When the target locale has no translation, fall back to that locale's home
   rather than emitting a URL that 404s, and omit the `hreflang` alternate
   entirely for that locale — an absent alternate is correct, a broken one is not.
3. Regression test: for an entity whose slugs differ per locale, assert the
   emitted `hreflang` URL and the switcher URL both return 200.

### F-03 · Non-numeric news/blog/promotion URL segments return HTTP 500

**Where:** `routes/web.php` — `{lang}/news/{newsItem}`, `{lang}/blog/{article}`, `{lang}/promotions/{promotion}`

**Repro:**

| URL | Status |
| --- | --- |
| `/en/news/1` | 200 |
| `/en/news/renovated-spa-wing-opens` | **500** |
| `/en/news/not-a-real-thing` | **500** |
| `/en/news/999999` | 404 |
| `/en/blog/{slug}` · `/en/promotions/{slug}` | **500** (identical) |

**Error:** `SQLSTATE[22P02]: invalid input syntax for type bigint:
"renovated-spa-wing-opens"` — raw, and with `APP_DEBUG=true` the response
carries the full stack trace.

**Root cause:** these three routes use default route-model binding on the
primary key. Any segment that is not an integer reaches Postgres as a `bigint`
comparison and throws before Laravel can turn a missing model into a 404. Every
link the portal emits uses the numeric id (`/en/news/3`), so the slug columns
that exist and are populated on `news_translations`, `article_translations`, and
`promotion_translations` are never used in a URL.

**Impact:** two defects in one. A crash reachable by typing a plausible URL —
and one that leaks the database error — plus the loss of the SEO URL model that
objects and territories already implement.

**Fix:**

1. Bind these three routes by translated slug, the way `{lang}/o/{slug}`
   already does, resolving through the same `PublicSlugResolver`.
2. Keep numeric ids working as a permanent 301 to the slug URL, so existing
   links and any indexed URL survive.
3. Independently of the above, constrain the route parameters
   (`->where('newsItem', '[A-Za-z0-9\-]+')`) so no non-matching segment can
   ever reach a bare integer comparison — a crash of this shape should not be
   reachable even if the binding changes again later.
4. Regression test: a garbage segment on each of the three routes returns 404,
   never 500.

## High

### F-04 · Object edit form 500s whenever the object has contacts

**Where:** `app/Filament/Cabinet/Resources/Objects/Schemas/ObjectForm.php:263-271` · the same repeater in `app/Filament/Admin/Resources/Objects/Schemas/ObjectForm.php`

**Repro:** as the owner, open `/cabinet/{object}/objects/{object}/edit` for an
object with at least one contact channel → 500. Identical as staff at
`/portal-admin/objects/{id}/edit`.

**Error:** `Attempted to lazy load [translations] on model
[App\Models\ContactChannelType] but lazy loading is disabled.`

**Root cause:** the type selector added in the previous sweep's F-1 remediation
labels its options with `getOptionLabelFromRecordUsing(fn (ContactChannelType $type) => $type->display_name …)`.
`display_name` is a translated accessor, and the relationship query
(`modifyQueryUsing`) filters on `is_active` without eager-loading `translations`.

**Impact:** the owner cabinet's central screen and the staff object editor both
crash for any object that has a phone number — i.e. every real object. Outside
`local`/`testing` the strict-mode guard is off (`Model::shouldBeStrict(! isProduction())`),
so production does not crash — it silently issues one query per option instead.

**Fix:** eager-load in the relationship query, in both forms:
```php
modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true)->with('translations'),
```
Regression test: render both edit pages for an object carrying one channel of
each seeded type and assert 200.

### F-05 · Placement package create/edit 500s

**Where:** `app/Filament/Admin/Resources/PlacementPackages/Schemas/PlacementPackageForm.php`

**Repro:** as chief administrator, open `/portal-admin/placement-packages/create`
or `/portal-admin/placement-packages/{id}/edit` → 500.

**Error:** `Attempted to lazy load [translations] on model [App\Models\PlacementTier]`.

**Root cause and fix:** identical shape to F-04 — the tier `Select` labels its
options with a translated accessor and never eager-loads `translations`.

**Impact:** placement packages are the portal's revenue model (TZ §111, §60);
neither creating nor editing one is currently possible in a strict environment.

### F-06 · `api/v1/countries` and `api/v1/territories` return 500

**Where:** `app/Http/Controllers/Api/V1/CountryController.php:20-27` · `app/Http/Controllers/Api/V1/TerritoryController.php:32-52`

**Repro:** enable the `api` module, issue a full-ability token:

```
countries            → 500  Attempted to lazy load [translations] on model [App\Models\Country]
territories          → 500  Attempted to lazy load [level] on model [App\Models\Territory]
object-types         → 200
amenities            → 200
news / promotions / articles / objects → 200
```

**Root cause:** neither controller eager-loads the relations its API resource
reads. Two of twelve documented endpoints are broken.

**Impact:** in a strict environment, a hard 500 on two published endpoints; in
production, a silent N+1 (one query per row) that puts a list endpoint far past
the 30-query budget.

**Fix:** `Country::query()->with('translations')` and
`Territory::query()->with(['translations', 'level.translations', 'country'])`.
Add a contract test that walks **every** endpoint in `routes/api_v1.php` with a
full-ability token and asserts 200 — the existing suite covers only a subset,
which is why these two survived.

### F-07 · Banner impression and click counters are never written

**Where:** `app/Http/Controllers/BannerClickController.php:29-38` · `app/Filament/Admin/Resources/Banners/Tables/BannersTable.php:52-70`

**Repro:** open a banner click-through and re-read the row:
`banners.clicks` `0 → 0`, while the redirect works correctly.

**Root cause:** the click path records a `banner_click` **stat event**
(`EventCaptureService`), and nothing ever increments the `impressions` /
`clicks` columns on `banners`. The back-office table reads exactly those
columns, and derives the click-through rate from them:

```php
TextColumn::make('impressions'), TextColumn::make('clicks'),
TextColumn::make('click_through_rate')
    ->getStateUsing(fn (Banner $record) => $record->impressions > 0
        ? number_format($record->clicks / $record->impressions * 100, 2).'%' : …)
```

**Impact:** every banner in the back office permanently reports 0 impressions,
0 clicks, 0.00 % CTR — the numbers an advertiser is invoiced against (TZ §115,
§24.2). `TransferableRegistry` exports the same zeros.

**Fix:** pick one source of truth and delete the other. Recommended: keep the
analytics events (they carry territory, locale, and date, which the columns do
not) and have the table read from `PortalReportingService`, which already
computes impressions, clicks, and CTR from `stat_dailies`. Then drop the
`impressions`/`clicks` columns in a new migration, and drop them from the
exporter. Regression test: a click and an impression each show up in the
back-office figure for that banner.

### F-08 · Every cache invalidation on the write path is a no-op

**Where:** writers — `app/Services/Cabinet/AvailabilityToggleService.php:93` ·
`app/Services/Objects/AvailabilityAdministrationService.php:135` ·
`app/Services/Placement/BumpService.php:94` ·
`app/Services/Placement/PlacementLifecycleService.php:209` ·
`app/Services/Content/ContentPublicationService.php:51`.
Readers — `app/Http/Controllers/Public/ObjectPageController.php:75` ·
`app/Services/Catalog/CatalogQueryService.php:124` ·
`app/Http/Controllers/Public/TerritoryPageController.php:82`.

**Repro:** render an object page (badge "Vacancies available" present), toggle
availability off through `AvailabilityToggleService`, render the page again →
**the badge is still there**.

**Root cause:** the five write paths all invalidate by tag —

```php
Cache::tags(['catalog', "territory:{$object->territory_id}", "object:{$object->id}"])->flush();
```

— while the three public caches are written **untagged**:

```php
Cache::remember(sprintf('object:profile:%d:%s', $object->id, $lang), 300, …)
Cache::remember($this->cacheKey($criteria, $constraint), 300, …)   // 'catalog:search:'.sha256(…)
Cache::remember(sprintf('territory:sidebar:%d:%s', $territory->id, $lang), 300, …)
```

A tagged flush only removes entries stored through those tags. None of these
keys is, so none of the five invalidations reaches the page it targets.

**Impact:** for up to the 300-second TTL — an owner who marks "no vacancies"
still shows as available (directly against TZ §27.1 and §27.3, which require
the badge to disappear from the object page immediately); a bump does not
change catalog order; a tier upgrade or expiry leaves a stale badge and border;
an approved moderation decision does not reach the territory sidebar. The code
believes it is invalidating, so nothing about this is visible from reading
either side alone.

**Fix:** store the three caches through the same tags the writers flush:

```php
Cache::tags(["object:{$object->id}", "territory:{$object->territory_id}"])
    ->remember("object:profile:{$object->id}:{$lang}", self::PROFILE_CACHE_TTL_SECONDS, …);
Cache::tags(['catalog'])->remember($this->cacheKey(...), …);
Cache::tags(["territory:{$territory->id}"])->remember(...);
```

Add an architecture test asserting that no `Cache::remember` call in
`app/Http/Controllers/Public` or `app/Services/Catalog` is untagged — the rule
a machine can check, so the next reader cannot re-introduce the mismatch. Add a
feature test per write path: mutate, re-request, assert the page changed.

### F-09 · The seeded role catalogue does not grant the roles their own duties

**Where:** `database/seeders/RoleSeeder.php:33-96`

**Observed** (`role_has_permissions`, full matrix in `qa-admin-matrix.log`):

| Role | Holds | Cannot reach |
| --- | --- | --- |
| `advertising_manager` | `content.*`, `object.view` | banners, banner slots, promotion labels — **all 403** |
| `technical_support` | `settings.*`, `audit.view`, `impersonate` | owners — 403, so `impersonate` has no entry point |
| `country_administrator` | `user_management` but not `user.view` | owners — 403 |
| `moderator` | `moderation.*`, `object.*` | news, promotions — 403 |

Permission families held by **no role except the chief administrator**:
`advertising.*`, `analytics.*`, `notification.*`, `commerce.*`, `user.*`,
`api.*`, `personal_data_access`, `audit.export`, `backup_restore`.

**Impact:** five of the nine staff roles TZ §121 enumerates are decorative. The
advertising manager cannot touch advertising; technical support holds
`impersonate` but has no screen from which to use it, while holding
portal-settings, languages, and modules — an inverted, over-broad grant; nobody
but the chief can send a notification (§124), manage a placement package
(§111), or see the analytics report (§125).

**Fix:**

1. Correct the map: `advertising_manager` → `advertising.*` + `content.view` +
   `object.view`; `technical_support` → `user.view` + `impersonate` +
   `audit.view` only (drop `settings_management`); `country_administrator` →
   add `user.view/create/edit`; give `finance_manager` `commerce.view`;
   give `content_manager` and `advertising_manager` `analytics.view`.
2. Add a test that asserts, for each role, that it can open the resources its
   name implies and is refused the rest — the matrix as an executable table.
3. Ship the Users & Roles back-office section (TZ §121) so a client can correct
   a grant without a developer. Note F-01: the same gap makes a mis-seeded
   scope unrepairable.

### F-10 · Every footer category link lands on the unfiltered catalog

**Where:** `resources/views/components/public/footer.blade.php:86` · `app/Livewire/Public/CatalogSearch.php:43`

**Repro (browser):** click any category in the footer, e.g. **Hotel** →
`/en/catalog?type=hotel` → the query string is dropped and the unfiltered
catalog renders. `/en/catalog?type=1` filters correctly (page title becomes
"Hotel").

**Root cause:** the footer passes the object type's **key**
(`['type' => $group->key]`) while the Livewire component declares
`public ?int $type = null`. A non-numeric value cannot bind, so the filter is
silently discarded. The home page's own category tiles pass the numeric id —
two different grammars for the same parameter, one of them wrong.

**Impact:** eight dead links in the footer of every page on the portal.

**Fix:** the cheap fix is `['type' => $group->id]` in the footer. The better
fix, and the one that matches the URL model everywhere else, is to accept the
type **slug** in `CatalogSearch` (`public ?string $type`), resolve it to an
`ObjectType`, and keep numeric ids accepted for compatibility — a slug in the
query string is what the SEO model asks for. Regression test: each footer
category link returns a catalog narrowed to that type.

### F-11 · The reviews module has no way to create a review

**Where:** no writer anywhere — `app/Services/Cabinet/ReviewInteractionService.php` exposes only `reply()` and `report()`; there is no admin resource for reviews and no public submission form.

**Observed:** reviews are read on the object page, in the API
(`/objects/{id}/reviews`), on the home page, and in the cabinet; an owner may
reply and report. Nothing in the codebase creates a `Review` row. The table is
designed for visitor submission (`author_id`, `author_name`, `status` with
`pending/published/rejected`, `reported_at`, `hidden_reason`) and a `reviews`
feature module exists.

**Impact:** the rating on every card and every object page is permanently
empty, the rating filter in the catalog matches nothing, the cabinet's Reviews
screen is always empty, and TZ §39/§87/§120 are unimplemented end to end.

**Fix, as a specification:**

1. **Public submission** — a form on the object page (name, rating 1–5, text),
   rate-limited per IP, CAPTCHA-gated when a provider is configured (the
   settings keys already exist — see F-16), writing `status = 'pending'`.
2. **Moderation** — a `ReviewResource` in the staff panel: list with filters by
   object, country, status, and reported flag; publish / reject with a reason /
   hide with a reason; view the owner reply; block an author. TZ §120 forbids
   editing the visitor's text, so the form exposes status and service fields only.
3. **Owner side** — already built (`reply`, `report`); wire the reply into the
   moderation queue if the portal is in review mode.
4. **Aggregates** — `CatalogQueryService` already computes averages from
   `reviews`; nothing further needed once rows exist.

## Moderate

### F-12 · A disabled API module answers 401, not 404

**Where:** `bootstrap/app.php:35-43`

**Repro:** with the `api` module in its shipped-disabled state —

| Request | Status |
| --- | --- |
| no token, `/api/v1/objects` | **401** |
| valid token, `/api/v1/objects` | 404 |
| no token, `/api/v1/status` (unauthenticated route) | 404 |

**Root cause:** `bootstrap/app.php` deliberately tries to run the module gate
first —

```php
$middleware->prependToPriorityList(before: Authenticate::class, prepend: EnsureModuleEnabled::class);
```

— but `Illuminate\Auth\Middleware\Authenticate` is **not** in Laravel's
priority list; the list carries the contract
`Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests` instead. With no
anchor to insert before, `EnsureModuleEnabled` is appended to the **end** of
the priority list. The effective order for `api.v1.objects.index` is:

```
Authenticate:sanctum → ThrottleRequests:api-token → SubstituteBindings
  → EnsureModuleEnabled:api → ResolveApiLocale → RecordApiConsumption → CheckAbilities:objects
```

**Impact:** a disabled module is not "indistinguishable from an unregistered
path" as the route file documents — an anonymous probe learns the endpoint
family exists. Route-model binding and the token rate limiter also both run
ahead of a gate meant to make the whole surface inert.

**Fix:** anchor on the contract, which is what the list actually contains:

```php
$middleware->prependToPriorityList(
    before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
    prepend: EnsureModuleEnabled::class,
);
```

Regression test: with the module off, an unauthenticated request to a
token-protected endpoint returns **404** — the test that would have caught this
and does not exist today.

### F-13 · An elapsed promotion stays public until the next daily sweep

**Where:** `app/Http/Controllers/Public/PromotionController.php:73-79`

**Repro:** a promotion whose `ends_at` is in the past and whose `status` is
still `published` renders 200. After `PromotionArchivalJob` runs it correctly
404s and the stored status becomes `archived`.

**Root cause:** `isPubliclyVisible()` checks `status` and `starts_at` but
deliberately not `ends_at`, delegating expiry to the daily job — its docblock
says so explicitly.

**Impact:** up to 24 hours of a finished offer being served as current. TZ §38
and §85 both say the transition is automatic on the end date.

**Fix:** add `&& $promotion->ends_at->gte(now())` to the visibility check. The
job keeps owning the durable status transition (the archive listing needs it);
the controller stops depending on the job's schedule for correctness. This is
defence in depth, not a replacement.

### F-14 · Backup administration 500s when the backup bucket is unreachable

**Where:** `app/Filament/Admin/Pages/BackupAdministration.php`

**Error:** `Unable to list contents for 'media', shallow listing … AWS HTTP
error: NoSuchBucket` — the page is the only back-office screen that 500s for a
correctly provisioned chief administrator.

**Impact:** the one screen whose job is to *report* backup health is the one
that cannot report a problem — a missing bucket, expired credentials, or an
unreachable provider all surface as a stack trace. TZ §131 requires the panel
to show backup status and let the administrator see the log and act.

**Fix:** wrap the destination listing, catch `FilesystemException` /
`UnableToListContents`, and render the failure as the page's own status —
"backup destination unreachable", the provider name, and the error — with the
manual-backup action disabled. Separately, add bucket creation to the local
setup path (a MinIO `mc mb` step in the compose bootstrap or the README) so a
fresh clone does not start in this state.

### F-15 · `promotion_labels.position_on_card` has no database constraint

**Where:** migration for `promotion_labels` · `app/Support/Advertising/CardPosition.php`

**Repro:** write `position_on_card = 'top_left'` (underscore instead of the
enum's hyphen) → `/portal-admin/promotion-labels` and the record's edit page
both throw `ValueError: "top_left" is not a valid backing value for enum
App\Support\Advertising\CardPosition` — HTTP 500.

**Root cause:** `CardPosition`'s docblock states the value is closed "so an
administrator-authored label can never carry an arbitrary position value", but
the column is an unconstrained `varchar`. Every other enum-backed column in
this schema (`objects.status`, `reviews.status`, `financial_records.status`, …)
carries a `CHECK`; this one does not.

**Impact:** one out-of-range value — from an import, a data migration, or a
hand-edit — takes down the whole Promotion Labels list, and the record cannot
be opened to correct it. Recovery requires SQL.

**Fix:** add the `CHECK` constraint in a new migration, matching the four enum
cases, and cast the column defensively (`tryFrom` with a null-safe fallback) so
one bad row degrades to a missing badge rather than a broken page.

### F-16 · Map tile credentials in `.env` are read by nothing

**Where:** `.env:57-58` · `.env.example:92-93` · `app/Services/Integrations/MapTileConfigResolver.php:29-36` · `app/Services/Settings/SettingsRegistry.php:136-137`

**Repro (browser):** every page carrying a map logs
`Failed to load resource: 403 @ https://api.maptiler.com/…/style.json?key=`
and renders an empty grey box. `.env` contains a real `MAP_TILE_KEY`.

**Root cause:** `MapTileConfigResolver` reads `integrations.map_tile_provider`
and `integrations.map_tile_key` from the **settings registry** (database,
administrator-managed). Nothing imports the `.env` values into it, and the
registry default is an empty string. The two `.env` variables are decoy
configuration: they look authoritative and are read by no code.

**Impact:** the map is dead out of the box on the home page, every territory
page, and the catalog — with no visible explanation. TZ §11, §15.

**Fix:**

1. Remove `MAP_TILE_PROVIDER` / `MAP_TILE_KEY` from `.env.example`, or give
   `SettingsRegistry` an env-backed default (`env('MAP_TILE_KEY', '')`) so the
   documented variable is real. Prefer the second: a fresh clone should get a
   working map from `.env` alone.
2. Render a labelled placeholder instead of an empty map container when
   `styleUrl()` has no key, and surface the same condition as a warning on the
   SEO/health dashboard.
3. The same applies to the CAPTCHA settings (`integrations.captcha_provider`,
   `…_site_key`, `…_secret`): the keys exist in the registry and are read by
   nothing — no form validates a CAPTCHA. Either wire them into the feedback
   form and the future review form, or remove them until they are.

### F-17 · The object page has no map

**Where:** `resources/views/public/object/show.blade.php`

**Observed:** the template renders description, services, rooms, prices,
nearby, similar, news, promotions, reviews, contacts, badges, and breadcrumbs —
and contains no `<x-public.map>` and no coordinate output, though the home page,
territory pages, and catalog all embed one and the object carries
`latitude`/`longitude`/`geom`.

**Impact:** TZ §6 lists «расположение; карта» among the object page's sections
and §7 lists «Карта»; a visitor cannot see where the object is.

**Fix:** add a location section rendering `<x-public.map>` centred on the
object's own coordinates at a close zoom with a single pin, omitted entirely
when coordinates are absent. Reuse the existing component — no new JS.

### F-18 · Footer "About" and "Contacts" are inert text

**Where:** `resources/views/components/public/footer.blade.php` — the entries
render through `x-public.nav-link` with a null href, which degrades to plain
text when the route does not exist.

**Observed (browser):** `listitem: About` and `listitem: Contacts` — no links.

**Impact:** TZ §4 lists «Контакты» and «О проекте» among the portal's pages,
and §2 lists «Контакты», «О проекте» among its sections. Neither page exists;
the footer advertises them and does nothing. The graceful degradation is
working as designed — the missing piece is the pages.

**Fix:** add two editable static pages alongside the existing legal pages
(`{lang}/about`, `{lang}/contacts`), sourced from the same translatable content
mechanism the privacy and terms pages use, with SEO fields. The contacts page
should carry the portal's own contact details from the settings registry, and
the feedback form.

### F-19 · "Photo views" counts page views, not photo views

**Where:** `app/Http/Controllers/Public/ObjectPageController.php:66-73`

```php
$photoCount = $object->getMedia('photos')->count();
for ($i = 0; $i < $photoCount; $i++) {
    $this->events->capture('photo_view', $object, [...]);
}
```

**Impact:** every object page view emits one `photo_view` per attached photo.
An object with 20 photos records 20 photo views for a visitor who scrolled past
none of them, and the owner's statistics screen (TZ §40 «просмотры
фотографий») reports a number that is `page views × photo count`. It also
multiplies the analytics write volume by the photo count on the portal's
hottest page.

**Fix:** emit `photo_view` from an actual interaction — a lightbox open or a
gallery advance — through a small endpoint or a batched client-side beacon.
Until that exists, emit **one** `photo_view` when the gallery is present rather
than one per photo, and relabel the statistic accordingly.

## Low

### F-20 · Open Graph gaps

`og:title` and `og:description` render; `og:image` renders only when the entity
has one (no portal-wide fallback, though `MetadataResolver::defaultOgImage()`
exists and returns nothing configured); `og:type`, `og:url`, and the whole
`twitter:*` card family are absent. TZ §13 requires Open Graph. **Fix:** set a
default OG image in the settings registry, always emit `og:type` and `og:url`,
and add `twitter:card`/`twitter:title`/`twitter:description`/`twitter:image`.

### F-21 · Banner mobile creatives are uploadable but never served

`Banner::registerMediaCollections()` declares both `desktop_creative` and
`mobile_creative`, and the back-office form uploads both — but every public
template (`home/show.blade.php` ×4, territory) renders
`getFirstMediaUrl('desktop_creative')` unconditionally. TZ §24.2 requires a
separate mobile creative. **Fix:** render a `<picture>` with the mobile source
in a `max-width` media query, falling back to the desktop creative when no
mobile one is uploaded.

### F-22 · No cookie-consent notice is rendered

`grep -ril cookie resources/views/` returns nothing, and the browser pass finds
no banner. `.drafts/TODO.md` records the item as done. **Fix:** implement it, or
correct the TODO. The portal sets a country-preference cookie and runs
analytics, so a consent notice is a legal requirement in the launch markets,
not a nicety.

### F-23 · The project's own quality gate cannot pass on Windows

`composer test` on the developer's own machine: **977 tests, 970 passed, 4
failed.** All four are the same authoring defect —

```
Exporter file [C:\…\booking\app/Filament\Admin\Exports\BannerExporter.php]
does not resolve to class [App\C:\…\Admin\Exports\BannerExporter]
```

`tests/Unit/DataTransfer/TransferableRegistryTest.php:112`,
`tests/Feature/Operations/QueueTopologyTest.php:178`, and two assertions in
`tests/Feature/Operations/DataTransferInvariantTest.php` build a class name by
string-replacing `app_path()` (backslashes on Windows) inside a glob result
(forward slashes), so the replacement never matches. CI on Linux is green,
which is why this has persisted. **Fix:** normalise separators before the
replacement (`str_replace(['/', '\\'], DIRECTORY_SEPARATOR, …)`, or derive the
class from the file's own namespace declaration). Also note the suite needs
`memory_limit` above the 128 MB default — worth a line in the README.

### F-24 · Five tables carry no model, service, or UI

`reservations`, `room_availabilities`, `booking_settings`,
`home_block_selections`, and `favorites` were migrated on 2026-08-06 and have
no `App\Models` class, no service, no seeder, and no screen. `favorites` is
*read* by `ObjectStatisticsService::favouriteCount()` — so the owner's
"added to favourites" figure is structurally always 0, since nothing writes the
table. `home_block_selections` was evidently meant for administrator-curated
home page blocks (TZ §101 «быстрые действия» and §5's "recommended objects"),
which the home page currently derives by query instead.

Three of them (`reservations`, `room_availabilities`, `booking_settings`) belong
to a `booking` feature module whose only consumer is a structured-data flag —
and the TZ is explicit that the portal is **not** a booking system and keeps no
occupancy calendar (§76, §1). **Fix:** decide per table and record the decision.
Either implement the surface, or drop the table in a new migration and remove
the dead read in `ObjectStatisticsService`. Carrying unreferenced schema
contradicts the project's own cleanliness rule and gives the client's future
maintainer a false map of the domain.

## Cleared — investigated and *not* defects

Recorded so they are not re-raised:

- **API token revocation and expiry.** An early probe appeared to show a
  revoked token still returning 200. That was an artefact of the harness:
  `withToken()` sets a *default header* that persists for the rest of the test,
  so the "no token" calls after it still carried one. Re-tested in isolation:
  revoked → 401, expired → 401, live → 200. Working correctly.
- **API authentication.** Same artefact; `auth:sanctum` correctly refuses an
  absent or bogus bearer token on all eight endpoints.
- **`/portal-admin/login` returning 500 for non-chief roles.** Appeared in the
  role matrix but not in isolation — every role gets a clean 302. A harness
  ordering effect, not a product defect.
- **Sitemap coverage.** An early reading suggested news, articles, and
  promotions were missing. After `GenerateSitemapsJob` runs, all five kinds are
  present. The stale index was the artefact.
- **Cross-owner cabinet isolation.** Nine direct-URL probes as a second owner
  against another owner's dashboard, object edit, room edit, news edit,
  promotion edit, statistics, reviews, and bump — **all refused**. A suspended
  owner is refused; an owner whose only object is pending moderation can still
  reach the cabinet, as intended.
- **Object page composition.** With a fully populated object, every TZ §6/§7
  block renders except the map (F-17) — rooms, prices, services, location,
  nearby, similar, object news, object promotions, reviews with rating and
  owner reply, contacts, availability and tier badges, breadcrumbs.
- **Tier precedence.** No lower-tier object outranks a higher-tier one on the
  catalog. Contact deep links resolve correctly for all eight channel types,
  and a cross-object contact click is refused (404).
- **Mobile layout.** At 390 × 844 the object page has no horizontal overflow.

## Recommended sequencing

1. **F-01, F-02, F-03** before anything else — a portal with no usable
   administrator, a broken language switch, and a 500 on a guessable URL is not
   demonstrable to a client.
2. **F-04, F-05, F-06** next — three crashes with the same one-line shape.
3. **F-08, F-09, F-07, F-10** — correctness of what the panel reports and who
   may reach it.
4. **F-11** as its own piece of work; it is a feature, not a fix.
5. The rest in severity order.

Each of these should become a failing test first — the project's own rule — and
F-08 in particular deserves an architecture test, since it is exactly the class
of defect that reads as correct on both sides.
