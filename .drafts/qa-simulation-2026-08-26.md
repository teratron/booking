# Full-funnel QA simulation — 2026-08-26

> [!IMPORTANT]
> This document covers **behavioural defects in what exists**. A parallel
> specification-conformance pass found something more consequential: **the
> placement-revenue seller path, staff-account administration, and geographic
> banner delivery were never built** — see
> `qa-tz-conformance-2026-08-26.md`. Read that document's three headline
> clusters before triaging anything below; none of F-01 through F-16 outrank
> them.

A third sweep, driven end to end against a running instance at realistic volume:
**52,800 objects · 105,600 object translations · 6,270 territories · 3,001 users**
(`migrate:fresh --seed` + `DemoVolumeSeeder`).

Every finding below was reproduced against the live application and traced to a
named file and line. Where an earlier suspicion turned out to be correct
behaviour it is recorded under "Checked and cleared" rather than dropped, so it
is not re-investigated next time.

## Environment

| Component | Value |
| --- | --- |
| PHP | 8.5.9 (host), opcache on |
| Laravel / Filament | 13.26.1 / 5 |
| PostgreSQL | 18 + PostGIS, `pg_trgm`, `unaccent` |
| Redis / MinIO / Mailpit | 8 / S3 / SMTP capture |
| App under test | `http://127.0.0.1:8080` (host PHP, native filesystem) |

Two environment facts shaped the method and are worth keeping:

- **The container path is unusable for measurement.** `php -r` boots in 0.7 s
  inside the app container but Laravel takes **24 s**, because `vendor/` is read
  across the Windows bind mount. The same boot on the host takes **1.5 s**. All
  numbers below come from host execution.
- **Postgres host port 5433 cannot bind on this machine.** Windows reserves TCP
  `5341-5440` (Hyper-V dynamic range), so a per-machine
  `docker-compose.override.yml` remaps it to `15432`. The project already
  documents this same class of collision for nginx (8300, not 8000) — see F-15.

## Severity summary

| # | Severity | Finding |
| --- | --- | --- |
| F-01 | **Blocker** | Admin content-creation screens take ~55 s, emit 7 MB, and exhaust 512 MB |
| F-02 | **Blocker** | The action journal returns HTTP 500 for every role once it holds one entry |
| F-03 | **High** | The public map never shows a single object pin |
| F-04 | **High** | The owner acquisition funnel is a dead end |
| F-05 | **High** | An SEO specialist can switch any module on or off portal-wide |
| F-06 | **High** | The catalog page renders the entire territory registry into one `<select>` |
| F-07 | **High** | The territory page issues 159 queries against a 30-query budget |
| F-08 | Moderate | The object page issues 77 queries; module resolution is uncached |
| F-09 | Moderate | The object card breaks in every multi-column grid |
| F-10 | Moderate | The catalog offers no amenity filters until a type is chosen |
| F-11 | Moderate | `.env.production` contains development configuration |
| F-12 | Moderate | The map pins endpoint returns 2.1 MB with no server-side bound |
| F-13 | Low | The volume seeder creates no contacts, and no content at all |
| F-14 | Low | Figma carries booking-engine UI the specification forbids |
| F-15 | Low | The Postgres host port is hardcoded into a machine-specific collision |
| F-16 | Moderate | The public feedback form has no rate limit |

## Blockers

### F-01 · Admin content-creation screens take ~55 s, emit 7 MB, and exhaust 512 MB

**Where:** `app/Filament/Admin/Resources/NewsItems/Schemas/NewsItemForm.php:38,46,55` ·
`app/Filament/Admin/Resources/Promotions/Schemas/PromotionForm.php:42` · and ~15 further sites

**Repro:** open `portal-admin/news-items/create` as any role that may reach it.

**Observed:**

| Screen | Wall | HTML | Queries | Memory |
| --- | --- | --- | --- | --- |
| `news-items/create` | **58,830 ms** | **7.36 MB** | 11 | > 512 MB |
| `promotions/create` | **55,047 ms** | **7.00 MB** | 9 | > 512 MB |

