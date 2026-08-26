# Specification conformance — 2026-08-26

957 requirements were extracted from `.drafts/booking.md` and mapped onto the
implemented code (797 verified before a session limit interrupted the run; the
remaining slice — roughly §26-§60 territory/pricing detail — is unverified, not
contradicted, and should be swept before this is treated as final). Every
verdict below was checked against a real file, not inferred from the live
sweep's behavioural findings.

| Verdict | Count | Meaning |
| --- | --- | --- |
| Implemented | 580 | Matches the requirement |
| Partial | 131 | Mechanism exists, a real gap remains |
| Missing | 61 | No implementation found |
| Improvement | 10 | Does more than asked — keep, do not "fix" |
| Divergence | 15 | Deliberate difference — confirm with the client |

This document's job is the sixteen requirements marked **blocker**: read those
first. They describe one thing the live behavioural sweep could not have found
by clicking through the site, because the missing screens simply do not exist
to click.

## The headline: paid placement has no seller

The specification's entire revenue model is placement tiers an owner pays for.
The read side is real and correct — `PlacementOrderingService` joins
`object_placements → placement_packages → placement_tiers` and orders the
catalog by rank exactly as specified. **The write side has no operator.**

```
PlacementLifecycleService::grant()   — called only by PlacementExpirySweepJob (demotes on expiry)
PlacementLifecycleService::pin()     — called by nothing in app/Filament
PlacementLifecycleService::unpin()   — called by nothing in app/Filament
```

Verified independently of the mapping agent, by grep over `app/Filament` for
every call site of `grant(`, `pin(`, `unpin(`, and `PlacementLifecycleService`:
the only production caller is the expiry sweep, and it only ever *downgrades* an
object to its lowest eligible tier. Nothing in the delivered panel can put an
object into a tier it paid for, extend a package's term, or manually pin a
position — the specification's own escape hatch from strict tier ordering.

**Concretely, today:** a staff member can define a placement package
(`PlacementPackages/`), record that a payment happened
(`FinancialRecords/`), and see the resulting catalog order — but cannot connect
the three. Every object sits at the same `coalesce(rank, 999)` default, so the
catalog is effectively ordered by creation date regardless of what anyone paid.
This is §9, §12, §25, §25.4, §99, §101, §105, §112 and §134, all failing at the
same root cause.

**This is the most consequential finding in three sweeps of this portal.**
Everything else in this document is secondary to it: a directory that cannot
sell its own placement tiers has no revenue model, independent of how well
everything else works.

## The second cluster: no one can be hired

`RoleGrantService` — the service that assigns a role to a user — has no caller
anywhere outside its own file (verified by grep). The only user-facing admin
resource is `Owners/`, whose base query narrows to `object_owner` accounts. A
moderator, a country administrator, a content manager: none of the ten staff
roles can be created, edited, blocked, or reassigned from the panel. The only
route onto the roster is seeding or direct SQL.

This compounds the confirmed live finding that `seo_specialist` holds
`settings.edit` (F-05): even once that grant is corrected in code, **the client
has no way to adjust it again without a developer** — §9, §99, §121 and §134 all
name this as required.

## The third cluster: advertising sells inventory that does not exist

`BannerSelectionService::forSlot()` has exactly one caller site in the entire
codebase — `HomePageController`, requesting `home-top`, `home-mid`,
`home-bottom`. No territory page, country landing page, catalog page, object
page, news page or article page renders a banner. The targeting engine, the
admin CRUD, impression counting and click tracking are all built and correct —
only the render calls on every geographic page are absent.

§24 is written around geographic banner inventory — "one or more banners per
resort/city page", scoped strictly to that page's own territory or category. An
advertiser who buys resort targeting today receives **zero impressions**,
because the only page that ever asks for a banner resolves no territory at all.
No `BannerSlot` row is seeded either, so the slot list is empty on a fresh
install regardless.

## Everything else that is missing or partial, grouped

