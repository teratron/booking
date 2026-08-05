# Implementation Plan

**Version:** 2.0.0
**Generated:** 2026-08-05
**Based on:** .design/main/INDEX.md v2.1.0
**Status:** Awaiting generation

## Overview

Delivery plan for the international tourism portal — a self-hosted Laravel 13 monolith
serving visitors (browse and contact object owners), object owners (self-service
cabinet), and portal staff (back office), across three countries and two launch
languages.

**This plan has not yet been generated.** The specification set was re-baselined
against the client technical specification and then re-targeted onto an approved stack
change (Laravel 13 + Filament 5 + PostgreSQL/PostGIS + Redis). All 23 specifications
are `RFC` pending review; planning begins once that review completes.

The previous plan — six phases delivered against a Next.js/TypeScript implementation of
the superseded hotel-booking product — is archived at
[archives/PLAN-v1-nextjs.md](archives/PLAN-v1-nextjs.md) with its task ledger at
[archives/TASKS-v1-nextjs.md](archives/TASKS-v1-nextjs.md). That implementation is
preserved at git tag `v0.1.34`.

## Blocking Gates

**None.** Both gates that previously stood ahead of backend development are closed.

| Gate | Status |
| --- | --- |
| Deployment target (managed vs self-hosted) | **Closed** — self-hosted ([l2-tech-stack.md](specifications/l2-tech-stack.md) §5.10) |
| `[TZ]` §98 — client approval of the database structure | **Waived** — the client delegates the design to engineering judgment ([l2-data-model.md](specifications/l2-data-model.md) §5.7) |

The §98 waiver removes the approval step, not the artefacts: the field list, data
types, and keys are expressed as the Laravel migration set verified by
`migrate:fresh --seed`, and the ER diagram is generated from the applied schema.

The critical path now runs straight from specification review into scaffolding.

## Client-Stated Delivery Stages

`[TZ]` §23, recorded in [l1-platform-foundation.md](specifications/l1-platform-foundation.md) §5.4:

```plaintext
1. Specification         ← current; 23 specs at RFC
2. Visual design         ← Figma source exists
3. Backend
4. Frontend
5. Owner cabinet         -> Filament cabinet panel
6. Back office           -> [TZ] §134 priority order
7. SEO
8. Testing
9. Content population    ← import pipeline is release-one scope
10. Launch
```

## Next Step

Review the RFC specification set, then run `/magic.task main` to generate phases
against it.

Recommended reading order:

1. [l1-platform-foundation.md](specifications/l1-platform-foundation.md) §5.3 — what changed and why
2. [l2-tech-stack.md](specifications/l2-tech-stack.md) §1, §5.5 — stack rationale and package set
3. [l2-data-model.md](specifications/l2-data-model.md) §5.7 — schema deliverables (approval waived)
4. [l1-back-office.md](specifications/l1-back-office.md) §5.8 — first-release scope per `[TZ]` §134
