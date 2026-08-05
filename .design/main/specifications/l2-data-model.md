# Data Model

**Version:** 0.1.0
**Status:** RFC
**Layer:** implementation
**Implements:** l1-platform-foundation.md

## Overview

The consolidated table inventory, cross-cutting column conventions, relationship map,
index plan, and deletion/archival rules for the whole portal — plus the **client
approval gate** that `[TZ]` §98 places in front of backend development.

This spec exists because the data model is currently distributed across sixteen
domain specifications, each stating its own entities correctly but none stating the
whole. `[TZ]` §21 and §98 require exactly that whole, as a reviewable artefact,
before backend work begins.

## Related Specifications

- [l1-platform-foundation.md](l1-platform-foundation.md) - §5.2 entity relationship graph this spec makes concrete.
- [l2-tech-stack.md](l2-tech-stack.md) - §5.4 modelling decisions (materialized path, JSONB attributes, translation tables, partitioning) this spec applies.
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

That is a contractual gate, not a documentation preference, and it is the single most
schedule-relevant sentence in the specification. Missing it does not produce a bug; it
produces backend work built on an unapproved schema that the client can decline.

There is also a technical reason for consolidation independent of the gate. The
portal's schema is unusually interconnected — translations touch a dozen entities,
scoping touches territories and categories from three directions, and soft deletion
and audit apply almost everywhere. Rules of that kind are invisible when each domain
spec states only its own tables, and they are exactly where inconsistency creeps in.

## 2. Constraints & Assumptions

- The current implemented schema (`src/lib/db/schema.ts`, ~15 tables) was built for the
  superseded hotel-booking product. It is a **subset with a different shape**, not a
  starting point to extend incrementally
  ([l2-tech-stack.md](l2-tech-stack.md) §6.3).
- This spec fixes the **inventory, relationships, conventions, and rules**. Column-level
  types and constraints per table are the deliverable that follows it and that §5.7
  gates.
- Modelling decisions are inherited from [l2-tech-stack.md](l2-tech-stack.md) §5.4 and
  are not re-litigated here.
- <!-- TBD: [TZ] §98 requires client approval of the final structure. Neither the
     approver, the review format, nor the turnaround is stated. This is a project-
     management question that must be answered before backend scheduling, since it
     sits on the critical path. -->

## 5. Detailed Design

### 5.1 Cross-Cutting Column Conventions

Applied uniformly. Stating them once here is what keeps them consistent across ~50
tables.

| Convention | Applies to | Shape |
| --- | --- | --- |
| Surrogate key | Every table | UUID primary key, except auth tables which follow Better Auth's text ids |
| Timestamps | Every table | `created_at`, `updated_at`, not null, defaulted |
| Soft deletion | Objects, users, news, promotions, banners, articles, reviews | `deleted_at` nullable + `deleted_by`; filtered in the shared query layer |
| Moderation | Externally-authored content | `status` (pending / published / rejected), `moderation_reason` |
| Ordering | Every administrator-orderable registry | `display_order` integer |
| Activation | Every registry | `is_active` boolean |
| Translation | Every content-bearing entity | Sibling `*_translation` table, unique on `(entity_id, language_id)` |
| Scope denormalization | Object, banner, news, promotion | `country_id` carried directly for query performance ([l1-geography.md](l1-geography.md) §5.1) |

### 5.2 Table Inventory

Grouped by owning specification. Roughly fifty tables.

**Identity & access** — [l1-back-office.md](l1-back-office.md), [l1-public-api.md](l1-public-api.md)

```plaintext
user · session · account · verification        (Better Auth core)
role · permission · role_permission
user_role_grant        (role + scope kind + scope reference)
object_grant           (per-object rights per [TZ] §72)
api_client · api_token
```

**Localization** — [l1-localization.md](l1-localization.md)

```plaintext
language · country · country_translation
```

**Geography** — [l1-geography.md](l1-geography.md)

```plaintext
territory              (parent_id, country_id, level_id, path, coordinates)
territory_level        (per-country vocabulary)
territory_translation
```

**Taxonomy** — [l1-object-catalog.md](l1-object-catalog.md), [l1-object-profile.md](l1-object-profile.md)

```plaintext
object_type · object_type_translation
amenity · amenity_translation · amenity_group
contact_channel_type · contact_channel_type_translation
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
object                 (owner, type, territory, country, coordinates,
                        attributes JSONB, availability fields, moderation, soft delete)
object_translation
object_amenity · room_amenity
contact_channel
media_asset            (photo · video · panorama · logo · document)
media_asset_translation    (captions)
room · room_translation
price                  (period-aware, per object or room)
review
availability_history
favorite               (§5.5)
```

**Placement & finance** — [l1-placement-monetization.md](l1-placement-monetization.md)

```plaintext
placement_tier · placement_tier_translation
placement_package · placement_package_translation
object_placement       (current: package, dates, pinned position, internal priority)
placement_history      (append-only, with financial fields)
bump_event             (scoped: territory or category)
financial_record       ([TZ] §122 ledger)
```

