# Master Task Index (Registry)

**Version:** 1.0.0
**Generated:** 2026-07-30
**Based on:** .design/main/PLAN.md v1.0.0
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
| [Phase 1](tasks/phase-1.md) | Project scaffold, complete entity model, and the app shell every route inherits | `Blocked` |

**Phase 1 unblock action**: amend `l2-tech-stack.md` §4 (actor-roles and
moderation-checkpoint rows), §5.6 (project structure omits the admin mount point
and the auth module), and §6.4 (instructs against scaffolding auth) to match
`l1-platform-foundation.md` v0.2.0 and `l2-third-party-integrations.md`
§5.1/§5.3. Run `/magic.spec`, then re-run `/magic.task`.

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
