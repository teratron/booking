# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-07-30 20:42
**Phase:** 2 — Identity & Back Office
**Status:** Active

## Current Position

- **Task:** none started — Phase 2 decomposed (11 tasks) and ready to dispatch
- **Spec:** l2-third-party-integrations.md v0.2.0 — §5.1 (Better Auth), §5.3 (react-admin)
- **Next Action:** Execute T-2A01 Install Better Auth and configure it against the existing schema via /magic.run main

## Progress

```
Phase 1: [14/14] ████████ 100%  DONE — archived
Phase 2: [0/11]  ░░░░░░░░ 0%    (no blockers)
Overall: [1/6]   █░░░░░░░ 17%   (1 of 6 planned phases complete)
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-07-30 **Decision:** Phase 2 re-decomposed against the amended §5.3 — 11 tasks, no blockers. Shape `A → B01 → (B02–B03 ‖ C01–C03) → T`. IDs renumbered rather than `.N`-suffixed: nothing in Phase 2 had executed, so no traceability was lost.
- 2026-07-30 **Decision:** §5.3 back office resolved — **react-admin via shadcn-admin-kit** (marmelab first-party, built on `ra-core`, renders through shadcn/Radix): official App Router support *and* this project's existing design system. Hand-building recorded in §7 as the documented fallback.
- 2026-07-30 **Pattern:** The admin surface is client-side, so `app/api/admin/` is the security boundary — every request must require `role = 'admin'` server-side. Hiding a control in the admin UI is never access control.
- 2026-07-30 **Pattern:** Phase 1 put Header/Footer in the root layout, which wraps *every* route — correct then, wrong once an operator back office shares the app. `T-2B01` moves the marketing chrome into a `(marketing)` route group before the admin surface mounts.
- 2026-07-30 **Decision:** Phase 1 complete (14/14) and archived — scaffold, 14-table schema, local Postgres, shell with i18n/404/privacy/feedback, responsive nav. See `.design/main/CHANGELOG.md` and `RETROSPECTIVE.md` (🟢).

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
