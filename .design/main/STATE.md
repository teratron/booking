# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-07-30 21:14
**Phase:** 2 — Identity & Back Office
**Status:** Active

## Current Position

- **Task:** T-2B01 Split the root layout so the admin surface sheds the marketing chrome (Done)
- **Spec:** l2-third-party-integrations.md v0.2.0 — §5.1 (Better Auth), §5.3 (react-admin)
- **Next Action:** Execute T-2B02 (auth UI) and T-2C01 (admin REST surface) — now genuinely file-disjoint

## Progress

```
Phase 1: [14/14] ████████ 100%  DONE — archived
Phase 2: [5/11]  ███░░░░░ 45%   (Track A complete; T-2B01 done, no blockers)
Overall: [1/6]   █░░░░░░░ 17%   (1 of 6 planned phases complete)
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-07-30 **Decision:** Track A complete (T-2A01-04): Better Auth wired against Phase 1's schema, role escalation blocked and tested empirically (`role: "admin"` in a sign-up payload is rejected server-side, confirmed via direct DB read), session/role-gate helpers (`src/lib/auth/session.ts`) ready for Tracks B and C.
- 2026-07-30 **Pattern:** `session.ts` helpers take a plain `Headers` object rather than calling `next/headers` internally — keeps them framework-agnostic and directly Vitest-testable; call sites supply `await headers()`.
- 2026-07-30 **Decision:** §5.3 back office resolved — **react-admin via shadcn-admin-kit** (marmelab first-party, built on `ra-core`, renders through shadcn/Radix): official App Router support *and* this project's existing design system.
- 2026-07-30 **Pattern:** The admin surface is client-side, so `app/api/admin/` is the security boundary — every request must require `role = 'admin'` server-side. Hiding a control in the admin UI is never access control.
- 2026-07-30 **Pattern:** Phase 1 put Header/Footer in the root layout, which wraps *every* route — correct then, wrong once an operator back office shares the app. `T-2B01` moves the marketing chrome into a `(marketing)` route group before the admin surface mounts.

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
