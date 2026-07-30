# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-07-30 19:54
**Phase:** 2 — Identity & Back Office
**Status:** Active

## Current Position

- **Task:** none started — Phase 2 not yet decomposed into tasks
- **Spec:** l2-third-party-integrations.md §5.1 (Better Auth), §5.3 (AdminJS)
- **Next Action:** Plan complete — run /magic.task main to plan new scope

## Progress

```
Phase 1: [14/14] ████████ 100%  DONE — archived
Overall: [1/6]   █░░░░░░░ 17%   (1 of 6 planned phases complete)
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-07-30 **Decision:** Phase 1 complete (14/14) and archived. Provides: full Next.js scaffold, 14-table Drizzle schema (Better-Auth-ready), local Postgres, root layout shell with i18n/404/privacy/feedback, responsive nav. Track T validated both entity model and shell invariants; fallow audit clean (0 circular deps, 0 boundary violations). See `.design/main/CHANGELOG.md` for the full phase summary and `RETROSPECTIVE.md` for the L1 snapshot (🟢).
- 2026-07-30 **Decision:** Track C complete (T-1C01-04) — verified with a real browser (chrome-devtools MCP) at 375px/1280px, not just class-presence checks.
- 2026-07-30 **Decision:** Local dev Postgres via `docker-compose.yml` (postgres:18-alpine, host port 5433 — 5432 is taken by a pre-existing native PostgreSQL 18 service on this machine).
- 2026-07-30 **Decision:** Dependency policy — keep the whole stack on latest, including major bumps, not conservatively pinned. Fix real breakage as it surfaces rather than reverting versions.
- 2026-07-30 **Pattern:** Async Server Components calling `getTranslations` (next-intl/server) cannot be unit-tested with Vitest/RTL — verify via a live dev-server/browser instead.

## Blockers

<!-- Empty if none. Format: [severity] description -->

- none

## Blocking Constraints

<!-- Anti-patterns discovered through real failures. MANDATORY reading. -->
<!-- Agent MUST explicitly acknowledge each constraint before working. -->

- none recorded

## Session Continuity

**Last Session Ended:** 2026-07-30 19:54
**Handoff File:** none
**Bootstrap Mode:** false
