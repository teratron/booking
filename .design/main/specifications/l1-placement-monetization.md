# Placement & Monetization

**Version:** 0.2.0
**Status:** RFC
**Layer:** concept

## Overview

How the portal earns: four placement tiers that determine an object's position in
every catalog, administrator-defined packages that grant a tier for a period, the
act by which a staff member confers one on an object, the bump mechanic that
reorders an object within its own tier, package expiry handling, and the financial
ledger that records what was sold. Derived from
`[TZ]` §25, §26, §54–§63, §79–§81, §99, §101, §105, §111–§112, §122–§123, §129,
§133.

## Related Specifications

- [l1-platform-foundation.md](l1-platform-foundation.md) - Paid-placement-ordering, package-parity-of-capability, and configuration-over-code invariants.
- [l1-object-catalog.md](l1-object-catalog.md) - Renders the ordering this spec defines.
- [l1-geography.md](l1-geography.md) - Supplies the scopes within which tier ordering and bumps are evaluated.
- [l1-advertising.md](l1-advertising.md) - Sibling revenue stream; owns the badges and border treatments a tier grants.
- [l1-object-onboarding.md](l1-object-onboarding.md) - Surfaces package state and the bump control to the owner.
- [l1-notifications.md](l1-notifications.md) - Delivers the expiry warning schedule.
- [l1-back-office.md](l1-back-office.md) - [MODIFIED] Hosts the grant, position, and finance surfaces; §5.6 below defines the act those surfaces perform.
- [l1-moderation-governance.md](l1-moderation-governance.md) - Journals every package, position, and bump change.
- [l1-analytics.md](l1-analytics.md) - Measures the visibility a package actually delivered.

## 1. Motivation

This is the portal's business model, and it is unusual enough to be worth stating
plainly: the portal sells **position**, not capability. `[TZ]` §25 and §55 are
emphatic — every object gets the same page, the same photo allowance, the same
contacts, the same news and promotions. What money buys is where the object appears
in a list and how its card is decorated.

That constraint is a gift to the architecture. Because packages do not gate features,
there is no permission matrix threading through every surface, no "upgrade to unlock"
logic in the object page, and no risk of a downgrade silently deleting content. The
entire monetization model collapses to three things: a tier on the object, an
ordering rule in the catalog, and a ledger of what was sold.

The one exception — bumping is package-gated (`[TZ]` §41) — is deliberate and
narrow, and is called out as such rather than allowed to grow.

## 2. Constraints & Assumptions

- No guest-facing payment exists. Owners pay the portal operator through channels
  outside the system; the portal records the transaction, it does not process it
  (`[TZ]` §122). A full accounting system is explicitly out of scope
  (`[TZ]` §122).