At a 512 MB limit both die outright with
`Allowed memory size of 536870912 bytes exhausted` in
`Illuminate\Database\Eloquent\Model.php` — they only complete at 2 GB.

**Root cause:** the form builds every `Select`'s option list eagerly:

```php
Select::make('object_id')
    ->options(fn (): array => Object_::query()
        ->with('translations')
        ->get()
        ->mapWithKeys(fn (Object_ $object): array => [$object->id => $object->name ?? "#{$object->id}"])
        ->all())
    ->searchable(),
```

`->searchable()` on a static option array filters **client-side**, so every row
is hydrated into an Eloquent model and rendered into the DOM. One news form
loads all 52,800 objects with their 105,600 translations, all 3,001 users, and
all 6,270 territories — for three dropdowns.

Only 9-11 queries are issued, which is why a query-count budget never caught
this: the cost is hydration and rendering, not the database.

**The correct pattern already exists in this codebase** and is used in two
places — `FinancialRecordForm.php:45-51` and `EditObject.php:452-461` — with
server-side search:

```php
->searchable()
->getSearchResultsUsing(fn (string $search): array => Object_::query()...)
->getOptionLabelUsing(fn ($value): ?string => ...)
```

**Fix:** convert every unbounded `->options()` over a large table to that
pattern. The affected sites, by table:

- **Objects (52,800):** `NewsItemForm.php:46`
- **Territories (6,270):** `NewsItemForm.php:55`, `PromotionForm.php:42`,
  `ObjectsTable.php:374`, `EditTerritory.php:123`, `AnalyticsReport.php:126`,
  `NotificationBroadcast.php:185`
- **Users (3,001):** `NewsItemForm.php:38`, `ArticleForm.php:38`,
  `FinancialRecordForm.php:113`, `ActionJournalTable.php:75`,
  `ModerationRequestsTable.php:89,123`, `EditObject.php:412`, `ObjectsTable.php:393`

**Guard:** add an architecture or feature test asserting that no Filament
schema calls `->options()` with a closure that ends in `->get()` or `->pluck()`
on a model whose table is unbounded. A rule a machine cannot check is a rule
that erodes.

### F-02 · The action journal returns HTTP 500 for every role once it holds one entry

**Where:** `app/Filament/Admin/Resources/ActionJournal/Tables/ActionJournalTable.php:46,52`

**Repro:** on a fresh install the screen renders (the table is empty). Perform
any audited administrative action — toggling a module is enough — then reopen
`portal-admin/action-journal/action-journals`.

**Observed:** HTTP 500 for **every** role including `chief_administrator`:

```
App\Filament\Admin\Resources\ActionJournal\Tables\ActionJournalTable::{closure:…configure():46}():
Argument #1 ($state) must be of type ?array, string given
```

**Root cause:** the column formatter declares an array parameter but is handed
the raw JSON string:

```php
TextColumn::make('old_values')
    ->formatStateUsing(fn (?array $state): string => $state === null || $state === [] ? '—' : (json_encode($state) ?: '—'))
```

`new_values` at line 52 is identical.

**Why it survived every previous pass:** `audits` is empty after
`migrate:fresh --seed`, so the closure never runs. It has to be a *used* portal
before the screen breaks — this sweep only hit it because enabling the API
module wrote the first audit row.

**Impact:** the action journal is the specification's audit trail (§53, §91,
§129) and the only record of who changed what. It is inaccessible from the
first audited change onward.

**Fix:** accept the value as given and normalise inside the closure rather than
constraining the signature — decode a string, pass an array through, render an
em dash for null/empty. Add a feature test that seeds one audit row with
non-null `old_values`/`new_values` and asserts the page returns 200; an empty
table is exactly the state that hides this.

## High

### F-03 · The public map never shows a single object pin

**Where:** `app/Livewire/Public/CatalogSearch.php:140-144` · `resources/js/map.js:116-122`

**Repro:** open `/ru/catalog` and watch the request the map issues.

**Observed:** the browser requests

```
/ru/map/pins?sw_lat=…&ne_lng=…&type=null&q=null   →  {"pins":[]}
```

