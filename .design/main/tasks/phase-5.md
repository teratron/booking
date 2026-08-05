---
phase: 5
name: "Public Site"
status: Todo
subsystem: "resources/views, app/Livewire, app/Services"
requires: ["phase-1", "phase-2", "phase-3"]
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 5 Tasks — Public Site

**Phase:** 5
**Status:** Todo
**Strategic Goal:** The portal's acquisition surface — server-rendered Blade with
Livewire for catalog interactivity, built node by node against the Figma source, and
instrumented so that the contact click (the portal's only conversion signal) is never
lost.

## Atomic Checklist

Not yet decomposed. See §Scope.

## Scope

| Area | Spec |
| --- | --- |
| Header, data-driven navigation, language and country switchers, footer | l1-platform-shell.md §5.1–§5.3 |
| 404, legal pages, feedback overlay | l1-platform-shell.md §3 |
| Home page — sixteen blocks, four viewport classes, country-aware | l1-home-page.md §5.1–§5.4 |
| Territory landing pages | l1-geography.md §5.3 |
| Catalog — search parameters, filters, pagination, grid and list | l1-object-catalog.md §5.1–§5.3 |
| Clustered map synchronized with the result set | l1-object-catalog.md §5.4 |
| Object profile composition and the sticky contact rail | l1-object-profile.md §5.1–§5.3 |
| Reviews rendering (module-gated) | l1-object-profile.md §5.4 |
| Public blog, news, and promotion surfaces | l1-content-publishing.md §5.3 |
| First-party event emission from every measured surface | l1-analytics.md §3.1, §3.2 |
| Map tiles and CAPTCHA provisioning | l2-third-party-integrations.md §5.3, §5.5 |

## Standing Constraints

- **Every page is built against the Figma node, not against a written description of
  it.** Pull the node, adapt the reference output to Blade and Tailwind, and verify
  against the screenshot. Design tokens go into the Tailwind theme once and are reused;
  no magic values in templates.
- One responsive template per page. Frames exist in desktop and mobile pairs; that is
  two views of one page, not two pages.
- Every user-facing string is translatable. No literal copy in Blade or Livewire.
- Ordering on every listing surface — including the home page's rails — is
  placement-tier first. The home page is the portal's most valuable placement surface;
  exempting it would let a standard-tier object outrank a VIP one where it matters most.
- Every block degrades independently. A block with no content is omitted entirely,
  never rendered as an empty frame.
- **Public OpenStreetMap tile servers are prohibited in production** by the OSMF Tile
  Usage Policy. Ship against MapTiler, Stadia, or self-hosted tiles — never
  `tile.openstreetmap.org`. The previous implementation shipped this violation
  unnoticed, which is how it was found.
- The contact event is emitted before navigation and must not delay it. A measurement
  failure may never cost the visitor the hand-off it exists to measure.

## Decomposition Trigger

Decomposed into atomic `T-5XXX` tasks by `/magic.task main` once Phase 3 completes.
Figma node identifiers are resolved at decomposition time, per page.
