---
phase: 5
name: "Hotel Profile & Content Publishing"
status: Done
subsystem: "src/lib/hotel-profile/, src/lib/content/, src/app/(marketing)/hotel/[id]/, src/app/(marketing)/blog/"
requires:
  - "Phase 1 — Platform Foundation (hotel/room/amenity/review/article schema, moderation status column)"
  - "Phase 2 — Identity & Back Office (article admin resource, authenticated actor resolution)"
  - "Phase 3 — Property Onboarding (hotel/room data owners actually submit)"
  - "Phase 4 — Discovery & Catalog (result cards already link to /hotel/{id}; this phase is the first to make that route resolve)"
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 5 Tasks — Hotel Profile & Content Publishing

**Phase:** 5
**Status:** Done
**Strategic Goal:** A guest who reaches `/hotel/{id}` from a Catalog/Home result
card lands on a real conversion page — gallery, amenities, room summary, map,
on-site services, hotel-scoped news, and reviews, every section degrading
independently — and can separately browse/read the editorial blog that feeds
that news section, per `l1-hotel-profile.md` and `l1-content-publishing.md`.

## Track Structure

Honest execution shape: `(A01 ‖ A02) → (B01 ‖ C01) → B02 → T`.

- **Track A** (the two query modules — hotel-profile aggregation, article/blog)
  are mutually file-disjoint and have no dependency on each other; both only
  read tables Phase 1 already created (`hotel`, `room`, `review`, `article`,
  `amenity`/`hotelAmenity`), so both start immediately.
- **B01** (hotel profile page: gallery, header, room summary, map, on-site
  services) needs only `A02`. **C01** (blog listing + article detail + the
  shared `ArticleCard` component) needs only `A01`. They touch entirely
  different route trees (`hotel/[id]/` vs `blog/`), so they run in parallel.
- **B02** (hotel news feed + reviews + recently-viewed rail) is the one task
  with a real cross-track dependency: `l1-hotel-profile.md`'s own
  Implementation Note says the news section must reuse `C01`'s article
  component rather than a bespoke one, and it also lands in the same
  `hotel/[id]/page.tsx` file `B01` builds — genuinely sequenced after both,
  not parallel-eligible with either.

## Decisions Carried Into Decomposition

<!-- Elective forks resolved here per magic.md §7 (DA-8/DA-9) — narrated, not asked. -->

- [DR] The room-inventory summary on the hotel profile renders informational
  cards only (thumbnail, name, price-from, guest capacity) with no functional
  "book"/detail interaction wired yet. Criterion: `l1-hotel-profile.md` §6
  explicitly states "Room inventory summary and the reservation popup are one
  spec [`l1-room-reservation.md`]... do not duplicate the room data contract
  here" — that popup is Phase 6's undecomposed scope, so wiring a click target
  now would either dead-end or require building Phase 6 early. Matches this
  project's own established forward-reference pattern: Phase 4's `ResultCard`
  already linked to `/hotel/{id}` before this phase existed to resolve it.
  (Override: `/magic.task` on Phase 6 to build the popup; this task's cards
  become its click targets without needing to change here.)
- [DR] The "location map + nearby points of interest" section renders only the
  hotel's own pin (reusing T-4C03's Leaflet/OpenStreetMap infrastructure) —
  no synthetic or fetched POI markers. Criterion: no POI data source exists
  anywhere in this project's spec set — no POI table in the entity model, no
  third-party places/POI API registered in `l2-tech-stack.md` or
  `l2-third-party-integrations.md`. Inventing fake POI pins would misrepresent
  placeholder data as real; wiring an unspecified external API (e.g.
  Overpass) is a tech-stack decision outside this task's authority. (Override:
  `/magic.spec` to register a POI provider in `l2-tech-stack.md` if real
  nearby-POI data becomes a real requirement.)