**Advertising** — [l1-advertising.md](l1-advertising.md)

```plaintext
banner · banner_translation
banner_slot
banner_target          (many-to-many: territories, categories, languages)
promotion_label · promotion_label_translation
object_promotion
```

**Content** — [l1-content-publishing.md](l1-content-publishing.md)

```plaintext
article · article_translation
article_category · article_category_translation · article_tag
article_object · article_territory      (many-to-many)
news_item · news_translation
promotion · promotion_translation
```

**Governance** — [l1-moderation-governance.md](l1-moderation-governance.md)

```plaintext
moderation_request     (previous data, proposed data, decision, reason)
audit_entry            (append-only; INSERT-only privileges)
```

**Notifications** — [l1-notifications.md](l1-notifications.md)

```plaintext
notification · notification_type · notification_channel · notification_dispatch
notification_template  (per language, per channel)
```

**Analytics** — [l1-analytics.md](l1-analytics.md)

```plaintext
stat_event             (date-partitioned, short retention)
stat_daily             (aggregate, long retention)
```

**Platform** — [l1-feature-modules.md](l1-feature-modules.md), [l1-seo.md](l1-seo.md), [l1-home-page.md](l1-home-page.md)

```plaintext
module · module_setting
setting                ([TZ] §130 portal settings)
redirect               (slug changes, merges, archived content)
home_block_selection   (per-country curated selections)
```

**Booking (dormant)** — [l1-room-reservation.md](l1-room-reservation.md)

```plaintext
reservation · room_availability · booking_settings
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
| Country, region, city, resort | `object(country_id, territory_id)`; `territory(path)` GIN for subtree scans |
| Object type | `object(object_type_id)` |
| Package | `object_placement(package_id)` |
| Publication status | `object(status) WHERE deleted_at IS NULL` (partial) |
| Moderation status | `moderation_request(decision, submitted_at)` |
| Bump date | `bump_event(object_id, scope_kind, scope_id, occurred_at DESC)` |
| Package expiry | `object_placement(end_date)` |
| Object name | `object_translation(language_id, name) gin_trgm_ops` |
| Page address (slug) | `object_translation(language_id, slug)` unique; same per translated entity |
| Publication date | `news_item(published_at)`, `article(published_at)`, `promotion(start, end)` |
| Language | Every `*_translation(entity_id, language_id)` unique |
| Catalog ordering | Composite covering `(country_id, territory_id, object_type_id, status)` supporting the §5.2 ordering in [l1-placement-monetization.md](l1-placement-monetization.md) |
| Filterable attributes | GIN on `object.attributes` for the type-declared filterable keys |
| Statistics | `stat_daily(date, subject_id, kind)`; `stat_event` partitioned by date |

### 5.5 Favorites [`[TZ]` §8]

`[TZ]` §8 lists "Избранное" among owner-cabinet capabilities, without elaboration.
Two readings are possible and they imply different tables:

- A visitor-facing favorites feature, with the owner seeing how many visitors
  favorited their object;
- An owner-side bookmark within the cabinet.

Modelled as the first, since it is the reading that makes "Избранное" a *cabinet
statistic* rather than a redundant navigation aid, and because it composes with
[l1-analytics.md](l1-analytics.md):

```plaintext
favorite
├── object   -> object
├── owner    -> user (nullable: anonymous favorites keyed by browser token)
├── created at
└── Uniqueness: (object, owner) or (object, browser token)
```

<!-- TBD: [TZ] §8 names "Избранное" once with no description, and the portal has no
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

### 5.7 Client Approval Gate [`[TZ]` §98]

**Backend development does not begin until the client approves the database
structure.** `[TZ]` §98 states this directly, and it is recorded here as a gate rather
than as advice.

Deliverables required before that approval:

```plaintext
☐ Final ER diagram
☐ Table list                     (§5.2 — complete at inventory level)
☐ Field list per table           (outstanding)
☐ Data types per field           (outstanding)
☐ Primary and foreign keys       (outstanding)
☐ Indexes                        (§5.4 — complete at requirement level)
☐ Deletion rules                 (§5.6 — complete)
☐ Archival rules                 (§5.6 — complete)
☐ Backup scheme                  ([TZ] §97; blocked on the deployment fork,
                                  l2-tech-stack.md §5.9)
```

Four of nine are complete at this spec's granularity; three require column-level
elaboration; one is blocked on an unrelated decision. Stating the split plainly is
more useful than a claim of completeness — the column-level work is bounded and
mechanical once the inventory is agreed, and the backup scheme cannot be written at
all until the deployment target is chosen.

**Sequencing consequence**: the deployment fork
([l2-tech-stack.md](l2-tech-stack.md) §5.9) is on the critical path for this gate, and
this gate is on the critical path for all backend work.

## 6. Implementation Notes

1. Create the schema in one migration pass. The current schema is a different-shaped
   subset ([l2-tech-stack.md](l2-tech-stack.md) §6.3), and incremental extension
   would produce a hybrid of two product models.
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
