# Data Model

**Version:** 0.3.1
**Status:** RFC
**Layer:** implementation
**Implements:** l1-platform-foundation.md

## Overview

The consolidated table inventory, cross-cutting column conventions, relationship map,
index plan, and deletion/archival rules for the whole portal — plus the schema
deliverables `[TZ]` §98 calls for, whose client-approval gate has been waived (§5.7).

This spec exists because the data model is currently distributed across sixteen
domain specifications, each stating its own entities correctly but none stating the
whole. `[TZ]` §21 and §98 require exactly that whole as a reviewable artefact; the
requirement stands even though the approval step does not (§5.7).

## Related Specifications

- [l1-platform-foundation.md](l1-platform-foundation.md) - §5.2 entity relationship graph this spec makes concrete.
- [l2-tech-stack.md](l2-tech-stack.md) - §5.3 modelling decisions (recursive-CTE hierarchy, JSONB attributes, translation tables) and §5.5 package set this spec applies.
- [l1-localization.md](l1-localization.md) - Translation table contract.
- [l1-geography.md](l1-geography.md) - Territory hierarchy.
- [l1-object-catalog.md](l1-object-catalog.md) - Object type registry and ordering indexes.
- [l1-object-profile.md](l1-object-profile.md) - Contacts, media, rooms, prices, reviews.
- [l1-placement-monetization.md](l1-placement-monetization.md) - Packages, placement, bumps, ledger.
- [l1-advertising.md](l1-advertising.md) - Banners, slots, labels.
- [l1-moderation-governance.md](l1-moderation-governance.md) - Moderation requests, audit journal, soft deletion.
- [l1-analytics.md](l1-analytics.md) - Event and aggregate tables.
- [l1-notifications.md](l1-notifications.md) - Notification and dispatch tables.
- [l1-back-office.md](l1-back-office.md) - Roles, permissions, scoped grants.
- [l1-feature-modules.md](l1-feature-modules.md) - Module registry and settings.
- [l1-room-reservation.md](l1-room-reservation.md) - Dormant booking tables.
- [l1-public-api.md](l1-public-api.md) - API client and token tables.
- [l1-seo.md](l1-seo.md) - SEO fields and the redirect table.

## 1. Motivation

`[TZ]` §98 is unusually specific and unusually consequential: before programming
begins, the developer must produce a final ER diagram, table list, field list, data
types, primary and foreign keys, indexes, deletion rules, archival rules, and backup
scheme — and **the client approves the final database structure before the main
backend development starts**.

That was a contractual gate, not a documentation preference — and it was the single
most schedule-relevant sentence in the specification. [MODIFIED — v0.3.0] The client
has since waived the approval step (§5.7), which removes the schedule risk but leaves
the artefacts themselves worth producing: they are what the migrations are written
from, and what a later reader reconstructs the design's reasoning from.

There is also a technical reason for consolidation independent of the gate. The
portal's schema is unusually interconnected — translations touch a dozen entities,
scoping touches territories and categories from three directions, and soft deletion
and audit apply almost everywhere. Rules of that kind are invisible when each domain
spec states only its own tables, and they are exactly where inconsistency creeps in.

## 2. Constraints & Assumptions

- [MODIFIED — v0.2.0] There is **no schema to migrate from**. The previous
  implementation was built for the superseded hotel-booking product on a different
  stack and is preserved at git tag `v0.1.34`; the schema below is created from empty
  ([l2-tech-stack.md](l2-tech-stack.md) §6.2).
- This spec fixes the **inventory, relationships, conventions, and rules**. Column-level
  types and constraints per table are expressed as the migration set, not as a parallel
  document (§5.7).
- Modelling decisions are inherited from [l2-tech-stack.md](l2-tech-stack.md) §5.3 and
  the package set in §5.5; they are not re-litigated here.
- [MODIFIED — v0.3.0] `[TZ]` §98's client approval is **waived** by explicit client
  direction — the database design is delegated to this project's engineering judgment.
  See §5.7 for what that changes and what it costs.

## 5. Detailed Design

### 5.1 Cross-Cutting Column Conventions

