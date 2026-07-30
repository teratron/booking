# Hotel Profile

**Version:** 0.2.0
**Status:** Draft
**Layer:** concept

## Overview

The hotel detail page a discovery result links to: gallery, amenities, location,
on-site services, editorial news tied to the hotel, guest reviews, and a
recently-viewed rail. Evidenced by Figma frames `страница отеля` /
`страница отеля моб`.

## Related Specifications

- [l1-platform-foundation.md](l1-platform-foundation.md) - Discoverability, hotel/room hierarchy, and media-resilience invariants.
- [l1-hotel-discovery.md](l1-hotel-discovery.md) - Upstream entry point into this page.
- [l1-room-reservation.md](l1-room-reservation.md) - The room inventory table embedded in this page.
- [l1-content-publishing.md](l1-content-publishing.md) - Source of the on-page "hotel news" section.
- [l2-third-party-integrations.md](l2-third-party-integrations.md) - Resolves who may author a review (§2).

## 1. Motivation

The hotel profile is the conversion surface: everything a guest needs to decide
between "view rooms" and "back to catalog" lives here. It aggregates data owned by
three other specs (rooms, reviews, news) into one page, so its own scope is the
aggregation and presentation, not the underlying data mechanics.

## 2. Constraints & Assumptions

- Reviews display an aggregate score plus a per-review breakdown (reviewer name,
  avatar, individual rating, free-text comment, date).
  [MODIFIED] **Resolved**: a review is authored by an authenticated guest
  (see [l2-third-party-integrations.md](l2-third-party-integrations.md) §5.1)
  and passes the same moderation checkpoint as other external content (see
  [l1-platform-foundation.md](l1-platform-foundation.md) §3).
  <!-- TBD: whether a guest must additionally have a completed reservation at
       this specific hotel to review it (vs. any authenticated guest) is a
       separate business-policy question, not yet decided. -->
- The on-site restaurant section is presented as an amenity of the hotel, not a
  separately bookable entity.

## 3. Core Invariants (Layer 1 only)

- The page renders: photo gallery, name, location, aggregate star rating,
  amenity badges, embedded location map with nearby points of interest, the
  hotel's room inventory (summary; detail owned by
  [l1-room-reservation.md](l1-room-reservation.md)), an on-site-services section,
  a hotel-scoped news feed, guest reviews with aggregate + itemized display, and
  a "recently viewed hotels" rail.
- Every section degrades independently: a hotel with zero reviews, zero news
  items, or a partial gallery must still render a usable page (no section may be
  a hard dependency for page render).
- The recently-viewed rail is scoped to the visiting browser/session, not to an
  authenticated account — this stays true independent of the actor-role
  resolution, since browsing does not require an account
  ([l2-third-party-integrations.md](l2-third-party-integrations.md) only gates
  reservations, reviews, and hotel submission, not browsing).

## 5. Detailed Design

### 5.1 Page Composition

```plaintext
Gallery
Name + Location + Rating + Amenity badges
Room inventory (summary) -> l1-room-reservation
Location map + nearby points of interest
On-site services (e.g. restaurant)
Hotel news feed -> l1-content-publishing
Guest reviews (aggregate + itemized)
Recently viewed hotels
```

## 6. Implementation Notes

1. Room inventory summary and the reservation popup are one spec
   ([l1-room-reservation.md](l1-room-reservation.md)); do not duplicate the room
   data contract here.
2. News-feed rendering should reuse the article component from
   [l1-content-publishing.md](l1-content-publishing.md) rather than a bespoke one.

## 7. Drawbacks & Alternatives

Reviews could instead be modeled as their own top-level domain (with a dedicated
moderation queue). Kept folded into this spec for now since no review-submission
frame exists yet to justify the split; revisit if a submission flow is designed.

## Canonical References

| Alias | Path | Purpose |
| --- | --- | --- |
| `[FIGMA-HOTEL]` | `https://www.figma.com/design/N2cVVIS5wvjHIviP27peuX/Booking?node-id=85-2` | Hotel profile frame (desktop). |
| `[FIGMA-HOTEL-MOBILE]` | `https://www.figma.com/design/N2cVVIS5wvjHIviP27peuX/Booking?node-id=85-854` | Hotel profile frame (mobile). |

## Document History

| Version | Date | Change |
| --- | --- | --- |
| 0.1.0 | 2026-07-30 | Initial draft derived from Figma hotel-page frames. |
| 0.2.0 | 2026-07-30 | Resolved review-authorship (authenticated guest) via l2-third-party-integrations.md. |
