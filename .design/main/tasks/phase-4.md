---
phase: 4
name: "Owner Cabinet"
status: Todo
subsystem: "app/Filament/Cabinet, app/Policies"
requires: ["phase-1", "phase-2", "phase-3"]
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 4 Tasks — Owner Cabinet

**Phase:** 4
**Status:** Todo
**Strategic Goal:** The second Filament panel — the same toolkit and the same interface
conventions as the staff panel, scoped to the authenticated owner in both the base
query and the policy, and usable by someone with no technical training.

## Atomic Checklist

Not yet decomposed. See §Scope.

## Scope

| Area | Spec |
| --- | --- |
| Cabinet panel provider, menu filtered by type, permission, and module state | l1-object-onboarding.md §5.1 |
| Dashboard — package, expiry, tier, position, views, clicks, availability | l1-object-onboarding.md §5.2 |
| Object editing — core, geography, contacts, translations, SEO | l1-object-onboarding.md §5.3 |
| Media management with automatic optimization | l1-object-onboarding.md §5.4 |
| Rooms and period-aware prices (accommodation types only) | l1-object-onboarding.md §5.5 |
| Services selected from the administrator-maintained amenity registry | l1-object-onboarding.md §5.6 |
| Reviews — reply and report only, never edit or delete | l1-object-onboarding.md §5.7 |
| Settings, cabinet language, notification preferences | l1-object-onboarding.md §5.8 |
| Object lifecycle: draft, completeness, moderation, publication | l1-object-onboarding.md §5.9 |
| One-tap availability toggle, no form and no save step | l1-availability-status.md §5.3 |
| Statistics surfaces, including favorite count | l1-analytics.md §5.4 |
| Owner-authored news and promotions | l1-content-publishing.md §3.3, §3.4 |

## Standing Constraints

- Owner scoping is enforced in the resource's base query **and** in the policy — never
  in the UI alone. An owner must never reach another owner's data by guessing an
  identifier.
- Capability does not vary by placement package. Bumping is the single package-gated
  capability; everything else is identical for every owner.
- Module gates are an administrator's operational decision and are never sold as part
  of a placement package. Conflating the two reintroduces capability tiering.
- Administrator activation of a module makes a capability available; it never enrolls
  an owner's object automatically.
- Review deletion is refused server-side, not merely hidden.

## Known Dependency

Statistics surfaces render the aggregate contract from Phase 3's rollup, but carry no
live data until Phase 5 instruments the public surfaces that emit events. Expect empty
charts on completion of this phase — that is the dependency working as designed, not a
defect.

## Decomposition Trigger

Decomposed into atomic `T-4XXX` tasks by `/magic.task main` once Phase 3 completes.
