# Platform Shell

**Version:** 0.2.0
**Status:** RFC
**Layer:** concept

## Overview

The chrome shared by every public page: header navigation across the portal's many
sections, the language and country switchers, the global search entry, the mobile
menu, the footer, the feedback overlay, the 404 page, and the static legal pages.
Derived from `[TZ]` §2, §4–§5, §20, and the recurring header/footer in every Figma
frame.

[MODIFIED — v0.2.0] Widened for the portal scope: navigation now spans seventeen-plus
sections rather than three, and the language switcher is joined by a country switcher
— both backed by real registries rather than stubbed.

## Related Specifications

- [l1-platform-foundation.md](l1-platform-foundation.md) - Localization-completeness and responsive-parity invariants.
- [l1-localization.md](l1-localization.md) - Backs both switchers; owns resolution and fallback.
- [l1-geography.md](l1-geography.md) - Country switcher targets and the territory navigation beneath it.
- [l1-object-catalog.md](l1-object-catalog.md) - Destination of the header search and of every category entry.
- [l1-content-publishing.md](l1-content-publishing.md) - Destination of the news, promotions, and blog entries.
- [l1-object-onboarding.md](l1-object-onboarding.md) - Destination of the "add object" call to action.
- [l1-seo.md](l1-seo.md) - Breadcrumbs and alternate-language links live in this shell.
- [l1-advertising.md](l1-advertising.md) - Home page and header-adjacent banner slots.

## 1. Motivation

Header, footer, and error pages appear identically across every other domain spec's
surfaces; specifying them once prevents thirteen specs from each re-describing the
same navigation. The shell also carries two elements with real behaviour rather than
decoration — the language and country switchers — whose correctness determines whether
every other page resolves the content the visitor expects.

## 2. Constraints & Assumptions

- Navigation must accommodate a section list that grows: object types are
  administrator-managed data ([l1-object-catalog.md](l1-object-catalog.md) §3.1), so
  the menu cannot be a fixed list in markup.
- Four viewport classes are in scope — phone, tablet, laptop, desktop
  (`[TZ]` §20).
- The country switcher changes the browsing scope; it does **not** change the
  language ([l1-localization.md](l1-localization.md) §3).
- <!-- TBD: whether selecting a country navigates to that country's landing page or
     re-scopes the current page in place is not stated in [TZ]. Modeled below as
     navigation to the country landing page, which is unambiguous and always valid;
     in-place re-scoping fails for object pages, which belong to one country. -->

## 3. Core Invariants (Layer 1 only)

- **The shell is present on every public page** in all four viewport classes, with the
  mobile presentation collapsing navigation into a menu (`[TZ]` §5, §20).
- **Both switchers are always reachable.** The language switcher lists every active
  language; the country switcher lists every active country
  ([l1-localization.md](l1-localization.md) §5.1).
- **Switching language preserves position.** Changing language navigates to the same
  content's URL in the target language, never to the home page — this is the same
  alternate link [l1-seo.md](l1-seo.md) §3.3 emits.
- **Navigation is data-driven.** Menu entries derive from the active object-type and
  content registries, so adding an object type adds a navigation entry without a code
  change.
- **Global search is present in the header** on every page and submits into the
  catalog ([l1-object-catalog.md](l1-object-catalog.md) §5.1).
- **Breadcrumbs are present on every page below the home page**, and every crumb is a
  link (`[TZ]` §13).
- **A 404 page renders for any unresolved route**, in all viewport classes, and is
  itself never indexable.
- **Static legal pages exist** — privacy policy and terms of use — linked persistently
  (`[TZ]` §4).
- **The feedback overlay is a shared component** invokable from any page.

## 5. Detailed Design

### 5.1 Shell Composition

```plaintext
Header
├── Logo
├── Country switcher            -> l1-geography
├── Language switcher           -> l1-localization
├── Primary navigation (data-driven)
│   ├── Accommodation (grouped object types)
│   ├── Dining (grouped object types)
│   ├── Entertainment · Attractions
│   ├── News · Promotions · Blog
│   └── About · Contacts
├── Global search               -> l1-object-catalog
└── Owner entry (sign in / cabinet)

Breadcrumbs                     -> l1-seo

Page content (per domain spec)

Footer
├── Brand · about
├── Contact details
├── Social links
├── Popular destinations        -> l1-geography
├── Object categories           -> l1-object-catalog
├── Legal: privacy policy · terms of use
└── "Add your object" call to action  -> l1-object-onboarding

Overlays          Feedback popup (invokable from any page)
Standalone routes 404, privacy policy, terms of use, about, contacts
```