Applied uniformly. Stating them once here is what keeps them consistent across ~50
tables.

| Convention | Applies to | Shape |
| --- | --- | --- |
| Surrogate key | Every table | `bigIncrements` primary key; ULID on records exposed in public URLs |
| Timestamps | Every table | `created_at`, `updated_at` — Eloquent defaults |
| Soft deletion | Objects, users, news, promotions, banners, articles, reviews | `deleted_at` via Eloquent `SoftDeletes`, plus `deleted_by`; filtered by the trait's global scope |
| Moderation | Externally-authored content | `status` (pending / published / rejected), `moderation_reason` |
| Ordering | Every administrator-orderable registry | `display_order` integer |
| Activation | Every registry | `is_active` boolean |
| Translation | Every content-bearing entity | Sibling `*_translations` table, unique on `(entity_id, locale)` — `astrotomic/laravel-translatable` conventions |
| Scope denormalization | Object, banner, news, promotion | `country_id` carried directly for query performance ([l1-geography.md](l1-geography.md) §5.1) |
| Spatial | Object, territory | `geography(Point, 4326)` column with a GiST index, alongside plain `latitude`/`longitude` for display |
| Audit | Every table under `[TZ]` §91 | `owen-it/laravel-auditing` writes to one polymorphic `audits` table |

### 5.2 Table Inventory

Grouped by owning specification. Roughly fifty tables.

**Identity & access** — [l1-back-office.md](l1-back-office.md), [l1-public-api.md](l1-public-api.md)

```plaintext
users · sessions · password_reset_tokens       (Laravel core)
two_factor_secrets                             ([TZ] §17, §100)
roles · permissions · model_has_roles
  · model_has_permissions · role_has_permissions   (spatie/laravel-permission)
role_scopes            (scope kind + reference — [TZ] §121, this project's addition)
object_user            (per-object rights and staff — [TZ] §72)
personal_access_tokens (laravel/sanctum — [TZ] §19)
api_clients            (token owner metadata, rate limit, scope)
```

`spatie/laravel-permission` supplies roles and permissions but **not** `[TZ]` §121's
scoping of a permission to a country, territory subtree, or object category.
`role_scopes` is this project's addition, consumed by Policies
([l2-tech-stack.md](l2-tech-stack.md) §5.6).

**Localization** — [l1-localization.md](l1-localization.md)

```plaintext
languages · countries · country_translations
```

**Geography** — [l1-geography.md](l1-geography.md)

```plaintext
territories            (parent_id, country_id, level_id, geom, latitude, longitude)
territory_levels       (per-country vocabulary)
territory_translations
```

Hierarchy traversal uses `staudenmeir/laravel-adjacency-list`, which expresses
ancestor and descendant relations as recursive CTEs on the `parent_id` column —
so no materialized-path column is maintained by hand.

**Taxonomy** — [l1-object-catalog.md](l1-object-catalog.md), [l1-object-profile.md](l1-object-profile.md)

```plaintext
object_types · object_type_translations
amenities · amenity_translations · amenity_groups
contact_channel_types · contact_channel_type_translations
```

**Object** — [l1-object-profile.md](l1-object-profile.md), [l1-object-onboarding.md](l1-object-onboarding.md)

`object` carries the field set `[TZ]` §70 enumerates — owner, type, country, region,
district, city or resort, name, address, coordinates, short and full description,
cover image, publication status, availability status, placement package and its
dates, last bump date, creation and update dates, last freshness check, view count,
display order, manual priority, moderation status, and archived flag. Descriptive and
SEO text move to `object_translation` per §5.1; placement fields move to
`object_placement` so a package change does not rewrite the object row.

```plaintext
objects                (owner, type, territory, country, geom, latitude, longitude,
                        attributes JSONB, availability fields, moderation, soft delete)
object_translations
amenity_object · amenity_room
contact_channels
media                  (spatie/laravel-medialibrary — polymorphic, with conversions)
rooms · room_translations
prices                 (period-aware, per object or room)
reviews
availability_histories
favorites              (§5.5)
```

Media uses Media Library's single polymorphic `media` table rather than per-entity
asset tables; conversions (thumbnail, card, gallery) are declared on the model and
generated on the queue, satisfying `[TZ]` §33's automatic optimization.