- `[TZ]` presents the tier scheme twice with different working names (§25 "Premium /
  Priority / Extended / Basic", then §25.1 "VIP / Recommended / Priority Placement /
  Standard"). Both are working names; §25 states they are
  administrator-editable. This spec models **four ranks with editable labels** and
  treats neither naming as canonical.
- Package definitions may differ per object category — hotels, restaurants,
  sanatoria and holiday bases can each have their own package set (`[TZ]` §25.2).
- <!-- TBD: whether an object may hold more than one concurrent paid service (e.g. a
     placement package plus a separately purchased temporary promotion) is implied by
     [TZ] §58's administrator-applied promotions but never stated. Modeled below as
     one active package plus zero-or-more independent promotion grants, which
     satisfies both readings. -->

## 3. Core Invariants (Layer 1 only)

### 3.1 Tiers & Ordering

- **Four ranks exist.** Their labels, badge text, colours, and icons are
  administrator-editable data; their *count and relative order* are structural
  (`[TZ]` §25.1, §60).
- **Rank dominates.** Objects are emitted rank by rank. A lower-ranked object never
  appears above a higher-ranked one (`[TZ]` §25.2).
- **The only override is explicit and journalled.** [MODIFIED — v0.2.0] Position may
  be set by hand, and nothing else may violate rank order (`[TZ]` §25.2, §112). The
  two authorities are separate: placing an object at a position *within* its own tier
  is an ordinary administrator action, scopable like any other (`[TZ]` §112 grants all
  six position operations to «администратор»), while a placement that lets a lower
  tier outrank a higher one is reserved to the chief administrator (`[TZ]` §25.2).
  Conflating them either blocks routine merchandising or hands every administrator the
  one lever that breaks the ordering the portal sells.
- **Pinning has an inverse.** [ADDED — v0.2.0] Whatever can be pinned can be
  unpinned, its internal priority adjusted, and its automatic ordering restored — the
  six operations `[TZ]` §112 enumerates are a set, and a pin with no exit is a
  position no administrator can give back.
- **Ordering is scope-local.** Rank ordering restarts for every country, region,
  district, city, resort, object category, and search result set (`[TZ]` §25.2).
- **Manual pinning does not change tier.** Pinning an object to a position must not
  silently promote it into another package (`[TZ]` §112).

### 3.2 Package Parity

- A package buys position, card decoration, badge, and bump eligibility — nothing
  else (`[TZ]` §25, §55, §111). Photo count, contact count, description length,
  services, news, and promotions are identical across all packages.
- Bump availability and frequency are the **single** package-varying capability
  (`[TZ]` §41, §79).

### 3.3 Bumping

- A bump moves an object to the first free position **within its own tier**, never
  above it (`[TZ]` §25.3, §26).
- A bump is **scope-specific**: it applies to the city, district, or resort the
  object belongs to, and the system records which scope it acted on
  (`[TZ]` §25.3, §56).
- Bumps are constrained by administrator-set limits: minimum interval between free
  bumps, maximum bump count, price per bump, and bump duration (`[TZ]` §26.2).
- Every bump records object, package, scope, catalog category, timestamp, bump type,
  actor, previous position, new position, price, and comment (`[TZ]` §81, §25.3).
- Bump types are: free, paid, automatic, owner-initiated, administrator-initiated
  (`[TZ]` §81, §26.1).
- **An ineligible bump is refused, not merely hidden.** [ADDED — v0.2.0] §5.3
  expresses the package gate as a control the cabinet does not render; the refusal
  itself is decided where the bump is performed (`[TZ]` §41, §79). The control's
  absence is a usability affordance — stating the server-side refusal separately is
  what stops a later change removing the check along with the button.

### 3.4 Lifecycle & Expiry

- A package grant has a start and an end date. Changing a package never deletes the
  previous grant — history is append-only (`[TZ]` §80).
- On expiry the system performs a **configured** action: demote to standard tier,
  strip the border and badge, hide the object, or flag for manual review
  (`[TZ]` §123). The recommended default is demote-and-strip while remaining
  published (`[TZ]` §123).
- Expiry warnings are issued at 30, 14, 7, and 3 days before, on the day, and after
  (`[TZ]` §62). Delivery is owned by
  [l1-notifications.md](l1-notifications.md).
- Every financial record persists: date paid, validity window, package, amount,
  currency, payment method, document number, responsible staff member, status, and
  comment (`[TZ]` §80, §122).

### 3.5 Configurability

- Creating a package, changing its price, renaming it, altering its border colour,
  badge text, validity period, bump frequency, or activating and deactivating it are
  all administrator actions requiring no code change (`[TZ]` §60, §63).
- Package sets may differ per object category (`[TZ]` §25.2).

### 3.6 Granting [ADDED — v0.2.0]

§3.4 says a grant has a start and an end and that history is append-only. It does not
say how a grant comes to exist at all, and the omission is not academic: without it an
object can only reach a tier through a migration or a developer, which is precisely
the arrangement `[TZ]` §99.1 forbids.

- **A placement is granted, not merely recorded.** Attaching a package to an object is
  an explicit act performed in the back office — a staff member chooses the package,
  sets or accepts the validity window, and optionally records the money — never a
  direct edit of stored data and never a developer's task (`[TZ]` §25.4, §99.1, §101).
  Defining a package and recording a payment are two other acts; neither places
  anything.
- **Granting is scopable.** Conferring an existing package on an object is an
  administrator permission that may be bounded to a country, territory, or object
  category like any other (`[TZ]` §25.4, §121). Creating and pricing the packages
  themselves stays with the chief administrator (`[TZ]` §60) — selling a tier and
  defining what a tier costs are different authorities.
- **Changing a package closes one grant and opens another.** The act writes exactly one
  closing row and one opening row; no prior row is rewritten or removed. The opening
  row carries the acting staff member and their comment (`[TZ]` §80). §3.4 forbids
  deleting history; this is the shape of the act that produces it.
- **A grant may be free.** Staff may confer a tier with no payment recorded, and a
  comped placement stays distinguishable from a sold one wherever placements are
  counted or reported (`[TZ]` §101, §122). The client counts free placements on the
  dashboard, so the distinction is a required state rather than a tolerated one.
- **The term governs the tier; the ledger status reports on the money.** An object
  ranks at the tier its validity window grants, and `awaiting payment`, `partially
  paid`, and `overdue` do not silently revoke that position — the client lists overdue
  placements as a thing to chase (`[TZ]` §101, §123), which only means anything if an
  unpaid placement still exists and still ranks. `cancelled` is the one status that
  ends the placement: setting it applies §3.4's configured end-of-placement action at
  once, rather than leaving the object at its tier until the term runs out.
- **A placement's history is readable from the panel.** `[TZ]` §25.4 requires an
  administrator to be able to review an object's placement changes, and §3.4's
  append-only history is what such a view reads. A history nothing surfaces is a
  record kept for no one.
- **The portal records money; it does not compute it.** A mid-term package change
  performs no proration, no refund arithmetic, and no overlap reconciliation — money is
  transacted off-portal and the ledger records that it happened (`[TZ]` §122). Saying
  so is what stops the grant surface being designed around a billing engine the client
  did not commission.

## 5. Detailed Design

### 5.1 Model

```plaintext
PlacementTier                       PlacementPackage
├── rank            1..4            ├── tier            -> PlacementTier
├── border colour                   ├── object category -> ObjectType (optional)
├── badge colour                    ├── price · currency
├── badge icon                      ├── validity period
├── active flag                     ├── bump allowed
└── translations -> label,          ├── bump interval
                    badge text      ├── free bumps per period
                                    ├── paid bump price
                                    ├── active flag · display order
                                    └── translations -> name

ObjectPlacement (current, on the object)   PlacementHistory (append-only)
├── object       -> Object                 ├── object · package
├── package      -> PlacementPackage       ├── start · end
├── start · end                            ├── amount · currency
├── pinned position (optional)             ├── paid at · payment method
├── internal priority                      ├── document number
└── expiry action override                 ├── status        -> §5.5
                                           ├── granted by    -> Account
                                           └── comment

BumpEvent
├── object · package
├── scope            -> Territory | ObjectType   (which list was bumped)
├── occurred at
├── type             free | paid | automatic | owner | administrator
├── actor            -> Account
├── previous position · new position
├── price
└── comment
```

`BumpEvent.scope` is what makes `[TZ]` §25.3 and §56 true: an object bumped on the
Bukovel page is first among its tier *there*, and unaffected on its region page. The
catalog's ordering reads the most recent bump **for the scope being rendered**
([l1-object-catalog.md](l1-object-catalog.md) §5.3), not a single global timestamp.

### 5.2 Ordering Contract

Restated here as the authoritative source; the catalog spec renders it.

```plaintext
ORDER BY
  tier.rank                              ASC
  placement.pinned_position              ASC NULLS LAST
  bump_for_this_scope.occurred_at        DESC NULLS LAST
  active_promotion_weight                DESC
  placement.internal_priority            DESC
  object.created_at                      DESC
  object.rating                          DESC
  rotation_seed                          ASC   -- optional
```

### 5.3 Bump Flow

```mermaid
graph TD
    A[Owner opens Bump in cabinet] --> B{Package allows bumping?}
    B -->|no| C[Control absent; upgrade information shown]
    B -->|yes| D{Free bump available now?}
    D -->|yes| E[Free bump]
    D -->|no| F{Paid bumps configured and remaining?}
    F -->|no| G[Show next free bump date]
    F -->|yes| H[Paid bump]
    E --> I[Resolve scope: object's city / district / resort]
    H --> I
    I --> J[Record BumpEvent: previous and new position]
    J --> K[Invalidate catalog caches for that scope]
    K --> L[Object first within its tier in that scope]
```

Per `[TZ]` §26.2 the cabinet shows the owner: current position, placement tier, last
bump date, next available free bump date, remaining bump count, and a paid-bump
action.

### 5.4 Expiry

```mermaid
graph TD
    A[Scheduled job: packages nearing or past expiry] --> B[30 / 14 / 7 / 3 days out]
    B --> C[Notify owner]
    A --> D[Expiry day]
    D --> C
    D --> E{Configured expiry action}
    E -->|demote| F[Move to standard tier; strip border and badge]
    E -->|hide| G[Unpublish; remains in the back office]
    E -->|review| H[Flag for manual administrator decision]
    F --> I[Close PlacementHistory row; journal the transition]
    G --> I
    H --> I
    I --> J[Notify owner: placement ended]
```

The default is `demote` (`[TZ]` §123). `hide` is available and is the harsher
setting — an object that vanishes from the catalog also vanishes from search
engines, so the recommendation exists for a reason.

### 5.5 Financial Ledger

Per `[TZ]` §122, each record carries: object or advertiser, service, package, amount,
currency, payment date, validity period, payment method, document number, comment,
responsible staff member, and status. Statuses are: awaiting payment, paid, partially
paid, overdue, cancelled, granted free of charge.

Per `[TZ]` §61 and §123, the back office reports: active placements, placements
ending in 30 / 14 / 7 / 3 days, expired placements, objects with no term set, free
placements, paid bump counts, and active advertising campaigns. Reports are filterable
by country, period, package, and staff member, and exportable
([l1-back-office.md](l1-back-office.md) §5.7).

This is a **ledger, not a payment system**. It records that money changed hands
elsewhere. Access to it is a distinct permission — `[TZ]` §128 restricts financial
export to specific roles.

[ADDED — v0.2.0] Of the six statuses, only `cancelled` bears on ordering, and it ends
the placement early by the route §3.6 describes. The other five describe the state of
a debt, not the state of a position: an object whose payment is `overdue` continues to
rank at its tier until its term ends, because the alternative — silent demotion on a
bookkeeping state — would make the catalog's order depend on how promptly an accounts
clerk updated a field. §3.6 states the rule; this is where the statuses it governs are
enumerated.

### 5.6 Grant Flow [ADDED — v0.2.0]

The act §3.6 requires. It sits alongside §5.3's bump and §5.4's expiry as the third
thing that can change an object's placement, and it is the only one of the three a
person initiates.

```mermaid
graph TD
    A[Staff opens placement on an object] --> B{Permitted for this object's scope?}
    B -->|no| C[Action absent; refused if attempted]
    B -->|yes| D[Choose package, validity window, optional comment]
    D --> E{Object already holds a grant?}
    E -->|yes| F[Close the open history row]
    E -->|no| G[No prior row to close]
    F --> H[Open a new history row: package, window, actor, comment]
    G --> H
    H --> I{Money recorded now?}
    I -->|yes| J[Ledger entry: amount, method, document, status]
    I -->|no, comped| K[Mark the grant free of charge]
    I -->|no, later| L[Ledger entry may follow independently]
    J --> M[Journal the change]
    K --> M
    L --> M
    M --> N[Invalidate catalog caches for the object's scopes]
    N --> O[Object ranks at its new tier]
```

Two consequences worth stating, because both are easy to get wrong and neither is
recoverable afterwards. A manual position from §3.1 is cleared exactly when the object
leaves the tier it was pinned within, and at no other time — a position held inside a
tier the object no longer occupies means nothing, while a pin survives anything that
leaves the object where it was. So a package change that moves the object to a
different tier clears the pin and one that merely extends the same tier does not, and
of §3.4's four expiry actions the two that demote or hide clear it while a flag for
manual review leaves it standing until that review decides. And
a pin or unpin records the same shape §3.3 requires of a bump — object, scope, catalog
category, actor, previous position, new position, timestamp — because `[TZ]` §129
classifies position changes as journalled administrative operations, and a journal
entry that cannot answer "from what, to what" is a timestamp with a name attached.

## 6. Implementation Notes

1. Model bumps as scoped events from the first migration. A single `last_bumped_at`
   column on the object cannot express `[TZ]` §25.3, and retrofitting scope onto it
   means rewriting the catalog's ordering — the portal's hottest query.
2. Expiry is a scheduled job, not a read-time computation. A read-time check would
   make every catalog query evaluate expiry for every row, and would never fire the
   30/14/7/3-day notifications at all.
3. Package changes, bumps, and pins each invalidate catalog caches for the affected
   scopes. Get the invalidation keys from
   [l1-object-catalog.md](l1-object-catalog.md) §6.3 rather than inventing a second
   scheme.
4. Never let package state influence anything on the object page beyond the badge and
   border. The moment a package gates content, §3.2 is broken and the simplicity that
   makes this model cheap is gone.
5. [ADDED — v0.2.0] Route every grant, pin, and unpin through the one service that
   writes placement history, including the scheduled expiry action. A second write path
   — a bulk action reaching the tables directly, say — produces exactly the gap §3.6
   exists to close: history that is append-only everywhere except the one surface that
   skipped it.
6. [ADDED — v0.2.0] Decide the grant's permission scope in the policy, not in the
   action's visibility. §3.6 makes granting scopable, and a scoped grant enforced only
   by which button renders is not enforced at all.

## 7. Drawbacks & Alternatives

**Feature-gated packages (more photos, more contacts, video only on premium).** The
industry-standard directory model, and explicitly rejected by `[TZ]` §25 and §55.
Beyond compliance, it is also the more expensive design: it spreads entitlement
checks across every surface and turns every downgrade into a data-retention question.

**A single global bump timestamp.** Much simpler and wrong per `[TZ]` §25.3 — an
object bumped once would jump to the top of its tier on every page in the portal
simultaneously, which is both unsold value and unfair to other advertisers in scopes
the owner did not pay for.

**Integrating a payment gateway for owner self-service purchase.** Genuinely useful
and out of scope: `[TZ]` §122 describes manual, administrator-entered records with
document numbers and responsible staff, and §64 lists online payment as a future
module. When that module arrives
([l1-feature-modules.md](l1-feature-modules.md)), this ledger is the surface it
writes into — the schema here is deliberately shaped to accept it.

**Continuous scoring instead of four discrete tiers.** More flexible for ranking and
far harder to sell: an advertiser buying "VIP" expects a visible, categorical
position, not a coefficient. `[TZ]` §25.1's four ranks are a commercial decision, not
a technical one.

## Canonical References

| Alias | Path | Purpose |
| --- | --- | --- |
| `[TZ]` | `.drafts/booking.md` | §25, §26, §54–§63, §79–§81, §99, §101, §105, §111–§112, §122–§123, §129, §133 — source requirements. |
| `[L1]` | `.design/main/specifications/l1-platform-foundation.md` | Paid-placement-ordering and package-parity invariants. |

## Document History

| Version | Date | Change |
| --- | --- | --- |
| 0.1.0 | 2026-08-05 | Initial draft derived from the client technical specification. |
| 0.1.1 | 2026-08-05 | Patch: translated quoted `[TZ]` tier names and terminology from Russian to English per the project's language policy; no meaning changed. |
| 0.2.0 | 2026-08-26 | Minor: specified the act of granting a placement, which the document assumed throughout and never defined — new §3.6 Granting and §5.6 Grant Flow, the third diagram alongside §5.3's bump and §5.4's expiry. Split §3.1's position override into the scopable within-tier placement `[TZ]` §112 grants to any administrator and the cross-tier override §25.2 reserves to the chief administrator; the previous wording gave both to the chief administrator, which was narrower than the source. Added the pin's inverse (§3.1), a server-side refusal for an ineligible bump (§3.3), the ordering effect of the ledger statuses (§5.5) — the six were enumerated but none was ever given an effect on whether an object ranks — and §6 notes 5–6 on the single write path and where the grant's scope check belongs. §3.2's package parity is unchanged and §7 still rejects feature-gated packages by name: a QA fix specification proposed enforcing per-package promotion, news, and photo entitlements, and that proposal is refused here as contradicting `[TZ]` §25 line 850, §79, and §111, which this document already codified. §5.2's ordering contract is unchanged; no invariant removed. |
