# Fix specifications — 2026-08-26 sweep

> [!IMPORTANT]
> **S-16, S-17 and S-18 outrank everything below them.** They come from
> `qa-tz-conformance-2026-08-26.md`, not the live behavioural sweep, and close
> gaps where the specification asks for a capability that was never built —
> not a working feature behaving badly. Plan these first.

One specification per confirmed finding in `qa-simulation-2026-08-26.md`, written
so the work can be planned without re-deriving the diagnosis. Each carries the
change, the guard that keeps it fixed, and how to verify.

Ordered by the sequence they should be taken in, not by severity alone — S-01 and
S-06 share a root cause and a fix pattern, and S-03 must land with S-12 or it
trades one broken map for a slow one.

## S-01 · Bound every Filament option list to a server-side search

**Fixes:** F-01 (blocker), and the same disease behind F-06.

**Problem.** Filament `Select` fields build their option array eagerly. With
`->searchable()` alone the filtering happens in the browser, so the whole table
is hydrated and rendered. `news-items/create` loads 52,800 objects with 105,600
translations, 3,001 users and 6,270 territories — 7 MB, 55 s, over 512 MB.

**Change.** Replace unbounded `->options(fn () => Model::query()…->get()…)` with
the server-side pair already used at `FinancialRecordForm.php:45-51`:

```php
Select::make('object_id')
    ->searchable()
    ->getSearchResultsUsing(fn (string $search): array => Object_::query()
        ->whereTranslationLike('name', "%{$search}%")
        ->limit(50)
        ->get()
        ->mapWithKeys(fn (Object_ $o): array => [$o->id => $o->name ?? "#{$o->id}"])
        ->all())
    ->getOptionLabelUsing(fn ($value): ?string => Object_::query()
        ->find($value)?->name)
```

**Sites**, grouped by the table each one loads:

| Table | Rows | Sites |
| --- | --- | --- |
| `objects` | 52,800 | `NewsItemForm.php:46` |
| `territories` | 6,270 | `NewsItemForm.php:55`, `PromotionForm.php:42`, `ObjectsTable.php:374`, `EditTerritory.php:123`, `AnalyticsReport.php:126`, `NotificationBroadcast.php:185` |
| `users` | 3,001 | `NewsItemForm.php:38`, `ArticleForm.php:38`, `FinancialRecordForm.php:113`, `ActionJournalTable.php:75`, `ModerationRequestsTable.php:89,123`, `EditObject.php:412`, `ObjectsTable.php:393` |

Small fixed registries — object types, amenities, placement tiers, languages,
countries — stay as they are. The rule is table growth, not the call shape.

**Guard.** An architecture test that fails when a Filament schema or table calls
`->options()` with a closure resolving an unbounded model. Pair it with a feature
test asserting `news-items/create` responds under a fixed memory limit
(128 MB is generous) — the memory ceiling is what actually broke.

**Verify.** `news-items/create` and `promotions/create` return 200 in well under
a second, under 128 MB, with a response measured in tens of kilobytes.

## S-02 · Make the action journal survive its own contents

**Fixes:** F-02 (blocker).

**Problem.** `ActionJournalTable.php:46,52` type-hint `?array $state` on columns
whose state arrives as a JSON string, so the screen 500s for every role from the
first audited change onward. A fresh install hides it because `audits` is empty.

**Change.** Normalise inside the closure instead of constraining the signature:

```php
TextColumn::make('old_values')
    ->formatStateUsing(function (mixed $state): string {
        $values = is_string($state) ? json_decode($state, true) : $state;

        return is_array($values) && $values !== [] ? (json_encode($values) ?: '—') : '—';
    })
```

Apply to `old_values` and `new_values` alike.

**Guard.** A feature test that writes **one** audit row carrying non-null
`old_values` and `new_values`, then asserts the journal page returns 200. The
existing coverage passes only because the table is empty — which is precisely
the state that conceals the defect. Extend the same reasoning wherever a Filament
column is typed against a cast: assert against a populated table, never an empty one.

**Verify.** Toggle a module, then open the journal; the entry renders with its
old and new values.

## S-03 · Stop serialising nulls into the map pins request

**Fixes:** F-03 (high). **Land together with S-12.**