Per `[TZ]` §4 the navigable section list spans country, region, city, and resort
catalogs; hotel, apartment, guest house, cottage, sanatorium, restaurant, café, bar,
entertainment, ski resort, and camping catalogs; plus news, articles, promotions,
blog, contacts, about, privacy policy, and terms of use. That list is too large for a
flat menu bar — §5.2 groups it.

### 5.2 Navigation Grouping

Object types carry a parent association
([l1-object-catalog.md](l1-object-catalog.md) §3.1), and the menu renders that
hierarchy: top-level groups (accommodation, dining, entertainment, attractions)
expand to their child types. Because the grouping comes from the type registry,
adding "glamping" under accommodation is a data operation that appears in the menu
automatically.

### 5.3 Switcher Behaviour

```mermaid
graph TD
    A[Visitor selects a language] --> B[Resolve the current entity's slug in the target language]
    B --> C{Translation exists?}
    C -->|yes| D[Navigate to the alternate URL]
    C -->|no| E[Navigate to the nearest translated ancestor, e.g. the territory page]
    F[Visitor selects a country] --> G[Navigate to that country's landing page in the current language]
    G --> H[Store the country preference for subsequent scoped views]
```

The fallback in step E matters: sending a visitor to the home page because one object
lacks a Georgian translation loses their context entirely, while its city page in
Georgian is still a useful answer.

### 5.4 Responsive Behaviour

| Viewport | Header | Navigation | Footer |
| --- | --- | --- | --- |
| Phone | Logo, search icon, menu button; switchers inside the menu | Full-screen drawer, groups collapsed | Stacked, accordion sections |
| Tablet | Logo, search field, menu button; switchers inline | Drawer with groups expanded | Two columns |
| Laptop | Full header, inline navigation | Menu bar with dropdown groups | Multi-column |
| Desktop | Full header, inline navigation | Menu bar with dropdown groups | Multi-column |

## 6. Implementation Notes

1. Build the shell as a layout wrapping every public route, so all domain specs
   inherit it rather than reproducing it.
2. Derive navigation from the type and content registries at render, cached and
   invalidated on registry change — a hard-coded menu will diverge from the catalog
   the first time an administrator adds a type.
3. The switchers appear on every page and must not each trigger a registry query;
   cache the active language and country lists globally.
4. Emit the language alternates once, in the shell, and reuse them for both the
   switcher and [l1-seo.md](l1-seo.md)'s alternate links — two independent
   implementations will disagree.

## 7. Drawbacks & Alternatives

**A flat menu bar listing every section.** Matches `[TZ]` §4's enumeration literally
and is unusable at seventeen-plus entries, especially on tablet. The grouped
navigation in §5.2 preserves reachability without the wall.

**Country as a URL segment on every page instead of a switcher preference.** More
explicit and already partly true — territory pages carry the country in their path
([l1-seo.md](l1-seo.md) §5.1). The switcher preference exists for the pages that have
no country of their own, such as the blog.

**Deferring the country switcher until a second country launches.** Would be the
right call if the countries launched sequentially; `[TZ]` §1.3 launches three at once,
so the switcher is release-one functionality.

## Canonical References

| Alias | Path | Purpose |
| --- | --- | --- |
| `[TZ]` | `.drafts/booking.md` | §2, §4–§5, §20 — source requirements. |
| `[FIGMA-404]` | `https://www.figma.com/design/N2cVVIS5wvjHIviP27peuX/Booking?node-id=85-1278` | 404 page layout. |
| `[FIGMA-PRIVACY]` | `https://www.figma.com/design/N2cVVIS5wvjHIviP27peuX/Booking?node-id=85-1797` | Privacy policy layout. |

## Document History

| Version | Date | Change |
| --- | --- | --- |
| 0.1.0 | 2026-07-30 | Initial draft derived from recurring header/footer/overlay frames. |
| 0.2.0 | 2026-08-05 | Minor: widened navigation to the portal's full section list with data-driven grouping; added the country switcher, global header search, breadcrumbs, terms-of-use page, and the four-viewport responsive matrix. |