The same bounds without those two parameters return **2,151,356 bytes** of pins.
The literal string `"null"` is matched as a filter value and nothing qualifies.

**Root cause:** the component dispatches its filter state including nulls —

```php
$this->dispatch(
    'catalog-filters-changed',
    type: $this->type,                        // null when no type is selected
    q: $this->q !== '' ? $this->q : null,
);
```

— and the map spreads that object straight into `URLSearchParams`, which
stringifies `null` to `"null"`:

```js
const params = new URLSearchParams({
    sw_lat: …, sw_lng: …, ne_lat: …, ne_lng: …,
    ...this.filters,
});
```

The initial page load is unaffected, because `map.blade.php:41` wraps its own
payload in `array_filter(...)`. Only the event path leaks nulls — which is the
path taken on every catalog render.

**Fix:** drop null/undefined/empty entries before building the query string in
`fetchPins()` (defensive, covers every future dispatcher), and omit null keys
from the `dispatch()` payload. Add a browser test asserting the catalog map
issues a pins request that returns a non-empty set in the default state.

### F-04 · The owner acquisition funnel is a dead end

**Where:** `resources/views/components/public/footer.blade.php:106` ·
`app/Filament/Cabinet/Resources/Objects/ObjectResource.php:53` ·
`app/Providers/Filament/CabinetPanelProvider.php:58-60`

**Repro:** click "Добавить объект" / "Add your object" in the footer.

**Observed:** the call to action links to `/cabinet/login`. That page contains
**no** registration link (zero anchors besides the form). Even after signing in,
`ObjectResource::canCreate()` returns `false`. There is no public submission
route. An owner therefore cannot get an object onto the portal by any path.

The panel documents the decision:

> No registration page: objects are never self-created through the cabinet,
> only assigned by staff via the admin panel's Owners resource.

**Why it is a gap and not a deliberate simplification.** Three independent
sources describe an owner-facing funnel:

- **§29.1** — "After **registration** and confirmation of their object, the owner
  receives access to their own administration panel."
- **§104** — "An administrator can create an object himself, **without an owner
  application**", alongside "An administrator can create an object **on the basis
  of an owner's submitted application**". The specification distinguishes the two
  paths, so the application path is required.
- **Figma** — a dedicated `добавить отель` frame (`234:5704`, 1440×4501) plus the
  footer CTA that is already implemented.

The CTA shipping without the destination is the part that is unambiguously
wrong: the portal currently advertises a route it does not have. Object
acquisition is otherwise staff data entry or XLSX import only, which does not
scale for paid placement across three countries.

**Fix:** either build the application flow (public form → moderated
`object_application` → admin converts to an object, per §104), or — as an
interim that is at least honest — point the CTA at a contact form and state
that staff perform onboarding. Do not leave it pointing at a login wall.

### F-05 · An SEO specialist can switch any module on or off portal-wide

**Where:** `database/seeders/RoleSeeder.php` (grant set) · `app/Policies/ModulePolicy.php:33-36`

**Observed:** `seo_specialist` holds `settings.edit` and `settings.view`.
`ModulePolicy::update()` gates on `settings.edit`, and the Modules screen's
actions call `apply($record, 'portal', null, $enable)`. The role reaches
`portal-admin/modules` (HTTP 200 confirmed) and can therefore enable or disable
`reviews`, `booking`, `payment`, `guest_accounts` or `api` **for the whole
portal** — a blast radius the screen itself reports as 52,800 objects.

It also reaches `portal-admin/languages`, the language registry.

For scale: `seo_specialist` holds 15 permissions but reaches **29** back-office
screens, more than `country_administrator` (30 permissions, 21 screens),
because `settings.*` is a single coarse grant covering unrelated system
surfaces.

**This is least-privilege, not a missing check.** The confirmation dialog and
blast-radius count are implemented and correct; the grant is simply wrong for
the role.

**Fix:** split `settings.*` into a system tier (modules, languages, portal
settings) and an SEO-adjacent tier, and grant `seo_specialist` only the latter.
Add a policy test per role asserting the exact screen set it may reach, so a
future grant change cannot silently widen a role.