**Owner acquisition** (confirms the live sweep's F-04): no registration, no
public application form, `canCreate()` false. §104 additionally specifies an
administrator can create an object *from an owner's submitted application* —
there is currently no intake path into the directory at all beyond staff typing
records in by hand.

**Non-room pricing** (§3, §6, §7, §8): prices are editable only through the
Rooms screen, which is hidden unless the object type declares `has_rooms`.
Apartments, villas, restaurants and cafes have **no price-editing surface in
either panel** — the object-level `Price` rows exist in the schema but nothing
writes to them from the UI.

**Object type registry** (§2, §4): 8 of 17 named catalog sections are seeded.
Missing entirely: recreation bases, cottages, private sector, campings, bars,
entertainment, ski resorts. Adding a type is already a no-developer operation
per the architecture (§69 — confirmed implemented); the registry itself is
simply short of the specification's list.

**Promotions page** (§4, §13): the main menu and mobile drawer both link to
`public.promotions.index`, which does not exist as a route. The menu item is
inert on every page.

**Amenities/services dictionary** (§99, §102, §110): seeded as data, filterable
in the catalog, but **no admin screen creates, edits or retires one**. Changing
what "Wi-Fi" or "Parking" means, or adding a new amenity, requires a migration.

**Package entitlements are not enforced** (§25): every owner can publish
promotions and news regardless of what tier they hold; the photo cap is one
number for the whole portal rather than per-package. Two of the differentiators
the specification describes selling are currently given away free.

**Countries and territory-level names** (§24, §99): both are seeder/code only.
Adding a fourth country (this launch covers three) or naming Georgia's
territory levels correctly requires a developer and a redeploy, contradicting
§99's "all main settings changeable through the interface."

**Password recovery** (§100): no "forgot password" link on either sign-in
screen. An owner who forgets a password must telephone staff for a manual
reset.

**Dashboard financial indicators** (§101): 2 of 6 named metrics are shown, and
the one payments figure that exists has no period selector — the window is
hardcoded to 30 days.

**Admin main menu** (§102): 20 of 24 named sections exist. Missing: services and
amenities, rooms and prices, positions and bumps (bumping is a per-object action
only, not a section), and the user-management screen this document already
covers.

**Object list filters** (§103): 5 of 14 named filters. Notably absent: any
territory filter — region, district, city or resort — despite territory being
the portal's primary organising axis and already rendered as a column.

**Import** (§96): implemented for objects only. The other seven entity types
(contacts, prices, services, geographic dictionaries, owners, packages,
statistics) throw `InvalidArgumentException('Import is not yet implemented…')`.
A client migrating an existing directory cannot bring in anything but objects.

**Object edit form organisation** (§104): the specification describes fifteen
tabs; seven exist as tabs on the edit form (the remainder — photos, rooms,
prices, package/position, news, promotions, statistics, change history — are
implemented as separate screens/resources, not as tabs on this one form). This
is arguably a navigation-depth question rather than a missing-capability one;
flag for the client rather than treat as a functional gap.

## Confirmed divergences — decisions for the client, not defects

| §  | The specification asks for | The code does | 
| --- | --- | --- |
| §1 | Five languages (en, ru, ro, uk, ka) at launch | Two (en, ru); the other three are deferred by [existing project decision](../CLAUDE.md), addable from the admin panel without a deploy |
| §7 | Contact click routes through the portal | Goes directly to the owner's channel — matches the brief's own "no intermediary, no commission" framing, so this reads as the brief and the detailed spec disagreeing with each other, not the code diverging from either |
| §15 | Google Maps or OpenStreetMap, selectable | MapLibre GL + a paid tile provider (approved stack per project decision) |
| §108 | Admin panel in uk/ro/ka | Admin panel in en/ru only, matching the language decision above |
| §111 | Four placement package tiers | Verify exact tier count against `PlacementTierSeeder` when the placement-grant gap above is addressed — the tiers exist, only the assignment path is missing |

## Confirmed improvements — keep, do not simplify away

- Automatic publication bypassing moderation is available as a configurable
  mode (§12 brief) — beyond what was asked, and already the subject of a
  documented design decision (`review-submission-design-decision.md`).
- The action journal is protected from modification by ordinary users and
  limited administrators, with scheduled archival (§91) — the mechanism is
  sound even though the screen currently 500s (F-02).
- Statistics are stored separately from main data (§94), final deletion is
  chief-administrator-only (§95), the chief administrator gets mandatory 2FA
  (§100), bulk actions confirm with a record count (§105), and cabinet
  impersonation is always logged (§106) — all implemented ahead of what a
  minimum reading of the specification would require.

## What this changes about priority

The live behavioural sweep (`qa-simulation-2026-08-26.md`) found real defects in
what exists. This pass found that **a significant piece of what the
specification asks for was never built**: the seller side of placement, the
staff-account side of user management, and the geographic side of advertising.
None of the sixteen behavioural findings in the live sweep matter as much as
these three gaps, because F-01 through F-16 are about a working feature working
badly, and this document is about revenue and staffing operations that do not
exist yet to work badly.

Recommended reading order for planning: this document's three headline
clusters, then `qa-fix-specs-2026-08-26.md` S-01 through S-15, then the "missing
or partial" grouping above.