- [DR] Guest review **submission** (a guest posting a new review) is out of
  this phase's scope — only review **display** (aggregate + itemized) is a
  stated Core Invariant. Criterion: `l1-hotel-profile.md`'s Core Invariants
  list only display behavior; its own Constraints section flags
  reservation-required-to-review as an explicit unresolved TBD, and no Figma
  evidence or spec section describes a submission form. Review rows remain
  administratively seedable via the `review` admin resource Phase 2 already
  built (`src/app/admin/App.tsx`). (Override: `/magic.spec` to resolve the
  TBD and add a submission-flow section once that policy question is
  answered — likely coupled to Phase 6's reservation model, since "did this
  guest actually stay here" is a reservation-table question.)
- [DR] Article **authoring** (creating/editing blog posts and hotel news) is
  not part of this phase — only the public read side (listing, detail, hotel
  embed). Criterion: `l1-content-publishing.md` §2 resolves authorship to
  "platform admins... through the admin panel", and that panel's generic
  `article` resource (list/show/edit via react-admin guessers) already exists
  from Phase 2's admin scaffold. No new authoring UI is specified anywhere.
- [DR] The recently-viewed rail persists via browser `localStorage`, not a
  server-side session/table. Criterion: `l1-hotel-profile.md`'s own Core
  Invariant states this "stays scoped to the visiting browser/session, not an
  authenticated account... independent of actor-role resolution" — a
  client-only mechanism is the direct reading; no schema is warranted for
  what the spec itself frames as browser-local state.

## Atomic Checklist

- [x] [T-5A01] Article/blog query module — listing, detail, hotel-scoped news
- [x] [T-5A02] Hotel profile aggregation query — gallery, amenities, room summary, reviews
- [x] [T-5B01] Hotel profile page — gallery, header, room summary, map, on-site services
- [x] [T-5C01] Blog listing + article detail pages, shared ArticleCard component
- [x] [T-5B02] Hotel news feed, guest reviews, and recently-viewed rail
- [x] [T-5T01] Validate independent section degradation and the moderation checkpoint

## Detailed Tracking

### [T-5A01] Article/blog query module — listing, detail, hotel-scoped news

- **Spec:** l1-content-publishing.md §3 Core Invariants, §5.1 Content Relationship
- **Status:** Done
- **Assignment:** Agent
- **Verify:** integration test — `getArticles({page})` paginates and orders by `publishedAt` descending; `getArticleById` returns a single article or `undefined` for a missing id; `getHotelNews(hotelId)` returns only articles associated with that hotel, excluding both unrelated articles and articles belonging to a different hotel, proven with a 2-hotel fixture.
- **Handoff:** C01 (blog pages) and B02 (hotel news embed) both call this module.
- **Changes:**
  Added `src/lib/content/article-query.ts` — `getArticles(page)` (paginated, `desc(publishedAt)`), `getArticleById(id)` (full body or `undefined`), `getHotelNews(hotelId, limit=3)` (scoped, most-recent-first, empty array for a hotel with none). No `status` filter anywhere — confirmed against the `article` table's actual columns (no moderation spread, unlike `hotel`/`room`/`review`) rather than assuming symmetry with those tables.
  Added `article-query.test.ts` — 3 tests with a 3-article/2-hotel fixture (one hotel-scoped article per hotel plus one global post, distinct `publishedAt` timestamps to make ordering assertions deterministic): pagination metadata + newest-first ordering across all three; `getArticleById` for a real id and a well-formed all-zero UUID; `getHotelNews` scoped correctly to each hotel including the zero-news case.
  Verified: `tsc --noEmit` clean; `pnpm test` 43 files/115 tests green (dev server stopped beforehand); `pnpm exec biome check .` clean; `fallow audit --format json` — `verdict: "pass"`, `dead_code_introduced: 0` (both this task's exports and T-5A02's are already consumed by their own test files, so nothing shows dead yet despite no page/component wiring them in — B01/C01/B02 haven't been built).
- **Notes:** `src/lib/content/article-query.ts`. Articles skip the moderation checkpoint entirely (admin-authored, per l1-content-publishing.md §3's own resolved invariant) — do not add a `status` filter that doesn't exist on the `article` table.

### [T-5A02] Hotel profile aggregation query — gallery, amenities, room summary, reviews

- **Spec:** l1-hotel-profile.md §3 Core Invariants, §5.1 Page Composition
- **Status:** Done
- **Assignment:** Agent
- **Verify:** integration test — a `published` hotel returns its full gallery/amenities/room-summary/aggregate-rating/itemized-reviews shape; a `pending` or `rejected` hotel (or a missing id) returns `undefined`, proving the moderation checkpoint applies to profile lookups exactly as it does to catalog results (T-4A01's own invariant, restated here for a single-hotel fetch); a hotel with zero reviews, zero media, and zero amenities still returns a well-formed (empty-array, not throwing) shape — the data-layer half of §3's "every section degrades independently" invariant.
- **Handoff:** B01 (page header/gallery/rooms/services) and B02 (reviews) both consume this.
- **Changes:**
  Added `src/lib/hotel-profile/hotel-profile-query.ts` — `getHotelProfile(hotelId)` fetches the hotel row (`status: "published"` guard, `undefined` otherwise), then gallery/amenities/rooms/reviews in parallel via `Promise.all`, then room cover photos in a follow-up query keyed by the fetched room ids. Amenities are fetched via the `hotelAmenity` join unfiltered by `group` — matches `catalog-query.ts`'s own `amenityIdsCondition` precedent (the taxonomy's `group` column distinguishes hotel- vs room-level attachment, not a sub-category worth filtering here). Reviews join `user` for `guestName`/`guestAvatar`; `avgRating`/`reviewCount` are computed from the same fetched rows rather than a second aggregate query.
  **Bug caught before it shipped**: the first draft filtered `roomMedia` by `eq(roomMedia.type, "photo")`, copying `hotelMedia`'s shape — but `roomMedia` (unlike `hotelMedia`) has no `type` column at all (confirmed by reading `db/schema.ts` directly rather than assuming symmetry between the two media tables). Caught by `tsc --noEmit` before ever reaching a test run.
  Added `hotel-profile-query.test.ts` — 3 tests: a full fixture (gallery photo, amenity, one published room with a cover photo, one published review) asserts every field, including `basePrice` being coerced from the numeric column's string representation to a `number`; a minimal fixture (hotel with nothing else) asserts every array field is `[]` and `avgRating` is `null`, not a thrown error; `pending`/`rejected`/missing-id all resolve to `undefined`.
  Verified: `tsc --noEmit` clean; `pnpm test` 43 files/115 tests green (twice in a row, dev server stopped beforehand); `pnpm exec biome check .` clean; `fallow audit --format json` — `verdict: "pass"`, `dead_code_introduced: 0`.
  **Real cross-file regression found and fixed while re-running the full suite (not this task's own code, but surfaced by it running alongside the existing suite)**: `catalog-query.test.ts`'s `testAmenityId` (picked via an unordered `.limit(1)`) collided with `catalog-contract.test.ts`'s (T-4T01) own identical unordered pick — both resolved to the same physical row, so T-4T01's 13 globally-visible hotels leaked into `catalog-query.test.ts`'s unscoped `amenityIds` exact-equality assertion. A second, independent collision: T-4T01's fixture room prices (100–112) sat between `catalog-query.test.ts`'s own fixtures (Cozy Hostel 30, Grand Palace 200), pushing Grand Palace off page 1 of an unfiltered price-ascending sort. Fixed both at the source: `catalog-contract.test.ts` now picks its amenity via `orderBy(desc(amenity.name))` (deterministically different from the other file's unordered pick) and prices its fixture hotels from a 500+ base (clear of the `[0, 200]` band the other file's fixtures span); `catalog-query.test.ts`'s `amenityIds` test also gained a `destination: "Kyiv"` scope, matching the same fix already applied to its `price range` test in T-4C01, so it's immune to *any* future file's hotel sharing that amenity id, not just this specific collision. Both bugs were latent since T-4T01 was written — this task's own new test file was simply the trigger that finally ran the full suite with T-4T01's fixture present at meaningful scale.
- **Notes:** `src/lib/hotel-profile/hotel-profile-query.ts`. Reuses `catalog-query.ts`'s aggregate-rating SQL shape (published reviews only) rather than reinventing it — same underlying invariant, single-hotel scope instead of a filtered set.

### [T-5B01] Hotel profile page — gallery, header, room summary, map, on-site services

- **Spec:** l1-hotel-profile.md §3 Core Invariants, §5.1 (Gallery / Name+Location+Rating+Amenities / Room inventory / Location map / On-site services)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** component test — a mocked profile with zero gallery photos and zero amenities still renders the name/location/rating header (no section is a hard render dependency, per §3); the map renders exactly one pin at the hotel's own coordinates (reusing T-4C03's mocked-`react-leaflet` test pattern); a `not found` result (per T-5A02's moderation-checkpoint contract) renders Next's `notFound()` state rather than a broken page.
- **Handoff:** B02 appends the remaining sections to this same page.
- **Changes:**
  **Shared-component extraction, before the page itself**: this task needed the exact same Leaflet/OpenStreetMap rendering T-4C03 built for Catalog (a `MapPin[]` → markers-with-popups component), just with one pin instead of many. Rather than duplicate it under `hotel/[id]/`, extracted T-4C03's `catalog-map.tsx`/`catalog-map-loader.tsx` out of `catalog/` into `src/components/leaflet-map.tsx`/`leaflet-map-loader.tsx` — renamed `CatalogMap`→`LeafletMap` and its prop type from the catalog-specific `CatalogMapPin` to a generic `MapPin`, added optional `center`/`zoom` props (Catalog keeps its Ukraine-wide default view; the profile page centers tightly, `zoom={14}`, on the hotel's own coordinates). `catalog/page.tsx` and its test now import the shared component; the old `catalog-map*` files are deleted, not duplicated. Added the standard `biome.json`/`.fallowrc.jsonc` vendor-boundary negation entries for the three new `src/components/` files (per the documented Blocking Constraint).
  **A second instance of the react-day-picker/date-fns false-positive class, this time on a negated file**: after the extraction, `fallow audit` flagged `leaflet`/`react-leaflet` as unused dependencies — surprising, since (unlike the `date-fns` case) `leaflet-map.tsx` genuinely is negated/in-scope and imports both at the value level, confirmed by `tsc`, by `leaflet-map.test.tsx`, and by a live request. Added both to `.fallowrc.jsonc`'s `ignoreDependencies`, documented as a narrower instance of the same tool limitation rather than a re-derivation of the `calendar.tsx` case.
  Added `src/app/(marketing)/hotel/[id]/page.tsx` — calls `getHotelProfile(id)`, `notFound()` if `undefined`. Renders, in spec order: gallery grid (skipped if empty); header (name, star category, aggregate rating-or-"no reviews" via the shared `ResultCard` namespace, up to 6 amenity badges); room summary cards (skipped if empty — per this phase's own `[DR]`, informational only, no booking CTA); the location map (always renders, a single pin, reusing `LeafletMapLoader`); on-site services (the full amenity list, skipped if empty — same `amenities` array the header badges use, just un-truncated). `guestsLabel` uses a fixed `"{count} persons"` form (an invariant abbreviation) rather than Russian's real plural-agreement rules, which would require three distinct grammatical forms of the word "guest" depending on the count. This is the same pragmatic simplification T-4C01 already established for `resultsCount`, and the abbreviated form is also the standard real-world Russian convention for capacity displays, so it is not merely an approximation but the idiomatic phrasing.
  Added `page.test.tsx` — 3 tests: `notFound()` is called (and the mock made it throw, matching Next's real behavior) for an `undefined` profile; a full fixture renders every section including the map-loader stand-in; a fully-empty fixture (zero gallery/amenities/rooms/reviews) still renders the header and map, proving §3's degradation invariant at the render layer (T-5A02 already proved it at the data layer).
  Verified: `tsc --noEmit` clean; `pnpm test` 44 files/118 tests green (dev server stopped beforehand); `pnpm exec biome check .` clean (import-order + formatting fixes via `--write`); `fallow audit --format json` — `verdict: "pass"` after the `ignoreDependencies` fix, `dead_code_introduced: 0`.
  **Live dev-server check surfaced a real (non-code) bug and its fix**: seeded one temporary published hotel directly via `psql`, requested `/hotel/{id}` — got a `404` even though a standalone script calling `getHotelProfile` directly against the same database returned the correct profile. Isolated to a **stale Turbopack dev cache** left over from the `catalog-map*` → `leaflet-map*` file move earlier in this same task: `rm -rf .next` + a clean `pnpm dev` restart resolved it immediately, confirmed by re-requesting both the real hotel (`200`, correct content) and a missing one (`404`, via `notFound()`) with zero errors in the log. Not a code defect, but exactly the class of issue this project's live-dev-server-check practice (T-3B02's original rationale) exists to catch — a file-move-triggered cache staleness wouldn't surface in `tsc` or Vitest, both of which run against source files directly, never through Turbopack's incremental compiler.
- **Notes:** `src/app/(marketing)/hotel/[id]/page.tsx` — the route Phase 4's `ResultCard`/map-pin popup already link to. On-site services reuses the hotel-level amenity rows T-5A02 already fetches — no separate query.

### [T-5C01] Blog listing + article detail pages, shared ArticleCard component

- **Spec:** l1-content-publishing.md §3 Core Invariants, §6 Implementation Notes
- **Status:** Done
- **Assignment:** Agent
- **Verify:** component test — the listing page renders each article's cover image/title/summary/date via `ArticleCard`; the detail page renders the full `content` body for a given id; both routes render server-side (no client-only data fetch) so they satisfy the discoverability invariant l1-platform-foundation.md requires, verified the same way T-4B01/T-4C01 verified their Server Component pages (`await Page()` + render, no `useEffect` fetch).
- **Handoff:** B02 imports `ArticleCard` for the hotel-news embed rather than redefining it.
- **Changes:**
  Added `src/app/(marketing)/blog/article-card.tsx` (`ArticleCard`, plus an exported `formatArticleDate` helper both the card and the detail page use), `blog/page.tsx` (listing, `?page=` query param, same Prev/page-indicator/Next pattern T-4C01 established), `blog/[id]/page.tsx` (detail — cover image, title, date, `content` rendered `whitespace-pre-wrap` since it's a plain text column, not rich HTML). Placed under `src/app/(marketing)/blog/`, not `src/components/`, per the same reasoning T-4C02's filter sidebar and T-5B01's Notes already established — its only other caller (B02) can import across the route-group boundary, avoiding the vendor-boundary negation-list tax for a component with exactly two consumers. Added a `"Blog"` translation namespace with its own `previousPageLabel`/`nextPageLabel`/`pageIndicator` rather than reusing `"Catalog"`'s identical-looking keys — three short duplicated strings across two independent pages is a milder DRY case than `ResultCard`'s (a genuinely shared *component* needing non-page-specific strings), so duplicating here stays within CLAUDE.md's own "three similar lines over premature abstraction" guidance rather than coupling two unrelated pages' namespaces together.
  Added `page.test.tsx` for both routes (mocked `getArticles`/`getArticleById`, same pattern as every other page test this phase/session) — listing: page param reaches `getArticles`, empty state, pagination hidden/shown with correct hrefs; detail: `notFound()` for a missing id, full content render for a real one.
  Verified: `tsc --noEmit` clean; `pnpm test` 46 files/123 tests green (dev server stopped beforehand); `pnpm exec biome check .` clean (one formatting fix via `--write`); `fallow audit --format json` — `verdict: "pass"`, `dead_code_introduced: 0`.
  **Real finding from the live dev-server check — a genuine gap, not a code defect**: seeding a temporary article with an `example.test` cover-image URL for the live check produced a `500` on both `/blog` and `/blog/[id]` — `next/image`'s `remotePatterns` allowlist (`next.config.ts`) only covers `**.public.blob.vercel-storage.com` (the hotel/room media upload host, T-3B01). Re-pointing the fixture at an allowlist-matching URL confirmed the pages themselves render correctly (`200`, correct content, no other errors) — this was the *first* live check all session to actually exercise `next/image` against a concrete non-empty URL (every prior Home/Catalog/Hotel-Profile live check happened to use fixtures with no cover photo, so the conditional `{url ? <Image/> : null}` guard never got exercised with a real URL until now). The underlying gap is real, though: hotel/room owners are UI-constrained to the Blob upload widget (T-3B01), so their media URLs are always allowlist-safe, but admin article authoring goes through Phase 2's generic react-admin `CreateGuesser`/`EditGuesser` — a plain text input for `coverImage` with no upload flow or host validation, so an admin genuinely *can* type an arbitrary URL and 500 the public blog. This is a pre-existing gap in Phase 2's admin scaffold, not something T-5C01 introduced, and article authoring is explicitly out of this phase's scope per its own `[DR]` — flagging it here as a follow-up worth a `/magic.spec` note (either broaden `remotePatterns` deliberately, or give the admin article form a real upload widget) rather than silently fixing or silently ignoring it.
- **Notes:** `src/app/(marketing)/blog/page.tsx`, `src/app/(marketing)/blog/[id]/page.tsx`, shared `ArticleCard` component — place it under `src/app/(marketing)/blog/` (not `src/components/`) to sidestep that directory's documented vendor-boundary negation-list tax, same reasoning T-4C02's filter sidebar already established, since B02 is its only other caller and can import across the route-group boundary.

### [T-5B02] Hotel news feed, guest reviews, and recently-viewed rail

- **Spec:** l1-hotel-profile.md §3 Core Invariants (news feed, reviews, recently-viewed), §6 Implementation Note #2
- **Status:** Done
- **Assignment:** Agent
- **Verify:** component test — the news section renders `T-5C01`'s `ArticleCard` (not a bespoke card) for each article `getHotelNews` returns, and renders nothing (not an error) for zero hotel news; reviews render an aggregate score plus each itemized review's reviewer name/rating/comment/date; the recently-viewed rail reads/writes a `localStorage` key on the client and excludes the currently-viewed hotel from its own list.
- **Handoff:** feeds T-5T01's full page validation.
- **Changes:**
  **Shared-helper extraction, before the wiring**: reviews needed the same date formatting `ArticleCard`'s `formatArticleDate` already did — rather than importing a blog-named helper into an unrelated hotel page, extracted it to `src/lib/format-date.ts` (`formatDate`) and updated both `article-card.tsx` and `blog/[id]/page.tsx` to use the shared location. A small, second-consumer-triggered DRY move, same reasoning as T-5B01's `leaflet-map` extraction.
  Added `src/app/(marketing)/hotel/[id]/recently-viewed.tsx` (`RecentlyViewedRail`, `"use client"` — the one client piece of this otherwise server-rendered page) — on mount, reads `booking:recently-viewed-hotels` from `localStorage`, filters out the current hotel, renders the rest as pill links, then writes `[currentHotel, ...rest].slice(0, 6)` back. No section renders (returns `null`) when there's nothing else to show.
  Wired `getHotelNews(id)` into `hotel/[id]/page.tsx` (fetched via the same `Promise.all` as `getHotelProfile`, since both only need the raw route `id`) and appended News/Reviews/RecentlyViewed sections, reusing `T-5A02`'s already-fetched `profile.reviews`/`avgRating`/`reviewCount` for the reviews section — no new query needed there. News reuses `T-5C01`'s `ArticleCard` via a cross-route-group import (`@/app/(marketing)/blog/article-card`).
  **Complexity threshold hit, same pattern as T-3A02/T-4A01**: `fallow audit` flagged `HotelProfilePage` at CRAP 31.6 (cyclomatic 10, threshold 30) — six inline conditional sections had accumulated across T-5B01 and this task. Extracted each into its own small named component in the same file (`Gallery`, `Header`, `RoomsSection`, `ServicesSection`, `NewsSection`, `ReviewsSection`), each owning its own empty-guard; the page body itself became a flat sequence of component calls. Same "extract, don't just pad coverage" precedent this session has followed every time this threshold has come up, rather than the alternative fallow itself suggests (add more branch-coverage tests to dilute the CRAP score without actually reducing complexity).
  Added tests to `page.test.tsx` (news via `ArticleCard`, itemized reviews, recently-viewed excluding the current hotel from pre-seeded `localStorage`) and a dedicated `recently-viewed.test.tsx` (3 tests: renders nothing with no other hotel; writes the current hotel to `localStorage` on mount while excluding it from its own rendered list; renders a previously-stored other hotel with the correct link).
  Verified: `tsc --noEmit` clean; `pnpm test` 47 files/128 tests green (dev server stopped beforehand); `pnpm exec biome check .` clean; `fallow audit --format json` — `verdict: "pass"` after the complexity extraction, all `gate: "new-only"` categories zero.
  **Fixture-tooling lesson, not a code bug**: the live-check's first fixture-seeding attempt used a single `docker exec psql <<'EOF' ... EOF` heredoc with five statements — it reported success (`echo done` ran) but silently inserted *zero* rows; a follow-up `SELECT` confirmed the table was empty. Switched to five separate `docker exec psql -c "..."` calls (the pattern every earlier live check this session already used successfully) and all five inserted correctly. Worth remembering: this project's psql-via-`docker exec` pattern needs one `-c` per statement — multi-statement heredocs through this exact invocation chain don't reliably surface partial failure. Verified thereafter: `GET /hotel/{id}` → `200` for a hotel with a real review and real hotel-scoped news, both rendered correctly, zero errors in the dev log.
- **Notes:** Appends into `hotel/[id]/page.tsx` (`T-5B01`'s file) rather than a separate route. The recently-viewed rail is the one client-side (`"use client"`) piece of this otherwise server-rendered page — write the viewed-hotel id to `localStorage` on mount, read the existing list to render the rail.

### [T-5T01] Validate independent section degradation and the moderation checkpoint

- **Goal:** Prove `l1-hotel-profile.md` §3's "every section degrades independently" invariant holds for the fully-assembled page (not just at the data layer, per T-5A02's own narrower proof), and that a non-published hotel's profile is genuinely unreachable — mirroring T-2T01/T-3T01/T-4T01's role as this phase's exit gate.
- **Method:** One integration test driving: a hotel with a full gallery/amenities/rooms/reviews/news fixture renders every section; a second, minimal hotel with zero media/amenities/reviews/news still renders a usable page (no thrown error, no missing header); a `pending` and a `rejected` hotel id both resolve to a not-found state at the route level, not just at the query layer. Then the full quality gate: `pnpm test`, `pnpm exec biome check .`, `pnpm exec tsc --noEmit`, `pnpm exec fallow audit --format json` (expect the `gate: "new-only"` attribution clean — `dead_code_introduced`/`complexity_introduced`/`duplication_introduced`/`circular_dependencies`/`boundary_violations` all zero, per this phase's own T-4T01 finding that the raw top-level `verdict` field tracks total repo debt, not this session's actual gate), plus a live dev-server check of `/hotel/{id}` and `/blog` (this project's standing constraint: stop the dev server before `pnpm test`, and a live request is the only thing that reliably catches a Client Component pulling in a browser-only package cleanly under `tsc`/Vitest but not in a real request — T-3B02/T-4C03's precedent).
- **Status:** Done
- **Assignment:** Agent
- **Changes:**
  Added `src/app/(marketing)/hotel/[id]/hotel-profile-contract.test.tsx` — 4 tests, distinct from every prior task's own tests in this phase (each of which mocked `getHotelProfile`/`getHotelNews` to prove *the page's own logic*): this file exercises the real query modules against a real database, proving the actual route → query → render chain. Every query is scoped to its own hotel id (never unfiltered), so unlike the earlier catalog-side exit gate this one carries zero cross-file Postgres fixture-visibility risk by construction. (1) A fully-populated hotel (gallery photo, amenity, published room with cover photo, published review, hotel-scoped article) renders every section — Rooms/Services/News/Reviews headings, the room name, the review comment, the map. (2) A minimal published hotel with zero everything still renders a usable header and map, with all four optional sections' headings absent — the render-layer half of §3's degradation invariant, complementing T-5A02's data-layer proof. (3)/(4) A `pending` and a `rejected` hotel id both throw via `notFound()` when passed directly to the real page component — the moderation checkpoint holds at the route level, not just inside `getHotelProfile`.
  Verified: `tsc --noEmit` clean; `pnpm test` 48 files/132 tests green, run **twice in a row** (dev server stopped beforehand); `pnpm exec biome check .` clean (no fixes needed); `fallow audit --format json` — `verdict: "pass"` (the raw top-level field, not just the `gate: "new-only"` attribution — Phase 5 closes with a genuinely clean audit, same as T-4T01's), `dead_code_introduced: 0`, `complexity_introduced: 0`, `duplication_introduced: 0`, `circular_dependencies: 0`, `boundary_violations: []`; the one pre-existing `styling_introduced: 1` advisory (T-4C02's `grid-cols-[280px_1fr]`) remains, still an accepted one-off carried from Phase 4.
  **Fixture-tooling lesson confirmed, not repeated**: seeded this task's own live-check fixtures using three separate `docker exec psql -c "..."` calls (per T-5B02's finding that a multi-statement heredoc through this exact invocation chain silently inserts nothing) — all three confirmed via their own `INSERT 0 1` output before the live request ran. `GET /hotel/{id}` → `200` with the fixture hotel's name rendered; `GET /blog` → `200` with the fixture article's title rendered; zero errors or warnings in the dev log across both requests.
  Phase 5 is now fully closed: all 6 tasks Done, the shared `leaflet-map`/`leaflet-map-loader` and `article-card`/`format-date` extractions this phase produced are each consumed by 2+ call sites, and two genuine cross-task regressions this phase's own tasks surfaced (the Home page test's latent DB-fixture fragility, found via T-5A02; the `next/image` unconfigured-host gap in article authoring, found via T-5C01's live check) are both documented with clear reasoning for why they were fixed, worked around, or explicitly deferred rather than silently absorbed.