### F-06 · The catalog page renders the entire territory registry into one `<select>`

**Where:** `app/Livewire/Public/CatalogSearch.php:162-165`

**Observed:** `/en/catalog` returns **641 KB** and takes **2.4-2.6 s warm** while
issuing only **2 queries**. The page contains **6,280 `<option>` elements** in a
single `<select id="catalog-territory">`.

```php
'territories' => Territory::query()->where('is_active', true)
    ->orderBy('display_order')
    ->with('translations')
    ->get(),
```

Unbounded, unfiltered by country, and re-hydrated on every render. The slowest
statement is the translation fetch for all 6,270 territories.

Against the budgets (`< 100 ms` cache hit, `< 400 ms` miss) this is 6-25× over,
and it grows linearly with a registry the specification requires to be
runtime-extensible.

**Fix:** replace with a searchable, server-backed control (the same treatment
F-01 needs) or a country-scoped, level-limited list. 6,280 options is also
unusable as an interface, independent of the transfer cost.

### F-07 · The territory page issues 159 queries against a 30-query budget

**Where:** `app/Http/Controllers/Public/TerritoryPageController.php:171-184`

**Observed:** `/en/md/territory-1` cold — **159 queries**, 1,858 ms, 172 KB.

```php
foreach ($types as $type) {
    $criteria = new CatalogSearchCriteria(territory: $territory, objectTypeId: $type->id, …);
    $objects = $this->catalog->search($criteria)->getCollection();
    …
}
```

Each iteration runs the **entire** catalog stack again. Grouped by shape, the
page repeats one block eight times — once per active object type:

```
 8x  with recursive "laravel_cte" as (… territories …)      ← subtree recomputed per type
 8x  select count(*) … objects left join object_placements …
 8x  select "objects".* … left join object_placements …
 8x  object_translations / object_types / territories / amenities /
     contact_channels / media / reviews / stat_dailies / rooms / prices …
```

The recursive territory-subtree CTE — one of the two hot paths the project
singles out for benchmarking — is executed eight times for one page.

**It scales with a runtime registry.** Eight seeded object types give 159
queries; the specification requires an administrator to add types without a
developer (§69). Twenty types would be roughly 370 queries on a single page.

**Fix:** resolve the subtree once per request and share it across blocks, and
fetch all types' objects in one pass (a windowed query partitioned by type, or
one query plus in-PHP grouping). Benchmark against seeded volume afterwards —
this is exactly the path the project's own performance section names.

## Moderate

### F-08 · The object page issues 77 queries; module resolution is uncached

**Where:** `app/Services/Modules/ModuleResolver.php:71-103`

**Observed:** `/en/o/{slug}` cold — **77 queries**, 945 ms. Of those, the same
`module_settings` lookup runs **8 times**, plus 2 `modules` lookups.

`resolveOwnState()` walks a five-rung scope ladder (object → owner → category →
country → portal) issuing **one query per rung** until a row matches. With
`module_settings` empty — its state after a fresh install — every check walks
the entire ladder and finds nothing, so each `isEnabled()` call costs up to five
queries, and nothing memoises the result across call sites within one request.

**Fix:** memoise resolution per request, keyed by module and context, and cache
the resolved state in Redis (module state changes rarely and already has an
administrator-facing invalidation point). Cold home is 49 queries for the same
family of reasons and benefits from the same fix.

### F-09 · The object card breaks in every multi-column grid

**Where:** `resources/views/components/object-card.blade.php:9,13` ·
`resources/views/components/public/scroll-row.blade.php`

**Observed:** on the home page ("Рекомендуем", "Новые объекты") and in the
catalog's tile view, object titles are clipped mid-word, the rating block
overlaps, and the "Подробнее" button is cut off at the card edge.

**Root cause:** the card is authored as a **full-width horizontal row** —

```html
<div class="flex w-full flex-col … sm:flex-row">
  <div class="… sm:w-72">   <!-- 288 px fixed image -->
```

— while `scroll-row` places it in `sm:grid-cols-2 lg:grid-cols-3`. At a 1440 px
viewport each cell is ≈ 448 px, so a 288 px image plus 32 px padding leaves
≈ 128 px for the title, rating, price and call to action.