**Problem.** `CatalogSearch::render()` dispatches `catalog-filters-changed` with
null `type` and `q`; `map.js` spreads that into `URLSearchParams`, which
stringifies `null` to `"null"`. The endpoint filters on the literal string and
returns `{"pins":[]}`, so the catalog map is empty in its default state — that
is, always, unless a visitor sets both a type and a query.

**Change — two sides, both worth making.**

JavaScript (`resources/js/map.js`, `fetchPins()`) — defensive, covers every
future dispatcher:

```js
const filters = Object.fromEntries(
    Object.entries(this.filters).filter(
        ([, value]) => value !== null && value !== undefined && value !== '',
    ),
);

const params = new URLSearchParams({
    sw_lat: bounds.getSouth(),
    sw_lng: bounds.getWest(),
    ne_lat: bounds.getNorth(),
    ne_lng: bounds.getEast(),
    ...filters,
});
```

PHP (`CatalogSearch.php:140-144`) — do not emit keys with no value, matching what
`map.blade.php:41` already does with `array_filter`.

**Guard.** A browser test that loads `/catalog` with no filters and asserts the
pins request returns a non-empty set. A unit test on the dispatch payload
asserting absent keys rather than null ones.

**Verify.** The catalog map shows clustered pins on first paint.

## S-04 · Give the "Add your object" call to action a destination

**Fixes:** F-04 (high).

**Problem.** The footer CTA links to `/cabinet/login`, which offers no
registration, and `ObjectResource::canCreate()` is `false`. The portal advertises
a route it does not have. §29.1 references owner registration, §104 references an
owner's submitted application, and Figma carries a `добавить отель` page.

**Change.** Build the application flow the specification describes:

1. A public, rate-limited, CAPTCHA-protected submission form capturing the object
   basics, the owner's contact details and the proposed territory and type.
2. An `object_application` record in a pending state — never a live object, and
   never a user account.
3. An admin queue where staff review an application and convert it into an object
   and an owner account in one action, or reject it with a reason.
4. Notification to the applicant on both outcomes.

Object creation stays staff-gated, which preserves the current guarantee; the
application is a request, not a write.

**Interim, if the flow is not in this release.** Point the CTA at the contacts
page and say plainly that staff perform onboarding. Do not ship a call to action
that ends at a login wall.

**Guard.** A feature test asserting the CTA's target returns a page a logged-out
visitor can act on.

**Note for the client.** This is the acquisition funnel for a paid-placement
directory. Staff data entry and XLSX import are the only current routes onto the
portal, which does not scale across three countries.

## S-05 · Split the settings permission so SEO cannot toggle modules

**Fixes:** F-05 (high).

**Problem.** `seo_specialist` holds `settings.edit`. `ModulePolicy::update()`
gates on it, so the role can enable or disable `reviews`, `booking`, `payment`,
`guest_accounts` or `api` portal-wide — 52,800 objects — and reach the language
registry. It holds 15 permissions yet reaches 29 back-office screens, more than
`country_administrator`.

**Change.** Split the coarse grant:

| Permission | Covers |
| --- | --- |
| `system_settings.view` / `system_settings.edit` | modules, languages, portal settings, integrations |
| `seo_settings.view` / `seo_settings.edit` | metadata templates, redirects, robots, sitemap |

Grant `seo_specialist` only the SEO tier. Repoint `ModulePolicy`,
`LanguagePolicy` and the portal settings page at the system tier. Migrate
existing grants so no role silently loses a capability it legitimately had.

**Guard.** A policy test per role asserting the exact set of back-office screens
it may reach. The 700-probe matrix in this sweep is the shape of that test; the
value is that it fails when a grant widens, rather than being rediscovered.

**Verify.** Re-run the role matrix: `seo_specialist` no longer reaches
`portal-admin/modules` or `portal-admin/languages`.

## S-06 · Replace the catalog territory select

**Fixes:** F-06 (high).

**Problem.** `CatalogSearch.php:162-165` loads every active territory with
translations on every render — 6,280 `<option>` elements, 641 KB, 2.4-2.6 s. It
grows linearly with a registry the specification requires to stay extensible, and
6,280 options is not a usable control at any speed.

**Change.** A searchable, server-backed destination picker: type-ahead against
`territory_translations` scoped to the active country, returning a bounded set.
Where the full tree matters, load it lazily one level at a time rather than
inlining it.

