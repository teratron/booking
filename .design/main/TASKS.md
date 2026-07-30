# Master Task Index (Registry)

**Version:** 1.3.0
**Generated:** 2026-07-30
**Based on:** .design/main/PLAN.md v1.3.0
**Based on RULES:** .design/RULES.md v1.4.0
**Execution Mode:** Parallel
**Status:** Active

## Overview

Tactical registry of all phases and their statuses. Check individual phase files
in `tasks/` for atomic checklists. Only the active phase is decomposed into atomic
tasks; later phases are decomposed when they become active.

## Active Phases

| Phase | Description | Status |
| --- | --- | --- |
| [Phase 1](archives/tasks/phase-1.md) | Project scaffold, complete entity model, and the app shell every route inherits | `Done (Archived)` (14/14) |
| [Phase 2](tasks/phase-2.md) | Better Auth identity for guest/owner/admin, plus the react-admin moderation back office | `Todo` (0/11) |

Phase 1 ran `A → (B ‖ C) → T` and is summarized in `.design/main/CHANGELOG.md`.

Phase 2 runs `A → B01 → (B02–B03 ‖ C01–C03) → T`. Track A (auth core) is the
critical path — both other tracks import from it. `T-2B01` (splitting the
marketing chrome into a route group) is sequenced early because Track C's admin
surface must not inherit it *and* because it touches the same layout/header files
Track B does; after it, the auth UI and the back office are genuinely
file-disjoint and run in parallel.

The earlier `Blocked [!]` on the back office is cleared —
`l2-third-party-integrations.md` §5.3 was amended to v0.2.0 (react-admin via
shadcn-admin-kit), and Track C is decomposed into real work.

## Planned Phases

Registered in `PLAN.md`; decomposed into atomic tasks when activated.

| Phase | Description | Status |
| --- | --- | --- |
| Phase 3 | Owner-gated Add-Hotel intake flow with moderation lifecycle | `Planned` |
| Phase 4 | Hero search, catalog filters/sort/pagination, map view | `Planned` |
| Phase 5 | Hotel profile aggregation and the article/news content pipeline | `Planned` |
| Phase 6 | Room detail, date/guest selection, and the paid booking flow | `Planned` |

## Meta Information

- **Last Updated**: 2026-07-30
- **Maintainer**: Core Team
