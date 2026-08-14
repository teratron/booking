---
phase: 5
name: "Public Site"
status: Todo
subsystem: "resources/views, app/Livewire, app/Services"
requires: ["phase-1", "phase-2", "phase-3"]
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 5 Tasks — Public Site

**Phase:** 5
**Status:** Todo
**Strategic Goal:** The portal's acquisition surface — server-rendered Blade with
Livewire for catalog interactivity, built node by node against the Figma source, and
instrumented so that the contact click (the portal's only conversion signal) is never
lost.

## Atomic Checklist

### Track A — Site Shell, Catalog Query & Card Foundation

- [ ] [T-5A01] Public layout shell — header, navigation, switchers, footer
- [ ] [T-5A02] 404 page and static legal pages
- [x] [T-5A03] CatalogQueryService — the shared tier-ordered retrieval contract
- [x] [T-5A04] ContactChannelType model and deep-link resolution service
- [ ] [T-5A05] Object card component and card-view event emission
- [ ] [T-5A06] Clustered map — MapLibre GL JS and tile provisioning

### Track B — Object Profile

- [ ] [T-5B01] Object profile composition and type-varying detail
- [ ] [T-5B02] Contact rail, interaction flow, and contact-click emission
- [ ] [T-5B03] Reviews rendering (module-gated)
- [ ] [T-5B04] Nearby/similar objects and the object's own news/promotions feed

### Track C — Catalog & Territory Listing Pages

- [ ] [T-5C01] Catalog / search results page
- [ ] [T-5C02] Territory landing pages

### Track D — Home Page & Content Surfaces

- [ ] [T-5D01] Public blog — listing and article detail
- [ ] [T-5D02] Public news feed and promotions section
- [ ] [T-5D03] Home page composition

### Track T — Validation

- [ ] [T-5T01] Placement-tier ordering invariant across every public listing surface
- [ ] [T-5T02] Event-emission invariant — never blocks, never fails visibly
- [ ] [T-5T03] Public performance budget under seeded volume

## Track Ordering

`T-5A03` (`CatalogQueryService`) is this phase's hard gate, the same role `T-2A02`
played for Phase 2 and `T-4A01` for Phase 4: every listing surface in Tracks B, C, and
D — the object profile's nearby/similar block, the catalog page, every territory
page's per-type catalogs, and five of the home page's sixteen blocks — is a caller of
this one retrieval contract, never a reimplementation, per the spec's own
Implementation Note #1. Getting the tier-ordering wrong here is wrong on every surface
at once.

`T-5A01` (shell) is a second, independent gate: every other page in this phase renders
inside it. `T-5A04` (contact deep-link service) gates both `T-5A05` (the card's own
contact actions) and `T-5B02` (the object profile's contact rail) — modelling the
per-type link template once, not twice. `T-5A05` (card) and `T-5A06` (map) both depend
on `T-5A03`, since a card renders one retrieval result and the map renders the same
filtered set as pins.

Track B does not depend on Track C or D. Track C depends on Track A in full
(`T-5A01`, `T-5A03`, `T-5A05`, `T-5A06`) but not on Track B. Track D has one internal
ordering rule worth stating: `T-5D01` (blog) and `T-5D02` (news/promotions) land before
`T-5D03` (home), because five of the home page's blocks link into pages those two
tasks build — a home page shipped first would link to routes that do not exist yet.

Track T runs last, after every other track, mirroring Phase 4's own Track T: its three
invariants (tier ordering, event emission, performance budget) need every public
surface this phase adds to actually exist before they can be exercised together rather
than per-task.

## Scope

| Area | Spec |
| --- | --- |
| Header, data-driven navigation, language and country switchers, footer | l1-platform-shell.md §5.1–§5.3 |
| 404, legal pages, feedback overlay | l1-platform-shell.md §3 |
| Home page — sixteen blocks, four viewport classes, country-aware | l1-home-page.md §5.1–§5.4 |
| Territory landing pages | l1-geography.md §5.3 |
| Catalog — search parameters, filters, pagination, grid and list | l1-object-catalog.md §5.1–§5.3 |
| Clustered map synchronized with the result set | l1-object-catalog.md §5.4 |
| Object profile composition and the sticky contact rail | l1-object-profile.md §5.1–§5.3 |
| Reviews rendering (module-gated) | l1-object-profile.md §5.4 |
| Public blog, news, and promotion surfaces | l1-content-publishing.md §5.3 |
| First-party event emission from every measured surface | l1-analytics.md §3.1, §3.2 |
| Map tiles and CAPTCHA provisioning | l2-third-party-integrations.md §5.3, §5.5 |

## Standing Constraints

- **No public catalog retrieval query, event-emission caller, `ContactChannelType`
  model, or map component exists yet** — Phases 1–4 built the schema, the admin
  panel, and the owner cabinet, but the public site is greenfield. `EventCaptureService`
  and `CaptureStatEventJob` (Phase 3) already exist and are proven from one caller
  (`BannerClickController`); this phase's own event tasks are wiring new callers into
  that existing service, never building a second capture mechanism.
- **Every page is built against the Figma node, not against a written description of
  it.** Pull the node, adapt the reference output to Blade and Tailwind, and verify
  against the screenshot. Design tokens go into the Tailwind theme once and are reused;
  no magic values in templates.
- One responsive template per page. Frames exist in desktop and mobile pairs; that is
  two views of one page, not two pages.
- Every user-facing string is translatable. No literal copy in Blade or Livewire.
- Ordering on every listing surface — including the home page's rails — is
  placement-tier first. The home page is the portal's most valuable placement surface;
  exempting it would let a standard-tier object outrank a VIP one where it matters most.
- Every block degrades independently. A block with no content is omitted entirely,
  never rendered as an empty frame.
- **Public OpenStreetMap tile servers are prohibited in production** by the OSMF Tile
  Usage Policy. Ship against MapTiler, Stadia, or self-hosted tiles — never
  `tile.openstreetmap.org`. The previous implementation shipped this violation
  unnoticed, which is how it was found.
- The contact event is emitted before navigation and must not delay it. A measurement
  failure may never cost the visitor the hand-off it exists to measure.

## Detailed Tracking

### [T-5A01] Public layout shell — header, navigation, switchers, footer

- **Spec:** l1-platform-shell.md §3, §5.1–§5.4
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PublicShell` proves the shell renders on a representative route in all four viewport classes, with mobile collapsing navigation into a drawer menu; both switchers list every active language/country from their real registries; navigation entries are read from the object-type and content registries, not hard-coded, proven by seeding a type and asserting it appears; breadcrumbs render as links on every page below the home page; the feedback overlay is invokable from a representative page.
- **Handoff:** T-5A02 (404/legal render inside this shell), and every subsequent public page in this phase.
- **Notes:** Build as a layout wrapping every public route, so all later pages inherit it rather than reproducing it, per the spec's own Implementation Note #1. Cache the active language/country lists globally rather than querying per request (Implementation Note #3).

### [T-5A02] 404 page and static legal pages

- **Spec:** l1-platform-shell.md §3 (Core Invariants), §5.1
- **Status:** Todo
- **Assignment:** Agent
- **Requires:** T-5A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PublicErrorAndLegal` proves an unresolved route renders the 404 page (not a framework default) in all four viewport classes and carries a noindex directive; the privacy policy and terms-of-use pages render, are linked persistently from the footer, and are reachable without authentication.
- **Handoff:** none within this phase.
- **Notes:** The 404 page must itself never be indexable — verify the noindex directive directly against the rendered response head, not merely that the page renders.

### [T-5A03] CatalogQueryService — the shared tier-ordered retrieval contract

- **Spec:** l1-object-catalog.md §3.2, §3.3, §5.2, §5.3, §5.5; l1-geography.md §5.4
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CatalogQueryService` proves scope resolution expands a territory to itself plus every descendant (via `Territory`'s own `descendants()`, already available through `HasRecursiveRelationships` — no second tree-walk); the ordering contract is delegated to the existing `PlacementOrderingService::apply()` rather than reimplemented, falsified by asserting the query builder passed through it carries that service's own join/order clauses; filters (amenities, price, distance, catering) combine with AND semantics and round-trip through one parameter shape home/catalog/territory pages will share; a type's own `attribute_schema` and applicable amenity groups gate which fields and facets apply, proven against two differently-typed fixtures; pagination is offset-addressable, not infinite-scroll-only.
- **Handoff:** T-5A05 (card), T-5A06 (map), T-5B04, T-5C01, T-5C02, T-5D03 — every listing surface in this phase is a caller of this service, never a reimplementation.
- **Notes:** **Reuse, do not reimplement, `App\Services\Placement\PlacementOrderingService::apply()`** — it already carries the full tier → pinned-position → scoped-bump-recency → promotion-weight → internal-priority → created-at chain (built ahead of this exact call site, per its own docblock), including the deliberate, documented absence of a `rating` term (`objects.rating` does not exist — do not add a fabricated ordering criterion for it). This task's own job is scope resolution, filtering, and pagination around that existing ordering call, not a second `ORDER BY`. **The indexes the retrieval implies already exist** — an earlier index-plan migration already built the composite scope index, the partial published-status index, the GIN index on `attributes` for type-declared attribute filters, the trigram index for name search, the bump-recency composite, and `territories.parent_id` for descendant expansion — confirm each query path actually uses one of these (composite prefix order matters: a territory-scoped query must also filter `country_id` to use the composite scope index as a leading prefix) rather than assuming a new index is needed. Cache per (scope, filters, sort, page, language, country); invalidation keys are read directly from the existing bump/package-change/moderation-approval/availability-toggle/object-edit writes already produced elsewhere, never a second invalidation scheme.
- **Changes:** New `App\Services\Catalog\CatalogQueryService::search(CatalogSearchCriteria $criteria): LengthAwarePaginator` and `App\Support\Catalog\CatalogSearchCriteria` (readonly DTO). Territory scope expands via `Territory::descendantsAndSelf()`; ordering delegates entirely to the existing `PlacementOrderingService::apply()`. Filters implemented: amenity IDs (AND semantics via `whereHas`), price range (a `whereExists` subquery against `prices`, matching either a price row directly on the object or on one of its rooms — no `prices()` relation existed on `Object_` to reuse), a minimum average rating (a fresh `whereExists`/`having avg(rating)` subquery against published, non-deleted `reviews` — no rating column or aggregate exists on `objects`), and type-declared `attributes` (jsonb) numeric-range or exact-match filters, with the filter key always bound as a query parameter to the `->>` operator rather than interpolated into SQL. A real gap surfaced and closed along the way: territory-scoped queries now also filter `country_id` (redundant with `territory_id` but necessary) so they use the existing `objects_scope_ordering_index` composite as a true leading-column match — without it the territory-scoped hot path could not use that index at all.
- **Evidence:** `tests/Feature/Public/CatalogQueryServiceTest.php`, 9 cases: transitive territory-descendant expansion; a genuine falsification of the delegation claim (`PlacementOrderingService` rebound to throw, confirming `CatalogQueryService` genuinely resolves it as a collaborator rather than bypassing it with a parallel implementation — the class is `final`, so this replaces a Mockery spy); a tier-vs-recency ordering proof (a newer, lower-tier object never outranks an older, higher-tier one); AND-semantics amenity filtering; price-range filtering across both the direct-on-object and via-room cases; rating filtering that excludes a non-published review from the average; a type-declared attribute range filter; offset pagination; and published/moderation-approved-only visibility (draft, pending, and hidden fixtures all excluded). One planning correction made before implementation: initial research found `PlacementOrderingService::apply()` already implements the full ordering contract (built in an earlier phase anticipating this exact call site) — the task's own Verify/Notes text was corrected to reuse it rather than reimplement a second `ORDER BY`, avoiding wasted, conflicting work. A second correction: a first attempt at adding catalog indexes collided with an already-existing, more comprehensive index-plan migration (composite scope index, partial published-status index, GIN on `attributes`, trigram name search, bump-recency composite, `territories.parent_id`) built ahead of time for this exact task — the redundant migration was removed in favor of reusing the existing composite index (adding a `country_id` filter to the service instead of a new index). A containment near-miss was also caught and fixed before commit: two docblocks referenced a spec filename and "Phase 3" prose, both forbidden by this project's own SDD containment rule — rewritten in plain language, confirmed clean via `ContainmentTest`. Independently re-verified: Pint and PHPStan (level 8, both the touched files and the whole app) clean; full non-slow suite 587 passed, 0 failed, 3 skipped (up from 578).

### [T-5A04] ContactChannelType model and deep-link resolution service

- **Spec:** l1-object-profile.md §5.2
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=ContactChannelDeepLink` proves a raw value (a phone number, a handle) resolves to the correct actionable link per channel type (`tel:`, `viber://`, `https://wa.me/`, `mailto:`, a bare URL for website) purely from the type's own link template — no per-channel branching in the caller; an inactive channel type never resolves a link; adding a channel type through the registry (no code change) is reachable by a caller without touching the resolution service.
- **Handoff:** T-5A05 (card contact actions), T-5B02 (contact rail).
- **Notes:** `ContactChannelType` has a migration and is seeded but has no Eloquent model yet — this task adds it. Model the link template as registry data on the type, per the spec's own §5.2 diagram, so "other social networks" (`[TZ]` §74) is a data row, not a code change.
- **Changes:** New `App\Models\ContactChannelType` (translatable, `display_name`) and `App\Models\ContactChannelTypeTranslation`; a `contactChannelType()` relation added to the existing `ContactChannel` model. New `App\Services\Contact\ContactChannelLinkResolver::resolve(ContactChannelType $type, string $rawValue): ?string` — plain `{value}` placeholder substitution against the type's own `link_template`, returning null for an inactive or template-less type rather than fabricating a link. A real gap closed alongside this: `contact_channel_type_translations` predated this project's `needs_review`/`published_at` translation-completeness convention (the same class of gap `room_translations` hit earlier) — nothing made `ContactChannelType` a `Translatable` model until now, so `TranslatableEntityRegistry`'s reflection-based discovery would have picked it up and crashed `TranslationCompletenessReport` on the missing columns; closed with a migration mirroring the established precedent exactly, including its own backfill statement.
- **Evidence:** `tests/Feature/Public/ContactChannelDeepLinkTest.php`, 4 cases: real template substitution across four different template shapes (`tel:`, `wa.me`, a `viber://` query-string form, and a bare pass-through for website); inactive-type refusal; template-less refusal; and a genuinely falsifying case — a channel type ("snapchat") that appears nowhere in the resolver's own source, proving the substitution is generic rather than a disguised per-channel branch. One real architecture violation found and fixed during verification: the model's own docblock named the resolver via an `{@see}` tag, which Pint's `fully_qualified_strict_types` fixer turned into a real `use App\Services\Contact\ContactChannelLinkResolver;` import — invisible to Pint and PHPStan, but caught by the full suite's own "models are thin" architecture test, since an `App\Models` file importing `App\Services` fails it regardless of whether the import is only ever used inside a docblock. Fixed by naming the service in plain prose instead of a resolvable class reference. Independently re-verified: Pint and PHPStan (level 8) clean; full non-slow suite 591 passed, 0 failed, 3 skipped (up from 587).

### [T-5A05] Object card component and card-view event emission

- **Spec:** l1-object-catalog.md §3.4, §5.5; l1-analytics.md §3.1, §3.2
- **Status:** Todo
- **Assignment:** Agent
- **Requires:** T-5A03, T-5A04
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PublicObjectCard` proves the card renders exactly the fields §3.4 names (cover photo, name, settlement, short description, key services, rating, view count, a details action, direct contact actions) plus the tier's visual treatment and, for accommodation types, the availability badge; card geometry is uniform across every tier (asserted directly, not assumed) — a tier changes border/badge/header colour, never card size; a dining-type fixture never renders an availability badge, an accommodation-type fixture always does when its type declares the capability; an `object_card_view` event is captured through the existing `EventCaptureService` on render, falsified by asserting the capture call fires exactly once per card, not per page.
- **Handoff:** T-5C01, T-5C02, T-5D03, T-5B04 — every block that lists objects renders this one component.
- **Notes:** The home page introduces no second card (l1-home-page.md §2) — this is the only card component in the codebase. Card-view capture reuses `EventCaptureService`/`CaptureStatEventJob` exactly as `BannerClickController` already does for `banner_click`; do not build a second capture mechanism.

### [T-5A06] Clustered map — MapLibre GL JS and tile provisioning

- **Spec:** l1-object-catalog.md §5.4; l2-third-party-integrations.md §5.3
- **Status:** Todo
- **Assignment:** Agent
- **Requires:** T-5A03
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PublicMap` proves the map renders pins for exactly the filtered result set, not the full object table; a pin opens a compact card carrying the same contact actions as a list card; the configured tile provider is never `tile.openstreetmap.org`, asserted directly against the shipped tile URL configuration, not merely absent from a template by inspection; a filter change updates pins in the same round trip that updates the card list.
- **Handoff:** T-5C01, T-5C02, T-5D03 — every surface embedding a map.
- **Notes:** Clustering runs client-side in MapLibre with bbox filtering served by PostGIS, per the spec's own decision. Route-building hands off to the visitor's own map application with the object's coordinates as the destination — the portal never embeds turn-by-turn routing itself.

### [T-5B01] Object profile composition and type-varying detail

- **Spec:** l1-object-profile.md §3.2, §5.1, §5.5
- **Status:** Todo
- **Assignment:** Agent
- **Requires:** T-5A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PublicObjectProfile` proves the page renders breadcrumb, cover + placement badge + availability badge, gallery, name/type/category/rating/settlement, short and full description, the type-specific block (rooms/prices/catering/house-rules for accommodation; cuisine/cheque/hours for dining; visiting information for attraction), and services/infrastructure — each section present only when the object's type declares it, proven against two differently-typed fixtures; every section degrades independently, proven by an object with a partial gallery and no video still rendering a complete page; an `object_page_view` and per-photo `photo_view` are captured through `EventCaptureService`.
- **Handoff:** T-5B02, T-5B03, T-5B04 — each composes into this page.
- **Notes:** Render from one type-aware composition, never a branch per type (Implementation Note #2) — adding an object type must not require editing this page. Page capability does not vary by placement package (§3.2) — assert this directly, not merely assume it from the query's own scope.

### [T-5B02] Contact rail, interaction flow, and contact-click emission

- **Spec:** l1-object-profile.md §3.1, §5.2, §5.3; l1-analytics.md §3.2
- **Status:** Todo
- **Assignment:** Agent
- **Requires:** T-5A04, T-5B01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PublicContactRail` proves the rail renders every active contact channel in display order, each resolving through T-5A04's own deep-link service; activating a channel navigates directly to it with no portal-side proxy or intermediation, asserted against the rendered link's own href; a `contact_click` event (object, channel, territory, language) is emitted before navigation, falsified by asserting the emission call is reachable even when the destination link itself would fail to resolve; contact channels remain functional regardless of availability status or placement tier, proven against fixtures at both extremes; the rail keeps its above-the-fold position.
- **Handoff:** T-5T02 (event-emission invariant exercises this task's own claim directly).
- **Notes:** Build the rail and its instrumentation together, in the same change (Implementation Note #1 of both l1-object-profile.md and l1-analytics.md) — a shipped contact link with no event is a permanently lost metric; unlike most analytics gaps it cannot be backfilled. This is the portal's only conversion signal; treat this task's own correctness as load-bearing for the whole phase.

### [T-5B03] Reviews rendering (module-gated)

- **Spec:** l1-object-profile.md §3.4, §5.4
- **Status:** Todo
- **Assignment:** Agent
- **Requires:** T-5B01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PublicReviews` proves the page shows an aggregate score plus itemized reviews, each carrying rating/text/author/date and an owner reply where one exists; an object with zero reviews renders without an empty review block; the section is present only when the review module resolves enabled for the object's scope, absent (not disabled) otherwise; only published (moderation-approved) reviews render.
- **Handoff:** none within this phase.
- **Notes:** Review submission is out of scope — this task renders whatever review rows already exist (owner reply/report already ships from the cabinet). Reuse the module resolution ladder already established (object → owner → category → country → portal) rather than a page-local check.

### [T-5B04] Nearby/similar objects and the object's own news/promotions feed

- **Spec:** l1-object-profile.md §5.1; l1-content-publishing.md §5.3, §5.5
- **Status:** Todo
- **Assignment:** Agent
- **Requires:** T-5A03, T-5A05, T-5B01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PublicObjectRelated` proves nearby/similar objects render through T-5A03's own `CatalogQueryService` (tier-ordered, not a bespoke query, falsified by seeding a lower-tier nearby object and asserting it never outranks a higher-tier one), scoped to the object's own territory; the object's own published news and promotions render using the shared content component, never a bespoke one; a section with nothing to show is omitted entirely.
- **Handoff:** none within this phase.
- **Notes:** Reuse the article component from l1-content-publishing.md rather than authoring a bespoke one for this page (Implementation Note #4 of l1-object-profile.md).

### [T-5C01] Catalog / search results page

- **Spec:** l1-object-catalog.md §3.3, §5.1
- **Status:** Todo
- **Assignment:** Agent
- **Requires:** T-5A01, T-5A03, T-5A05, T-5A06
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PublicCatalog` proves the search surface accepts the full parameter set (§5.1: geography, identity, taxonomy, amenities, commercial, proximity, facilities, stay) and round-trips it through the URL, so a filtered view is shareable and back-navigable; changing a filter never silently discards the active sort and vice versa; results render as both grid and list, and the choice persists for the visitor; results and map pins update in the same round trip; pagination is addressable by page number in the URL, not only by infinite scroll.
- **Handoff:** T-5T01 (tier-ordering invariant), T-5T03 (performance budget).
- **Notes:** This page is a caller of T-5A03, never a second retrieval implementation — if this task finds itself writing an `ORDER BY` clause, that belongs in T-5A03 instead.

### [T-5C02] Territory landing pages

- **Spec:** l1-geography.md §5.3, §5.4
- **Status:** Todo
- **Assignment:** Agent
- **Requires:** T-5A01, T-5A03, T-5A05, T-5A06, T-5C01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PublicTerritory` proves a territory page renders breadcrumb, hero, short description, a catalog block per active object type (each independently omitted when empty — a territory with no restaurants renders without the dining block), territory news and promotions, a map centred on the territory, an SEO text block, and child-territory navigation; scoping is transitive, proven by seeding an object on a descendant node and asserting it appears in an ancestor's catalog blocks; tier ordering restarts per territory, proven by seeding the same tier distribution under two sibling territories and asserting each orders independently.
- **Handoff:** T-5T01, T-5T03.
- **Notes:** Every catalog block on this page is a scoped call into T-5A03, reusing the same card component T-5C01 uses — no page-specific card or query.

### [T-5D01] Public blog — listing and article detail

- **Spec:** l1-content-publishing.md §3.2, §5.1, §5.3
- **Status:** Todo
- **Assignment:** Agent
- **Requires:** T-5A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PublicBlog` proves the blog listing renders published articles only (never a draft), each carrying its category and tags; an article page renders its full body, author, category, tags, and its many-to-many related objects and territories, each rendered as a real link; an unpublished or future-scheduled article's detail route is unreachable publicly.
- **Handoff:** T-5D03 (home page's articles rail links here).
- **Notes:** Articles already exist as an Eloquent model with categories and tags (Phase 3) — this task is the public rendering surface only, no new authoring or moderation logic.

### [T-5D02] Public news feed and promotions section

- **Spec:** l1-content-publishing.md §3.3, §3.4, §5.1, §5.3
- **Status:** Todo
- **Assignment:** Agent
- **Requires:** T-5A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PublicNewsAndPromotions` proves the portal-wide news feed lists published, non-withdrawn items (an item past its own end date is excluded from the feed but its own page stays reachable, per the spec's own withdrawn-from-feeds-page-retained distinction); the promotions section lists only promotions whose end date has not yet passed, proven by seeding one elapsed promotion and asserting its absence from this section specifically (its own archival is a scheduled job this task does not own, only reads the resulting state); pinned news items sort first within the feed.
- **Handoff:** T-5D03 (home page's news and promotions rails link here).
- **Notes:** Promotion archival is a scheduled job that already exists (Phase 3) — this task reads `status`/`moderation_status` as already maintained; it does not implement the archival transition itself.

### [T-5D03] Home page composition

- **Spec:** l1-home-page.md §3, §5.1, §5.2, §5.3, §5.4
- **Status:** Todo
- **Assignment:** Agent
- **Requires:** T-5A01, T-5A03, T-5A05, T-5A06, T-5D01, T-5D02
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PublicHome` proves all sixteen blocks render in composition order for a fixture with content in every block, and each block is omitted entirely (never an empty frame) for a fixture with nothing to show it; the recommended/best and newest-objects blocks are tier-ordered through T-5A03, falsified the same way T-5C01 is; a visitor with a selected country sees that country's own curated destinations, cities, and content, proven against two country fixtures; the page resolves in one server pass, asserted by a query-count ceiling on this task's own render, not deferred to T-5T03.
- **Handoff:** T-5T01, T-5T02, T-5T03.
- **Notes:** The home page owns no domain data (§3) — every block is a view onto another spec's data via the components Tracks A/B/C/D already built. A home-page-only card, rail, or query is a defect, not a shortcut (Implementation Note #1). Curated block selections (destinations, cities, categories, informational copy, partners) are per-country back-office data this task reads, not authors — if no curation UI exists yet for a given block, seed the fixture directly in this task's own tests rather than blocking on a back-office task this phase does not include.

### [T-5T01] Placement-tier ordering invariant across every public listing surface

- **Goal:** Verify the tier-first ordering contract `T-5A03` establishes holds on every surface that renders a listing, not spot-checked on the catalog page alone.
- **Method:** `docker compose exec app ./vendor/bin/pest --group=slow --filter=PublicTierOrderingInvariant` — for every public surface built in this phase that lists objects (catalog, each territory-page catalog block, home page's recommended/newest/map blocks, object profile's nearby/similar block), seed a fixed cross-tier distribution and assert the rendered order matches `T-5A03`'s own contract exactly, falsified by temporarily reordering one surface's query and confirming the corresponding assertion fails, then restoring.
- **Status:** Todo
- **Requires:** T-5A03, T-5B04, T-5C01, T-5C02, T-5D03

### [T-5T02] Event-emission invariant — never blocks, never fails visibly

- **Goal:** Verify l1-analytics.md §3.2's fidelity invariants hold for real, not merely that an event row appears when nothing goes wrong: measurement never delays or blocks the interaction it measures, and a measurement failure is never a user-visible failure.
- **Method:** `docker compose exec app ./vendor/bin/pest --filter=PublicEventEmissionInvariant` — proves a contact-click navigation completes even when the underlying capture call is forced to throw (container rebind to a throwing implementation, the same falsification technique already established for `AvailabilityToggleService` in Phase 4); proves card-view, page-view, photo-view, and contact-click each reach `EventCaptureService` exactly once per genuine interaction, not zero and not duplicated; proves no analytics write is synchronous on the request that triggers it (asserted against the existing fire-and-forget/queued shape, not a new mechanism).
- **Status:** Todo
- **Requires:** T-5A05, T-5B01, T-5B02

### [T-5T03] Public performance budget under seeded volume

- **Goal:** Verify the public site's hottest pages meet this project's own measured budgets (catalog/territory cache hit < 100 ms TTFB, cache miss < 400 ms; object page cache miss < 300 ms; any single request ≤ 30 queries) at realistic volume, and that this phase's caching (per scope/filters/sort/page/language/country) actually holds — not merely that a small fixture set happens to render fast.
- **Method:** `docker compose exec app ./vendor/bin/pest --group=slow --filter=PublicPerformanceBudget` — seeds realistic per-territory object volume (hundreds, not the dozen-object fixtures individual task tests use) across a representative country/territory tree, measures TTFB and query count for the catalog page, a territory page, and an object page, both cache-hit and cache-miss, and reports actual figures the same way `T-4T03` does.
- **Status:** Todo
- **Requires:** T-5A01, T-5A03, T-5A05, T-5A06, T-5B01, T-5B02, T-5C01, T-5C02, T-5D03