**Guard.** A feature test asserting the catalog response stays under a fixed size
ceiling (100 KB is ample) with the volume seeder applied. Size is the honest
signal here — the query count never moved, because the cost is hydration.

**Verify.** `/en/catalog` under 100 KB and inside the 400 ms cache-miss budget at
52,800 objects.

## S-07 · Collapse the territory page's per-type catalog blocks

**Fixes:** F-07 (high).

**Problem.** `TerritoryPageController::catalogBlocks()` calls the full catalog
search once per active object type. Each call re-runs the recursive
territory-subtree CTE and the whole eager-load stack: 8 types produce 159 queries
against a 30-query budget, and the count scales with a registry administrators
are meant to extend (§69).

**Change.**

1. Resolve the territory subtree **once** per request and pass the resolved id
   set into each block, so the recursive CTE runs a single time.
2. Fetch the objects for all types in one pass — a window function partitioned by
   `object_type_id` with a per-type row limit — then group in PHP.
3. Eager-load the shared relations once across the combined result rather than
   per block.

**Guard.** A test asserting the territory page stays within the 30-query budget
**with a number of object types above the seeded eight** — otherwise the guard
passes today and fails silently the moment an administrator adds a type.

**Verify.** Cold territory page inside the query budget; re-benchmark the subtree
expansion, which the project names as one of its two hottest paths.

## S-08 · Memoise and cache module resolution

**Fixes:** F-08 (moderate), and part of the cold cost on home and object pages.

**Problem.** `ModuleResolver::resolveOwnState()` walks a five-rung scope ladder
issuing one query per rung. With `module_settings` empty — the state of a fresh
install — every check walks all five and finds nothing, and nothing memoises
across call sites. The object page pays this eight times.

**Change.** Memoise per request, keyed by module key plus context. Behind that,
cache the resolved state in Redis with an explicit invalidation in
`ModuleAdministrator::setState()`, which is already the single write path.

**Guard.** A test asserting one request resolves a given module at most once.

**Verify.** Cold object page inside the 30-query budget.

## S-09 · Let the object card know how wide it is

**Fixes:** F-09 (moderate).

**Problem.** The card is authored as a full-width row (`sm:flex-row`, a 288 px
fixed image) but `scroll-row` places it in a 2- or 3-column grid. Tailwind
breakpoints key off the viewport, not the container, so the horizontal layout
activates in a ~448 px cell and leaves ~128 px for title, rating, price and
button. Titles clip mid-word and the "Подробнее" button is cut off — visible on
the home page and in the catalog's tile view.

**Change.** Give the component an explicit `variant`:

- `row` — today's horizontal layout, for full-width list surfaces
- `tile` — stacked image over content, for grid surfaces

Callers in `scroll-row` and the catalog's tile view pass `tile`; the catalog's
list view passes `row`. Container queries (`@container`) are the more elegant fix
and worth considering, but the prop matches how the component is already used in
two distinct ways and needs no build-target change.

**Guard.** A browser test at 1440 px asserting no object card overflows its grid
cell on the home page.

**Verify.** Cards render intact in both layouts, at desktop and mobile widths,
against the Figma card node.

## S-10 · Show amenity filters before a type is chosen

**Fixes:** F-10 (moderate).

**Problem.** `CatalogSearch::filterableAmenityGroups()` returns an empty
collection unless an object type is already selected, so `/catalog` offers four
filters and none of the twelve filterable amenities. §10 and §14 require
filtering by services; Figma shows a permanently visible sidebar.

**Change.** With no type selected, show the union of filterable amenity groups
across active types. Once a type is chosen, narrow to that type's groups as
today. If the union is judged too broad, make the type selector an explicit
first step that says what it unlocks — silence is the part that is wrong.

**Guard.** A feature test asserting the unfiltered catalog renders at least one
amenity filter group.

## S-11 · Remove the production-shaped development environment file

**Fixes:** F-11 (moderate).

**Problem.** `.env.production` carries `APP_ENV=local`, `APP_DEBUG=true`,
`LOG_LEVEL=debug`, the dev host, the dev database port, MinIO and Mailpit. It is
gitignored and untracked, so nothing is leaked — but a deploy that picks it up
serves stack traces, file paths and SQL to the public.

**Change.** Delete it; `.env.production.example` is the real template and is
correct. If a local production-like file is genuinely wanted, name it something
that cannot be mistaken for a deployment artefact.