**Placement & finance** — [l1-placement-monetization.md](l1-placement-monetization.md)

```plaintext
placement_tiers · placement_tier_translations
placement_packages · placement_package_translations
object_placements      (current: package, dates, pinned position, internal priority)
placement_histories    (append-only, with financial fields)
bump_events            (scoped: territory or category)
financial_records      ([TZ] §122 ledger)
```

**Advertising** — [l1-advertising.md](l1-advertising.md)

```plaintext
banners · banner_translations
banner_slots
banner_targets         (many-to-many: territories, categories, languages)
promotion_labels · promotion_label_translations
object_promotions
```

**Content** — [l1-content-publishing.md](l1-content-publishing.md)

```plaintext
articles · article_translations
article_categories · article_category_translations · article_tags
article_object · article_territory      (many-to-many pivots)
news_items · news_translations
promotions · promotion_translations
```

**Governance** — [l1-moderation-governance.md](l1-moderation-governance.md)

```plaintext
moderation_requests    (previous data, proposed data, decision, reason)
audits                 (owen-it/laravel-auditing — polymorphic, INSERT-only privileges)
```

**Notifications** — [l1-notifications.md](l1-notifications.md)

```plaintext
notifications · notification_types · notification_channels · notification_dispatches
notification_templates (per locale, per channel)
```

**Analytics** — [l1-analytics.md](l1-analytics.md)

```plaintext
stat_events            (date-partitioned, short retention)
stat_dailies           (aggregate, long retention)
```

**Platform** — [l1-feature-modules.md](l1-feature-modules.md), [l1-seo.md](l1-seo.md), [l1-home-page.md](l1-home-page.md)

```plaintext
modules · module_settings
settings               ([TZ] §130 portal settings)
redirects              (slug changes, merges, archived content)
home_block_selections  (per-country curated selections)
```

**Booking (dormant)** — [l1-room-reservation.md](l1-room-reservation.md)

```plaintext
reservations · room_availabilities · booking_settings
```

These three exist in the schema and carry no rows until the module is activated
([l1-feature-modules.md](l1-feature-modules.md) §3).

### 5.3 Relationship Map

Beyond [l1-platform-foundation.md](l1-platform-foundation.md) §5.2's graph, the
cardinalities `[TZ]` §93 states explicitly:

```plaintext
country      1 ── n  territory (via recursive parent)
territory    1 ── n  territory          (self-referencing, unbounded depth)
territory    1 ── n  object
user         1 ── n  object             (primary owner)
object       n ── n  user               (staff and limited managers, via object_grant)
object       1 ── n  media_asset · room · contact_channel · news_item
                     · promotion · review · bump_event · placement_history
object       n ── n  amenity
object       1 ── 1  object_placement   (current) + 1 ── n placement_history
banner       n ── n  territory          (via banner_target)
article      n ── n  object · territory
{entity}     1 ── n  {entity}_translation   (one per active language)
```

### 5.4 Index Plan

`[TZ]` §94 names the fields requiring indexes. Mapped to concrete indexes:

| Requirement (`[TZ]` §94) | Index |
| --- | --- |
| Country, region, city, resort | `objects(country_id, territory_id)`; `territories(parent_id)` for CTE traversal |
| Object type | `objects(object_type_id)` |
| Package | `object_placements(placement_package_id)` |
| Publication status | `objects(status) WHERE deleted_at IS NULL` (partial) |
| Moderation status | `moderation_requests(decision, created_at)` |
| Bump date | `bump_events(object_id, scope_type, scope_id, occurred_at DESC)` |
| Package expiry | `object_placements(ends_at)` |
| Object name | `object_translations(locale, name) gin_trgm_ops` |
| Page address (slug) | `object_translations(locale, slug)` unique; same per translated entity |
| Publication date | `news_items(published_at)`, `articles(published_at)`, `promotions(starts_at, ends_at)` |
| Language | Every `*_translations(entity_id, locale)` unique |
| Catalog ordering | Composite covering `(country_id, territory_id, object_type_id, status)` supporting the §5.2 ordering in [l1-placement-monetization.md](l1-placement-monetization.md) |
| Filterable attributes | GIN on `objects.attributes` for the type-declared filterable keys |
| **Spatial** | GiST on `objects.geom` and `territories.geom` — map bbox, radius, and nearby-object queries (`[TZ]` §7, §10, §15) |
| Statistics | `stat_dailies(date, subject_id, kind)`; `stat_events` partitioned by date |

