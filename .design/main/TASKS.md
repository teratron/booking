# Master Task Index (Registry)

**Version:** 1.1.0
**Generated:** 2026-07-30
**Based on:** .design/main/PLAN.md v1.1.0
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
| [Phase 1](tasks/phase-1.md) | Project scaffold, complete entity model, and the app shell every route inherits | `Todo` |

Execution order within the phase is `A → (B ‖ C) → T`: Tracks B and C never touch
the same files and run in parallel, but both need Track A's scaffold on disk
first. Track T validates the phase before it closes.

## Planned Phases

Registered in `PLAN.md`; decomposed into atomic tasks when activated.

| Phase | Description | Status |
| --- | --- | --- |
| Phase 2 | Better Auth (guest/owner/admin) and the AdminJS moderation queue | `Planned` |
| Phase 3 | Owner-gated Add-Hotel intake flow with moderation lifecycle | `Planned` |
| Phase 4 | Hero search, catalog filters/sort/pagination, map view | `Planned` |
| Phase 5 | Hotel profile aggregation and the article/news content pipeline | `Planned` |
| Phase 6 | Room detail, date/guest selection, and the paid booking flow | `Planned` |

## Meta Information

- **Last Updated**: 2026-07-30
- **Maintainer**: Core Team