**Guard.** A deploy-time assertion that refuses to release when `APP_DEBUG` is
true or `APP_ENV` is not `production`. This belongs in the pipeline, not in a
reviewer's memory.

## S-12 · Bound the map pins response

**Fixes:** F-12 (moderate). **Land together with S-03.**

**Problem.** `/map/pins` returns 2,151,356 bytes for a country-wide viewport —
every object, unclustered server-side. Clustering happens in the browser, so it
receives all 52,800 points. Currently masked by F-03 returning an empty set.

**Change.** Cap the response and cluster server-side by zoom: aggregate to
grid cells at low zoom returning cluster centroids and counts, switch to
individual pins past a threshold. Cap individual pins per request and tell the
client when the set was truncated so the map can ask the visitor to zoom in.

**Guard.** A test asserting a country-wide viewport response stays under a fixed
size ceiling with the volume seeder applied.

**Verify.** Fix and measure alongside S-03 — a working map that ships 2 MB is not
a fixed map.

## S-13 · Seed the paths that carry the business

**Fixes:** F-13 (low), and it is the reason several findings went unseen for two sweeps.

**Problem.** `DemoVolumeSeeder` creates 52,800 objects and zero contact channels —
the portal's entire product is the contact handoff. `banners`, `news_items`,
`promotions`, `articles`, `reviews` and `banner_slots` are empty too, so 14 admin
screens cannot be probed against a real record.

**Change.** Extend the seeder with contact channels for a realistic share of
objects (several channel types each, mixed activity), plus a modest volume of
each content entity and a populated `audits` table. Keep it in the `slow` group
so the ordinary development loop stays fast.

**Guard.** The seeder is the guard — but only if the volume-sensitive tests
actually run against it. Contact rendering, click tracking and the moderation
queue all deserve a volume test.

**Note.** F-02 (the action journal blocker) would have been caught two sweeps ago
by a single seeded audit row. The empty fixture was the reason it survived.

## S-14 · Make the Postgres host port configurable

**Fixes:** F-15 (low).

**Problem.** `docker-compose.yml:157` pins `"5433:5432"`. Windows reserves TCP
`5341-5440` for Hyper-V dynamic ports, so the bind fails with a permissions error
rather than "address in use" — an error that reads as a misconfiguration. The
same file already documents this exact class of collision for nginx (8300, not
8000).

**Change.** `"${DB_HOST_PORT:-5433}:5432"`, with `DB_HOST_PORT` in
`.env.example`, and a comment beside the existing nginx note naming the reserved
range. Container-to-container traffic is unaffected — it uses the service name on
5432.

## S-15 · Throttle the feedback form

**Fixes:** F-16 (moderate).

**Problem.** `POST /{lang}/feedback` accepts unlimited submissions from one
client; eight in a row all stored. The review route two lines below already
carries `->middleware('throttle:5,1')` and correctly refuses the sixth attempt.
The inconsistency reads as an oversight rather than a decision.

**Change.** Apply the same throttle shape to `/feedback`, and to
`/{lang}/country` at a looser rate. Where the form is expected to attract real
traffic, add the CAPTCHA check — the wiring already exists for review submission
in `open` mode, so this is configuration rather than new code.

**Guard.** A feature test asserting the endpoint returns 429 past its limit,
mirroring the review-submission test that already exists.

**Note.** Severity is bounded by the fact that `FeedbackSubmissionService` only
writes a row — no mail, no queued job. Should a staff notification ever be added
to this path, the missing throttle becomes an amplification vector and the
severity rises with it.

## S-16 · Build the placement-grant admin surface

**Fixes:** the paid-placement seller gap (`qa-tz-conformance-2026-08-26.md`,
§9/§12/§25/§25.4/§99/§101/§105/§112/§134).

**Problem.** `PlacementLifecycleService::grant()`, `pin()` and `unpin()` have no
caller anywhere in `app/Filament`. The only production caller of `grant()` is
`PlacementExpirySweepJob`, and it only ever demotes an expired object to its
lowest eligible tier. Staff can define a package and record that a payment
happened, but cannot connect the two: every object sits at the placement-order
default regardless of what was paid.

**Change.** This is a build item, not a patch — plan it as its own piece of
work:

1. An action on the object edit screen (or a dedicated relation manager) that
   calls `grant()` with a chosen package, sets the term, and journals the
   change — the service and the journal call already exist; only the screen
   is missing.
