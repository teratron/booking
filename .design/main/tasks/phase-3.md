---
phase: 3
name: "Commerce, Advertising & Platform Services"
status: Todo
subsystem: "app/Services, app/Jobs, app/Filament/Admin"
requires: ["phase-1", "phase-2"]
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 3 Tasks — Commerce, Advertising & Platform Services

**Phase:** 3
**Status:** Todo
**Strategic Goal:** The revenue mechanics and the background machinery both panels and
the public site depend on — placement ordering, bumps, banner targeting, analytics
ingest, notifications, and the content pipeline.

## Atomic Checklist

Not yet decomposed. See §Scope.

## Scope

| Area | Spec |
| --- | --- |
| Placement tiers, packages, current placement, append-only history | l1-placement-monetization.md §5.1 |
| The catalog ordering expression — tier-first, scope-local | l1-placement-monetization.md §5.2 |
| Bump engine — scoped per territory and category, with limits | l1-placement-monetization.md §5.3 |
| Expiry sweep and the configured expiry action | l1-placement-monetization.md §5.4 |
| Financial ledger | l1-placement-monetization.md §5.5 |
| Banners, slots, targeting, specificity ranking, rotation | l1-advertising.md §5.1–§5.3 |
| Promotional labels and card decoration rules | l1-advertising.md §5.4, §5.5 |
| Batched analytics ingest, daily rollup, compaction | l1-analytics.md §5.1–§5.3 |
| Notification and dispatch model, channel adapters, templates | l1-notifications.md §5.1–§5.3 |
| Scheduled jobs — expiry, staleness, availability, archival, retry | l1-notifications.md §5.4 |
| Articles, news, promotions and their shared publication pipeline | l1-content-publishing.md §5.1, §5.2, §5.4 |

## Standing Constraints

- **Catalog ordering is placement-tier first.** A lower-tier object must never outrank
  a higher-tier one except by an explicit administrator pin, and the pin is journalled.
  This is not a relevance-ranking problem to be improved into one — inverting it breaks
  the revenue model.
- A bump moves an object to first position **within its own tier**, in the scope being
  viewed, never above the tier and never globally.
- Scheduled work belongs in queued jobs dispatched by the scheduler — never executed
  during a web request.
- Measurement never delays or blocks the interaction it measures, and an analytics
  write may never fail a page render.
- Banner slots collapse when nothing is eligible; an empty frame is worse than no frame.

## Decomposition Trigger

Decomposed into atomic `T-3XXX` tasks by `/magic.task main` once Phase 2 completes.
