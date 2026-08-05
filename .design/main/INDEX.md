# Workspace Specifications Registry

**Version:** 2.1.0
**Status:** Active

## Overview

Local registry of specifications for this workspace.

The specification set was restructured on 2026-08-05 against the client technical
specification (`.drafts/booking.md`), which redefined the product from a hotel
booking marketplace to a multi-country tourism information portal. See
[l1-platform-foundation.md](specifications/l1-platform-foundation.md) §5.3 for the
scope-delta ledger.

All specifications are `RFC` pending review of that restructure — they carry
substantive new requirements and a number of open questions marked inline as `TBD`.

A second, line-by-line pass over `[TZ]` on the same date closed six coverage gaps and
added three specifications: `l1-home-page.md` (§4/§5), `l1-public-api.md` (§19), and
`l2-data-model.md` (§21/§98, including the client approval gate that precedes backend
development). All 134 `[TZ]` sections are cited by at least one specification.

**Stack change (2026-08-05):** the implementation stack was replaced with
**Laravel 13 + Filament 5 + PostgreSQL/PostGIS + Redis**, self-hosted. The 19 Layer-1
specifications are technology-neutral and unaffected; the three Layer-2 documents were
rewritten. The previous Next.js/TypeScript implementation is preserved at git tag
`v0.1.34` and is not a migration source.

Grouping below is editorial (Foundation → Public → Owner/Operator → Commerce →
Optional → Implementation); the registry itself is flat.

## Domain Specifications

| File | Description | Status | Layer | Version |
| --- | --- | --- | --- | --- |
| [l1-platform-foundation.md](specifications/l1-platform-foundation.md) | Foundation. Cross-cutting invariants: delivery, reach, domain, governance, commerce, evolution, privacy; delivery stages | RFC | 1 | 1.4.0 |
| [l1-feature-modules.md](specifications/l1-feature-modules.md) | Foundation. Administrator-toggleable capability modules; scoping ladder, dependencies, inertness, candidate modules | RFC | 1 | 0.2.0 |
| [l1-localization.md](specifications/l1-localization.md) | Foundation. Countries, languages (launch: EN + RU), per-entity translation model, phased activation | RFC | 1 | 0.2.0 |
| [l1-geography.md](specifications/l1-geography.md) | Foundation. Recursive territory hierarchy, per-country level vocabularies, landing pages | RFC | 1 | 0.1.0 |
| [l1-platform-shell.md](specifications/l1-platform-shell.md) | Public. Header, data-driven navigation, language and country switchers, footer, 404, legal pages | RFC | 1 | 0.2.0 |
| [l1-home-page.md](specifications/l1-home-page.md) | Public. Front-page block inventory, data sources, curation, four-viewport behaviour | RFC | 1 | 0.1.0 |
| [l1-object-catalog.md](specifications/l1-object-catalog.md) | Public. Object type registry, search, filters, tier-governed ordering, map | RFC | 1 | 1.1.0 |
| [l1-object-profile.md](specifications/l1-object-profile.md) | Public. Object page; direct-contact conversion contract, rooms, prices, services, reviews | RFC | 1 | 1.1.0 |
| [l1-availability-status.md](specifications/l1-availability-status.md) | Public. Owner-asserted "vacancies available" flag, staleness management | RFC | 1 | 0.2.0 |
| [l1-content-publishing.md](specifications/l1-content-publishing.md) | Public. Articles, news, and promotions; shared publication pipeline | RFC | 1 | 1.0.0 |
| [l1-seo.md](specifications/l1-seo.md) | Public. URL grammar, metadata, indexation policy, structured data, sitemaps, redirects | RFC | 1 | 0.1.1 |
| [l1-object-onboarding.md](specifications/l1-object-onboarding.md) | Owner. Object submission and the full owner cabinet lifecycle | RFC | 1 | 1.2.0 |
| [l1-back-office.md](specifications/l1-back-office.md) | Operator. Portal administration, scoped RBAC, bulk operations, import/export, settings | RFC | 1 | 0.1.0 |
| [l1-moderation-governance.md](specifications/l1-moderation-governance.md) | Operator. Moderation modes and queue, audit journal, soft deletion, confirmation gates | RFC | 1 | 0.1.0 |
| [l1-notifications.md](specifications/l1-notifications.md) | Operator. Notification model, channel adapters, automated schedules, broadcasts | RFC | 1 | 0.1.0 |
| [l1-placement-monetization.md](specifications/l1-placement-monetization.md) | Commerce. Four placement tiers, packages, bump mechanics, expiry, financial ledger | RFC | 1 | 0.1.0 |
| [l1-advertising.md](specifications/l1-advertising.md) | Commerce. Geo/language-targeted banners, slots, scheduling, promotional labels | RFC | 1 | 0.2.0 |
| [l1-analytics.md](specifications/l1-analytics.md) | Commerce. Event model, aggregation, traffic sources, owner and operator reporting, privacy bounds | RFC | 1 | 0.2.0 |
| [l1-public-api.md](specifications/l1-public-api.md) | Integration. Outward-facing REST contract, issued tokens, scoping, rate limits, documentation | RFC | 1 | 0.1.0 |
| [l1-room-reservation.md](specifications/l1-room-reservation.md) | Optional module — **disabled by default**. Booking: calendars, requests, prepaid checkout | RFC | 1 | 1.0.0 |
| [l2-data-model.md](specifications/l2-data-model.md) | Implementation. Consolidated table inventory, conventions, index plan, deletion rules, client approval gate | RFC | 2 | 0.2.0 |
| [l2-tech-stack.md](specifications/l2-tech-stack.md) | Implementation. Laravel 13 + Filament 5 + PostgreSQL/PostGIS + Redis; package set, bespoke surface, self-hosted deployment | RFC | 2 | 2.0.0 |
| [l2-third-party-integrations.md](specifications/l2-third-party-integrations.md) | Implementation. External services: storage, CDN, map tiles, SMTP, CAPTCHA, error tracking, dormant payment | RFC | 2 | 2.0.0 |

## Rename Map (2026-08-05)

- `l1-hotel-discovery.md` → [l1-object-catalog.md](specifications/l1-object-catalog.md) — domain generalized from hotels to the administrator-managed object type registry.
- `l1-hotel-profile.md` → [l1-object-profile.md](specifications/l1-object-profile.md) — same, plus the conversion path changed to direct contact.
- `l1-property-onboarding.md` → [l1-object-onboarding.md](specifications/l1-object-onboarding.md) — same, plus widened from an intake form to the full owner cabinet.

`l1-room-reservation.md` was **not** renamed or deprecated; it was re-scoped as an
optional module (see [l1-feature-modules.md](specifications/l1-feature-modules.md)).

## Meta Information

- **Maintainer**: Core Team
- **Last Updated**: 2026-08-05