Tailwind breakpoints key off the **viewport**, not the container, so `sm:flex-row`
activates even though the container is narrow. The component cannot express
"go horizontal only when I am actually wide".

**Fix:** either give the card a `variant` prop (`row` vs `tile`) so grid callers
opt into the stacked layout, or move to CSS container queries
(`@container`) so the card responds to its own width. The first is simpler and
matches how the component is already used in two distinct ways.

### F-10 · The catalog offers no amenity filters until a type is chosen

**Where:** `app/Livewire/Public/CatalogSearch.php:226-230`

```php
private function filterableAmenityGroups(?ObjectType $type): Collection
{
    if (! $type instanceof ObjectType) {
        return collect();
    }
```

**Observed:** `/ru/catalog` renders four filters — destination, type, price
range, minimum rating. `/ru/catalog?type=1` additionally renders the amenity
groups. Twelve amenities are flagged filterable and none of them is reachable
from the catalog's own entry point.

The specification asks for filtering by parking, pool, SPA, pets, playground,
Wi-Fi and meals (§10, §14), and the Figma catalog carries a permanently visible
sidebar with those groups.

The per-type scoping is defensible — amenity groups genuinely differ per type —
but the outcome is that the default catalog exposes almost none of the required
filtering, with nothing telling the visitor that choosing a type reveals more.

**Fix:** show the union of filterable amenity groups when no type is selected,
or surface the type selector as the explicit first step with a hint that it
unlocks the remaining filters.

### F-11 · `.env.production` contains development configuration

**Where:** `.env.production` (gitignored, untracked — no secret was committed)

**Observed:** the file named for production is a copy of the development
environment:

```
APP_ENV=local          APP_DEBUG=true         LOG_LEVEL=debug
APP_URL=http://booking.test
DB_HOST=127.0.0.1      DB_PORT=5433
AWS_ENDPOINT=http://localhost:9000     (MinIO)
MAIL_HOST=127.0.0.1    MAIL_PORT=1025  (Mailpit)
MAP_TILE_KEY=          CAPTCHA_SITE_KEY=      CAPTCHA_SECRET_KEY=
```

`.env.production.example` is correct (`APP_ENV=production`, `APP_DEBUG=false`).

A deploy that picks this file up runs the public portal in debug mode, which
renders full stack traces, file paths and SQL to anyone who triggers an error.
The API already demonstrates the shape — a 403 currently returns
`{"message":"Invalid ability provided.","exception":"…"}`.

**Fix:** delete the file (the `.example` is the real template) or correct it,
and add a deploy-time assertion that refuses to release when `APP_DEBUG` is
true or `APP_ENV` is not `production`.

### F-12 · The map pins endpoint returns 2.1 MB with no server-side bound

**Where:** `app/Http/Controllers/Public/MapPinsController.php`

**Observed:** with valid bounds and no filters, `/ru/map/pins` returns
**2,151,356 bytes** — every object in view, unpaginated and unclustered
server-side. Clustering is implemented client-side (`map.js`, `clusterRadius: 50`),
so the browser receives and clusters all 52,800 points.

This is currently masked by F-03: the endpoint returns an empty set in practice.
Fixing F-03 without also bounding this response would replace a broken map with
a 2 MB one.

**Fix:** cap the response, and cluster or simplify server-side by zoom level.
Fix alongside F-03, not after it.

### F-13 · The volume seeder creates no contacts, and no content at all

**Where:** `database/seeders/DemoVolumeSeeder.php`

**Observed:** the seeder produces 52,800 objects and **zero** `contact_channels`.
Contacts are the portal's entire product — it "publishes objects and hands
visitors directly to the owner's phone or messenger" — so the most important
path is the one the volume fixtures never exercise. Object-page measurements
taken before contacts were added understate the real cost (58 KB / 17 queries
warm without contacts, 64 KB / 77 cold with them).

Also empty: `banners`, `news_items`, `promotions`, `articles`, `reviews`,
`banner_slots`. Fourteen admin screens could not be probed with a real record
because their tables hold nothing.