### 5.5 Favorites [`[TZ]` §8]

`[TZ]` §8 lists "Favorites" among owner-cabinet capabilities, without elaboration.
Two readings are possible and they imply different tables:

- A visitor-facing favorites feature, with the owner seeing how many visitors
  favorited their object;
- An owner-side bookmark within the cabinet.

Modelled as the first, since it is the reading that makes "Favorites" a *cabinet
statistic* rather than a redundant navigation aid, and because it composes with
[l1-analytics.md](l1-analytics.md):

```plaintext
favorite
├── object   -> object
├── owner    -> user (nullable: anonymous favorites keyed by browser token)
├── created at
└── Uniqueness: (object, owner) or (object, browser token)
```

<!-- TBD: [TZ] §8 names "Favorites" once with no description, and the portal has no
     visitor accounts in its default configuration (guest_accounts is a dormant
     module). Anonymous, browser-scoped favorites are assumed above so the feature
     works without accounts; confirm with the client whether favorites are expected
     to survive across devices, which would require the guest-accounts module. -->

### 5.6 Deletion & Archival Rules [`[TZ]` §95, §98]

| Entity | Delete behaviour | Cascade |
| --- | --- | --- |
| Object | Soft; archived, restorable, transferable to another owner | Media retained ([l1-object-profile.md](l1-object-profile.md) §5.5); rooms, prices, contacts follow the parent |
| User | Soft; objects must be reassigned before hard deletion | Grants revoked; audit entries retained |
| News, promotion, article, banner | Soft; archived | Translations follow |
| Review | Hidden with a recorded reason, never deleted by the owner | — |
| Territory | Blocked while objects or descendants are attached; deactivation instead | — |
| Audit entry | **Never deleted** by any role; archived by age per `[TZ]` §94 | — |
| Financial record | Never deleted; retained for reporting | — |
| Statistics | Raw events compacted after rollup; aggregates retained | — |
| Placement history | Append-only; a package change never removes prior rows | — |

Permanent deletion is restricted to the chief administrator and is itself journalled
([l1-moderation-governance.md](l1-moderation-governance.md) §3.3).

### 5.7 Schema Deliverables [`[TZ]` §98 — approval waived]

[MODIFIED — v0.3.0] `[TZ]` §98 requires that "the final database structure is
approved by the client before the main backend development begins". **The client has
waived that approval**, delegating the database design to this project's engineering
judgment.

The waiver removes the *gate*, not the *work*. The artefacts below are still produced —
as the basis for migrations and as the record of what was built and why — but they no
longer block the start of backend development, and there is no sign-off step.

**What the waiver costs, stated plainly**: §98 existed to protect the client from a
schema designed for the contractor's convenience rather than the product's needs. With
no external reviewer, that protection now rests entirely on this specification layer —
which is why the inventory, conventions, and rules in §5.1–§5.6 are written to be
auditable by a reader who was not present for the decisions. The client can still
reject the design at any point; nothing is irreversible until migrations are applied
against production data.

Deliverables:

```plaintext
☐ Final ER diagram
☐ Table list                     (§5.2 — complete at inventory level)
☐ Field list per table           (outstanding)
☐ Data types per field           (outstanding)
☐ Primary and foreign keys       (outstanding)
☐ Indexes                        (§5.4 — complete at requirement level)
☐ Deletion rules                 (§5.6 — complete)
☐ Archival rules                 (§5.6 — complete)
☑ Backup scheme                  ([TZ] §97 — spatie/laravel-backup to an
                                  S3-compatible destination separate from the
                                  application server, l2-tech-stack.md §5.10)
```

