# Platform Shell

**Version:** 0.1.0
**Status:** Stable
**Layer:** concept

## Overview

The chrome shared by every page: header navigation, language switcher, mobile
menu, footer, the feedback popup overlay, the 404 page, and the privacy-policy
page. Evidenced by the recurring header/footer in every frame plus the dedicated
`меню моб`, `404`, `политика конфиденциальности`, and
`поп ап обратная связь` frames.

## Related Specifications

- [l1-platform-foundation.md](l1-platform-foundation.md) - Localization and responsive-parity invariants.
- [l1-hotel-discovery.md](l1-hotel-discovery.md) - Primary nav destination (Catalog / Map).
- [l1-content-publishing.md](l1-content-publishing.md) - Primary nav destination (Blog).
- [l1-property-onboarding.md](l1-property-onboarding.md) - Footer CTA destination (Add Hotel).

## 1. Motivation

Header, footer, and error/legal pages appear identically across every other
domain spec's frames; specifying them once prevents six other specs from each
re-describing the same nav bar.

## 2. Constraints & Assumptions

- Header nav items observed: Catalog, Map, Blog, plus a language switcher
  currently showing "Ру" (Russian) only.
- Footer contains: brand mark, an "About" link, contact details (phone, email),
  social links (Instagram, Telegram, Facebook), and an "Add Hotel" call to
  action.

## 3. Core Invariants (Layer 1 only)

- Header navigation (Catalog, Map, Blog) and the language switcher are present
  on every page, in both desktop and mobile presentations (mobile collapses to
  a hamburger menu).
- The footer's contact details, social links, and "Add Hotel" call to action
  are present on every page.
- A dedicated 404 page renders for any unresolved route, in both desktop and
  mobile presentations.
- A privacy-policy page exists as static legal content, linked from the footer
  or an equivalent persistent location.
- A feedback popup overlay is available as a shared component invokable from
  other domain surfaces (observed invoked from the room-detail popup in
  [l1-room-reservation.md](l1-room-reservation.md)).
- The language switcher's presence is a structural commitment to future
  localization even though only Russian ships initially (see
  [l1-platform-foundation.md](l1-platform-foundation.md)).

## 5. Detailed Design

### 5.1 Shell Composition

```plaintext
Header: [Logo] [Catalog] [Map] [Blog]            [Language switcher]
Page content (per domain spec)
Footer: [Brand] [About] [Contact] [Social] [Add Hotel CTA]

Overlays: Feedback popup (invokable from any page)
Standalone routes: 404, Privacy Policy
```

## 6. Implementation Notes

1. Build the header/footer as a layout shell wrapping every route, not per-page
   markup, so all seven domain specs inherit it automatically.

## 7. Drawbacks & Alternatives

None identified; this spec consolidates unambiguous, uniformly-repeated chrome
with no observed variation across frames.

## Canonical References

| Alias | Path | Purpose |
| --- | --- | --- |
| `[FIGMA-404]` | `https://www.figma.com/design/N2cVVIS5wvjHIviP27peuX/Booking?node-id=85-1278` | 404 page frame. |
| `[FIGMA-PRIVACY]` | `https://www.figma.com/design/N2cVVIS5wvjHIviP27peuX/Booking?node-id=85-1797` | Privacy policy frame. |

## Document History

| Version | Date | Change |
| --- | --- | --- |
| 0.1.0 | 2026-07-30 | Initial draft derived from recurring header/footer/overlay frames. |