2. A manual position action wired to `pin()`/`unpin()`, surfaced next to the
   catalog-order display §112 asks for.
3. A bulk action on the objects table — "Assign placement package" — alongside
   the existing `assign_promotion_label` and `assign_manager` bulk actions,
   which are the pattern to follow.
4. A placement column (current tier, position, expiry) on the objects table
   and a read-only history view drawing on the same data the journal already
   records.
5. Package entitlements (promotions allowed, news allowed, photo cap) checked
   at the point an owner tries to use them, rather than universally granted —
   otherwise the tiers this action now lets staff sell buy nothing.

**Guard.** A feature test: grant a package to an object, assert the catalog
ordering query reflects it. A second test: attempt to use an entitlement the
object's package does not include, assert it is refused.

**Verify.** Staff can take a payment recorded in Financial Records and, in the
same visit, place that object into the tier it paid for — end to end, no direct
database access.

## S-17 · Build staff account and role administration

**Fixes:** the no-one-can-be-hired gap (§3/§9/§99/§121/§134).

**Problem.** `RoleGrantService` has no caller outside its own file. The only
user-facing admin resource, `Owners/`, narrows its query to the `object_owner`
role. None of the ten staff roles can be created, edited, blocked or reassigned
from the panel — onboarding a moderator requires seeding or direct SQL.

**Change.**

1. A Users/Staff resource, scoped the opposite way from `Owners/` — accounts
   holding any non-`object_owner` role — with create, edit, block and role
   assignment.
2. Wire it to `RoleGrantService`, which already exists and is presumably
   correct; it simply has nothing calling it today.
3. A permission-matrix view, even read-only at first, so the client can see
   what each role can do without reading `RoleSeeder.php` — this is also what
   would have surfaced the `seo_specialist`/`settings.edit` grant (F-05) to a
   human, not just to this sweep.
4. Scope assignment (country, region, category) through the same UI that
   already writes `role_scopes` for the seeded accounts — the mechanism this
   sweep used to provision its own probe accounts is the shape the panel needs.

**Guard.** A feature test creating a staff account through the resource and
asserting the resulting user can reach exactly the screens their role grants —
the 700-probe authorization matrix from this sweep is the template.

**Verify.** A chief administrator can onboard a new moderator, assign a scope,
and have that moderator sign in and reach exactly the right screens — without a
deploy.

## S-18 · Render banners on geographic pages

**Fixes:** the advertising-sells-nothing gap (§12/§24).

**Problem.** `BannerSelectionService::forSlot()` has exactly one call site —
`HomePageController`, requesting three home-page slots by language only. No
territory page, country landing page, catalog page, object page, news page or
article page ever asks for a banner. The targeting engine, admin CRUD,
impression counting and click tracking are all correct; the render calls are
what's missing, which is why this is smaller work than S-16 or S-17 despite the
severity.

**Change.**

1. Add `forSlot()` calls at the anchor points §24 names, passing the resolved
   territory and category so scope targeting has something to match against:
   territory pages (after the description, between catalog objects, before
   news), the catalog page, and the object page.
2. Seed the missing `BannerSlot` rows — none exist today, so the slot list is
   empty on a fresh install regardless of the render calls.
3. Pass `territory` and `category` context everywhere `forSlot()` is called,
   including the existing home-page calls, which currently pass only
   `language` — home-page scope targeting is equally unreachable today.

**Guard.** A feature test asserting a resort-targeted banner appears on that
resort's own territory page and does not appear on a different one — the
`Bukovel-only-on-Bukovel` guarantee the specification describes, currently
unobservable because nothing renders a banner anywhere it could be scoped.

**Verify.** An advertiser buying resort-level targeting receives impressions
when a visitor opens that resort's territory page.

## Not defects — record and confirm

**Figma carries booking-engine UI** (F-14): the home hero has a check-in/check-out
range and a guest-count selector, cards read "за 1 ночь для 2 взрослых", and
separate calendar and occupancy components exist. The specification forbids all
of it and the implementation correctly omits it. Confirm with the client so it is
not rediscovered as a missing feature.

**The country landing redirect**: `/{lang}/{country}` 301s to that country's first
top-level territory, deliberately and with the reasoning documented in
`CountryLandingController`. Worth confirming against §24 with the client, since a
country landing page with its own content is a plausible alternative reading.
