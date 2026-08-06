# Master Task Index (Registry)

**Version:** 1.3.0
**Generated:** 2026-07-30
**Based on:** .design/main/PLAN.md v1.3.0
**Based on RULES:** .design/RULES.md v1.4.0
**Execution Mode:** Parallel
**Status:** Active

## Overview

Tactical registry of all phases and their statuses. Check individual phase files
in `tasks/` for atomic checklists. Only the active phase is decomposed into atomic
tasks; later phases are decomposed when they become active.

## Active Phases

| Phase | Description | Status |
| --- | --- | --- |
| [Phase 1](tasks/v1-nextjs/phase-1.md) | Project scaffold, complete entity model, and the app shell every route inherits | `Done (Archived)` (14/14) |
| [Phase 2](tasks/v1-nextjs/phase-2.md) | Better Auth identity for guest/owner/admin, plus the react-admin moderation back office | `Done (Archived)` (11/11) |
| [Phase 3](tasks/v1-nextjs/phase-3.md) | Owner-gated Add-Hotel intake flow with moderation lifecycle | `Done (Archived)` (8/8) |
| [Phase 4](tasks/v1-nextjs/phase-4.md) | Home hero search, catalog filters/sort/pagination/map, shared with Home's default list | `Done (Archived)` (7/7) |
| [Phase 5](tasks/v1-nextjs/phase-5.md) | Hotel profile aggregation and the article/news content pipeline | `Done (Archived)` (6/6) |
| [Phase 6](tasks/v1-nextjs/phase-6.md) | Room reservation detail, availability, and the paid booking flow | `Done (Archived)` (6/6) |

Phase 1 ran `A → (B ‖ C) → T` and is summarized in `.design/main/CHANGELOG.md`.

Phase 2 ran `A → B01 → (B02–B03 ‖ C01–C03) → T`. Track A (auth core) was the
critical path — both other tracks imported from it. `T-2B01` (splitting the
marketing chrome into a route group) was sequenced early because Track C's admin
surface must not inherit it *and* because it touches the same layout/header files
Track B does; after it, the auth UI and the back office were genuinely
file-disjoint and ran in parallel.

Phase 3 ran `(A01 ‖ A02 ‖ B01 ‖ C01) → B02 → B03 → C02 → T`. Track A (data +
persistence foundation) and `T-3B01` (upload route) were mutually file-disjoint
and dispatchable immediately; `T-3C01` (owner dashboard) only needed `T-3A02`'s
data model, not the form, so it ran parallel with `T-3B02`/`T-3B03`. See
`archives/tasks/v1-nextjs/phase-3.md` for the full track rationale, including the
`T-3B02`/`T-3B03` split a Planning Audit pass surfaced, and the Server
Action file-split pattern (schema/persistence/actions) T-3B02 established
after a build break only a live dev-server request caught.

Phase 4 ran `(A01 ‖ A02) → (B01 ‖ C01 ‖ C02) → C03 → T`. Track A (the shared
catalog-query contract and the reusable date/guest-count widgets) had no
Phase 3 dependency and started immediately; `T-4C02` (filter sidebar) only
needed the URL-param contract `T-4A01` defines, not its runtime output, so it
ran parallel with `T-4C01` rather than behind it. See `archives/tasks/v1-nextjs/phase-4.md`
for the full track rationale, its four planning-stage `[DR]` resolutions
(Home-as-shared-query, category shortcuts as filter presets not new schema,
Leaflet/OSM as the map provider, and the catalog map plotting the full
filtered set rather than just the current page), and the exit-gate task
(`T-4T01`) that retrofitted a Phase 4B test to a mocked-query pattern after
its own pagination fixture surfaced a real cross-file DB-fixture-visibility
risk.

Phase 5 ran `(A01 ‖ A02) → (B01 ‖ C01) → B02 → T`. Track A (the hotel-profile
aggregation query and the article/blog query module) were mutually
file-disjoint and read only tables Phase 1 already created, so both started
immediately; `B01` (hotel profile page) and `C01` (blog pages) touched
entirely different route trees and only depended on one Track A module each,
so they ran in parallel. `B02` (hotel news + reviews + recently-viewed) was
the one task with a genuine cross-track dependency — `l1-object-profile.md`
requires its news section to reuse `C01`'s article component rather than a
bespoke one — so it was sequenced after both `B01` and `C01`. See
`archives/tasks/v1-nextjs/phase-5.md` for the full track rationale, its five
planning-stage `[DR]` resolutions (room summary renders without a functional
booking CTA since Phase 6 owns that popup, no synthetic nearby-POI data
without a registered provider, review submission and article authoring both
out of scope, and the recently-viewed rail as browser-local `localStorage`
state), two shared-component extractions this phase produced (`leaflet-map`/
`leaflet-map-loader` out of Catalog into `src/components/`, `format-date`
out of the blog article card), and a real gap its own live checks surfaced
in article authoring (`next/image`'s host allowlist has no story for an
admin-entered cover-image URL, since articles have no upload flow the way
hotel/room media do).

Phase 6 ran `A01 → (B01 ‖ D01) → B02 → C01 → T`. Track A (room detail,
availability, and guest-reservations queries) had no dependency on anything
else in this phase and started immediately; `B01` (room detail popup) and
`D01` (guest reservation status page) were mutually file-disjoint and only
depended on `A01`, so they ran in parallel. `B02` (reservation creation)
needed `B01`'s popup to collect room/dates/guests from; `C01` (payment)
needed `B02`'s `pending` reservation to attach a payment attempt to — a
linear checkout funnel from there on, an honest reflection of this phase's
narrower, deeper shape versus Phase 4/5's broader parallel surfaces. See
`archives/tasks/v1-nextjs/phase-6.md` for the full track rationale, its four
planning-stage `[DR]` resolutions (payment built behind a swappable provider
interface with a simulated implementation, since no real Fondy credentials
exist — real Fondy wiring is a later drop-in swap, not a rewrite; guest
reservation status as a dedicated account page, not email; the room popup as
a Base UI Dialog finally wiring Phase 5's intentionally-inert room cards;
availability computed from `paid` reservations only, per the spec's own
"unpaid attempts don't hold dates" invariant), T-6C01's multi-agent
build-then-adversarially-verify pass (8 real findings, all fixed), and
T-6T01's live-browser exit gate, which caught and fixed two real defects no
automated test had (a Base UI `nativeButton` accessibility warning, and a
deterministic same-file test-isolation bug mischaracterized earlier in the
phase as random flakiness) — the full suite closed at 52 files / 158 tests,
zero failures. This was the last phase this project's plan scheduled; all six
are now complete.

## Meta Information

- **Last Updated**: 2026-08-01
- **Maintainer**: Core Team