**Fix:** extend the seeder to cover contact channels for a realistic share of
objects, plus a modest volume of each content entity, so the `slow` group
measures the paths that actually carry the business.

### F-16 · The public feedback form has no rate limit

**Where:** `routes/web.php:77`

**Repro:** submit `POST /{lang}/feedback` eight times in succession with a valid
CSRF token.

**Observed:** all eight are accepted (302) and stored. `feedback_submissions`
grows unbounded from a single client.

The sibling endpoint two lines below is throttled:

```php
Route::post('/feedback', FeedbackSubmissionController::class)          // no throttle
Route::post('/objects/{object}/reviews', ReviewSubmissionController::class)
    ->middleware('throttle:5,1')                                       // throttled
```

The review endpoint refuses the sixth attempt in a minute with 429, correctly.
Feedback accepts everything.

**Severity is moderate, not high**, because `FeedbackSubmissionService` only
writes a row — it sends no mail and queues no job, so there is no amplification
beyond table growth. It remains an unauthenticated, unbounded public write.

`POST /{lang}/country` is likewise unthrottled, but it only records a preference
and carries far less weight.

**Fix:** apply a throttle to `/feedback` in the same shape as the review route,
and add a CAPTCHA check if the form is expected to attract real traffic — the
CAPTCHA wiring already exists for review submission in `open` mode.

## Low

### F-14 · Figma carries booking-engine UI the specification forbids

**Where:** Figma `225:3619` (Главная), `244:116` (календарь), `225:3464` (выбор колво людей)

The home frame's hero carries a **check-in/check-out date range** and a
**guest-count selector**, and every card reads "от 500 грн / за 1 ночь для 2
взрослых". Separate calendar and occupancy-picker components exist as frames.

The specification is explicit that this is not a booking system — no
reservation, availability check, cart or stay payment. The implementation
correctly omits all of it.

**No action beyond confirmation.** Recording it because the divergence is
visible, deliberate, and should be acknowledged with the client rather than
rediscovered as a "missing feature" later. The house rule already covers it:
where Figma and the specification disagree, the specification wins and the
divergence is noted.

### F-15 · The Postgres host port is hardcoded into a machine-specific collision

**Where:** `docker-compose.yml:157`

`"5433:5432"` cannot bind on this machine: Windows reserves TCP `5341-5440` for
Hyper-V dynamic ports, and a bind inside a reserved range fails with a
permissions error rather than "address in use". The compose file already carries
exactly this reasoning for nginx (`8300`, not `8000`) — the database mapping did
not get the same treatment.

**Fix:** make the host port configurable (`${DB_HOST_PORT:-5433}`) so a
per-machine value does not require editing a tracked file, and note the reserved
range beside the existing nginx comment.

## Checked and cleared

Reproduced, found correct, and recorded so they are not re-investigated:

| Area | Result |
| --- | --- |
| **Authorization, 700 role × route probes** | Guests get 63/64 redirects to login and reach only the login page. `object_owner` and `object_staff_member` get **63/64 forbidden** — owners cannot enter the staff panel. Staff roles receive graduated access matching their duties. |
| **Review submission** | Missing CSRF → 419. `rating=9` → validation redirect. Valid submission stored as `pending`, never auto-published. Rate limited to 3, then 429. |
| **API** | `/status` and `/docs` public; protected endpoints 401 without a token. Narrow scope → 200 on granted, **403** on the rest. Revoked token → 401. Invalid token → 401. Rate limit exactly 60/min, then 429 with `Retry-After` and `X-RateLimit-*`. |
| **Contact deep links** | All five channel types resolve correctly — `tel:`, `https://wa.me/…`, `https://t.me/…`, `mailto:`, website. `{channel}` is the channel row id, not the type id. |
| **Owner cabinet** | 16/16 screens render, tenant-scoped to the owner's own object, 11-24 queries each. |
| **Cross-scope refusals** | `owners/{id}/edit` returns 200 only for a user holding `object_owner`; 404 for a chief administrator and for an `object_staff_member`. Correct scoping, not a bug. |
| **Moderation lifecycle (§44-49)** | Driven end to end for both outcomes. An owner's proposed change creates a `pending` request and **the published record does not move while it is pending** — verified on the live row, not inferred. Approve applies the proposed data; reject leaves the published record exactly as it was. Both settle with the deciding moderator recorded. A section outside `moderation.moderated_change_types` correctly publishes immediately instead of queueing. |
| **Feedback submission** | Stores correctly, rejects a missing CSRF token with 419, and redirects back with errors on an invalid email. Rate limiting is the one gap — see F-16. |
| **Injection / XSS** | `<script>alert(1)</script>` in the catalog query is HTML-escaped in the Livewire snapshot. `' OR 1=1--` and `UNION SELECT` payloads return 200 with no error — statements are parameterised. |
| **Locale and filter edges** | `/xx`, `/EN`, `/ru-RU`, `/en%00`, `/../etc/passwd` all 404 cleanly. `page=0`, `page=-1`, `page=abc`, inverted price ranges, `ratingMin=99`, unknown type and territory ids all degrade to an empty result set, never an error. |
| **Country landing redirect** | `/{lang}/{country}` 301s to that country's first top-level territory. Deliberate and documented in `CountryLandingController`. |
| **Quality gate** | `pint` passes. `phpstan` level 8 passes with 0 errors. `composer audit` and the pnpm/Fallow audit report no advisories. |
| **Hygiene** | No `dd`/`dump`/`var_dump`/`ray`/`print_r` outside tests. No `TODO`/`FIXME`/`HACK`/`@deprecated` anywhere in `app/`, `resources/`, `database/`. |
| **Secrets** | The GitHub App private key in `.secrets/` is covered by its own `.gitignore` (`*`) and appears nowhere in git history. `.env*` is ignored except the two `.example` files. |
| **Migrations** | `migrate:fresh --seed` applies cleanly from empty in 59 s. |
| **Grid/list toggle** | Implemented (`Плитка` / `Список`, `viewMode`), and `setViewMode()` whitelists the value. Recorded as missing in the previous sweep; it is present now. |
| **Map clustering** | Implemented client-side with `clusterRadius: 50`, cluster counts and pin cards. The basemap did not paint under headless Chromium, but MapTiler style, tiles and sprite all returned 200 — treated as a harness limitation, not reported as a defect. |

## Measured baseline

One process per request, cache cleared before each cold run.

| Page | Cold ms | Cold queries | Warm ms | Warm queries | Size | Budget |
| --- | --- | --- | --- | --- | --- | --- |
| `/en` (home) | 832 | **49** | 886 | 24 | 62 KB | ≤ 30 queries |
| `/en/catalog` | 3,401 | 25 | **2,616** | 2 | **641 KB** | < 400 ms miss |
| `/en/o/{slug}` | 945 | **77** | 576 | 17 | 64 KB | < 300 ms miss |
| `/en/md/territory-1` | 1,858 | **159** | 883 | 8 | 172 KB | ≤ 30 queries |
| `/en/news` | 371 | 10 | 160 | 1 | 26 KB | — |
| `/en/blog` | 241 | 10 | 151 | 1 | 26 KB | — |
| `/en/about` | 62 | 0 | — | — | — | framework floor |

`/en/about` at 62 ms with zero queries establishes the framework floor, so the
figures above are application cost, not boot overhead.

**On the timings.** These come from Windows + PHP's built-in server; a tuned
Linux `php-fpm` host would be materially faster. The **query counts and payload
sizes are platform-independent**, and so is the shape of F-06 and F-07 — both
are algorithmic, scaling with registries the specification requires to stay
runtime-extensible.

**Concurrency was not measured.** PHP's built-in server ignores
`PHP_CLI_SERVER_WORKERS` on Windows, so 20 concurrent requests serialise
(4.3 s → 14.3 s, linearly). That measures the harness, not the application. Real
concurrency figures need `php-fpm`; from per-request cost, one worker sustains
roughly 1.7 object pages/s, 1.1 home pages/s and **0.38 catalog pages/s**.