[MODIFIED — v0.3.0] Five of nine are complete at this spec's granularity. The three
outstanding items — field list, data types, primary and foreign keys — are produced as
**Laravel migrations plus the `migrate:fresh --seed` verification**, not as a separate
document: a migration set that applies cleanly from empty *is* the field list, the type
list, and the key list, and it cannot drift from the schema the way a parallel document
would.

The ER diagram is generated from the applied schema rather than hand-drawn, for the
same reason.

**Sequencing consequence**: nothing now blocks backend work. The critical path runs
straight from specification review into scaffolding and the migration set.

## 6. Implementation Notes

1. Create the schema in one migration pass, from empty. Group migrations by domain
   (identity, localization, geography, taxonomy, object, placement, advertising,
   content, governance, notifications, analytics, platform, booking) so the ordering
   is legible and `migrate:fresh --seed` is the standard verification.
2. Create translation tables alongside their parents, never afterwards
   ([l1-localization.md](l1-localization.md) §6.1) — regardless of how many languages
   are active at launch.
3. Enforce append-only tables at the privilege level, not in application code
   ([l1-moderation-governance.md](l1-moderation-governance.md) §6.3).
4. Put soft-delete and moderation filtering in the shared query layer. A single
   forgotten predicate republishes archived or unmoderated content silently.
5. Partition `stat_event` from day one ([l1-analytics.md](l1-analytics.md) §6.2).
6. Seed registries as data in the first migration: languages, countries, territory
   levels, object types, amenities, contact channel types, placement tiers, roles,
   permissions, modules, notification types.

## 7. Drawbacks & Alternatives

**Leaving the model distributed across the sixteen domain specs.** How it stood before
this spec, and it fails `[TZ]` §21 and §98 outright: there is no artefact to approve.
It also hides the cross-cutting conventions in §5.1, which is where inconsistency
between fifty tables actually originates.

**Specifying every column here.** The literal reading of `[TZ]` §98, and premature at
this stage: column-level detail is worth writing once the inventory is agreed with the
client, not twice. §5.7 records the split explicitly so the remaining work is visible
rather than assumed done.

**Extending the existing schema incrementally.** Appealing because it preserves
working code, and wrong on shape: `hotel` becomes `object` with a type registry and a
JSONB attribute bag, translations arrive for every content entity, and territory
replaces free-text location. That is a replacement, not an extension.

**Deferring the approval gate until the schema is built.** Reverses `[TZ]` §98 and
risks the client declining a schema that backend work already depends on — the exact
failure the client wrote the clause to prevent.

## Canonical References

| Alias | Path | Purpose |
| --- | --- | --- |
| `[TZ]` | `.drafts/booking.md` | §21, §65–§70, §71–§95, §98 — source requirements. |
| `[L1]` | `.design/main/specifications/l1-platform-foundation.md` | §5.2 entity graph. |
| `[L2-STACK]` | `.design/main/specifications/l2-tech-stack.md` | §5.4 modelling decisions, §5.9 deployment fork. |

## Document History

| Version | Date | Change |
| --- | --- | --- |
| 0.1.0 | 2026-08-05 | Initial draft. Closes the `[TZ]` §21/§98 gap found during the second requirements pass: consolidated table inventory, cross-cutting conventions, index plan, deletion and archival rules, the favorites model, and the client approval gate that precedes backend development. |
| 0.2.0 | 2026-08-05 | Minor: realigned to the approved Laravel stack — Eloquent/Filament table naming, spatie Media Library and Auditing tables, spatie/laravel-permission plus this project's `role_scopes` addition, adjacency-list traversal in place of a materialized path, and PostGIS `geom` columns with GiST indexes. Marked the backup-scheme deliverable complete: the deployment fork it depended on is resolved. |
| 0.3.0 | 2026-08-05 | Minor: `[TZ]` §98's client approval waived by explicit client direction. Reframed §5.7 from an approval gate to an engineering deliverable, recorded what the waiver costs, and resolved the three outstanding items into the migration set plus a generated ER diagram rather than a parallel document. Nothing now blocks backend work. |
| 0.3.1 | 2026-08-05 | Patch: translated quoted `[TZ]` excerpts from Russian to English per the project's language policy; no meaning changed. |
