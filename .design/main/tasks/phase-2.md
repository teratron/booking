---
phase: 2
name: "Back Office Core"
status: Todo
subsystem: "app/Filament/Admin, app/Services, app/Policies"
requires: ["phase-1"]
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 2 Tasks — Back Office Core

**Phase:** 2
**Status:** Todo
**Strategic Goal:** A staff panel a portal can actually be operated from — objects,
owners, geography, taxonomy, translations, moderation, and the action journal — built
on Phase 1's scoped authorization rather than around it.

## Atomic Checklist

Not yet decomposed. See §Scope.

## Scope

Delivered in `[TZ]` §134 priority order, as Filament resources rather than custom pages.

| Area | Spec |
| --- | --- |
| Panel shell, navigation filtered by permission, dashboard | l1-back-office.md §5.1, §5.3 |
| Object management — list, tabbed form, bulk operations | l1-back-office.md §5.4 |
| Owner management, including journalled impersonation | l1-back-office.md §5.5 |
| Portal settings and the module management screen | l1-back-office.md §5.6; l1-feature-modules.md §5.6 |
| Moderation queue, side-by-side diff review, decisions | l1-moderation-governance.md §5.1–§5.3 |
| Action journal — search, filter, before/after, export | l1-moderation-governance.md §5.4 |
| Archive, restore, permanent deletion, confirmation gates | l1-moderation-governance.md §3.3, §3.4, §5.5 |
| Object type registry administration | l1-object-catalog.md §3.1 |
| Territory administration with guarded reparenting | l1-geography.md §5.5 |
| Interface catalogs and translation management | l1-localization.md §5.4, §5.5 |
| Availability override, staleness filters, bulk reset | l1-availability-status.md §5.4, §5.5 |

## Standing Constraints

- Every action is permission-checked server-side against Phase 1's scoped resolution.
  Hiding a Filament action is a usability affordance and never an access control.
- Permissions are registered as Filament resource policies, never as inline `visible()`
  closures.
- Moderation acts on **changes**, not on records: a pending revision never overwrites
  the published version, so a rejected edit cannot damage a live page.
- Bulk actions require a confirmation naming the affected record count.
- Impersonation is journalled without exception — it grants an administrator the full
  authority of another account.

## Decomposition Trigger

Decomposed into atomic `T-2XXX` tasks by `/magic.task main` once Phase 1 completes,
against the specification set as it stands at that point.
