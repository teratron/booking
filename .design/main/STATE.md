# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-01 12:41
**Phase:** 6 - Room Reservation and Payment
**Status:** Active

## Current Position

- **Task:** T-6A01/B01/D01/B02 code complete (4/6), DB verification deferred
- **Spec:** l1-room-reservation.md v0.2.0 + l2-third-party-integrations.md v0.2.0 §5.2 — decomposed into 6 tasks across 3 tracks
- **Next Action:** T-6C01 Payment integration (simulated provider) via magic.run main

## Progress

```
Phase 1: [14/14] ████████ 100%  DONE — archived
Phase 2: [11/11] ████████ 100%  DONE — archived
Phase 3: [8/8]   ████████ 100%  DONE — archived
Phase 4: [7/7]   ████████ 100%  DONE — archived
Phase 5: [6/6]   ████████ 100%  DONE — archived
Phase 6: [4/6]   █████░░░ 67%   IN PROGRESS (code; DB verification deferred)
Overall: [5/6]   ███████░ 83%   (5 of 6 planned phases complete)
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-01 **Decision:** Phase 6 payment (`T-6C01`) is built behind a swappable `PaymentProvider` interface with a simulated implementation — no real Fondy sandbox credentials exist in this environment (confirmed with the user). Real Fondy wiring later is a drop-in implementation swap. See `tasks/phase-6.md` Decisions for all four [DR] resolutions.
- 2026-08-01 **Decision:** T-6A01/B01/D01/B02 all code-complete (dialog, reservation-creation action, `/account/reservations` page) — every DB-independent gate (tsc/biome/fallow/non-DB suite) clean, zero regressions. All four checklist items stay unchecked deliberately until real DB verification runs (see Blockers) — same rationale as T-6A01 alone previously.
- 2026-08-01 **Note:** this codebase has no client-side `useTranslations`/`NextIntlClientProvider` — Client Components take fully-resolved plain-string label props from their Server Component parent (confirmed via `AuthNav`/`SignInForm`). Follow this for any future Client Component needing translated text.

## Blockers

<!-- Empty if none. Format: [severity] description -->

- [medium] Postgres (`booking-postgres-1`, port 5433) is unreachable this
  session — `docker` isn't found in Bash or PowerShell post-resume. Per user
  direction: keep writing code and running `tsc`/`biome`/`fallow` (DB-free),
  but every DB-backed test and live-server check from T-6A01 onward is
  **deferred, not verified** — don't report those as passing until Postgres
  access is confirmed restored and the suite actually runs green.

## Blocking Constraints

<!-- Anti-patterns discovered through real failures. MANDATORY reading. -->
<!-- Agent MUST explicitly acknowledge each constraint before working. -->

- **Router Cache staleness / stale `useState` after auth mutations:** pair a
  client-side sign-up/sign-in/sign-out redirect with `router.refresh()` (a
  plain `fetch()` doesn't invalidate the Router Cache, so a shared layout
  like `Header` keeps showing stale session state); also reset
  "pending/loading" `useState` flags in the success path, not just on error
  — a conditional-branch prop flip does not remount the component. Both
  discovered live in T-2B03.
- **`biome.json` breaks on a `//` comment before `"overrides"`** (silently
  stops honoring `useIgnoreFile`) — verify with unscoped `pnpm exec biome
  check .`. Discovered live in T-2C02.
- **`src/components/` has no clean vendor/first-party boundary** (shadcn-
  admin-kit flattened ~85 files in directly) — a new first-party file there
  needs both `biome.json`/`.fallowrc.jsonc` negation lists updated.
- **Engine bug:** `executor.js update-state` corrupts STATE.md fields on
  nearly every invocation — most often overwrites the top-level `**Status:**`
  with the single task's `--status` value (wrongly marking the whole
  workspace `Done`), sometimes also collapses `## Progress`. Always re-open
  STATE.md after `update-state`/`finalize` and manually verify both.
- **Server Actions must live in their own file**, separate from schema and
  persistence: a "use server" fn sharing a file with server-only helpers
  (anything importing `db`) breaks the build the moment a Client Component
  imports it — even after splitting, importing anything else from the
  persistence file pulls `db`→`pg`→Node builtins into the client bundle, a
  runtime 500 `tsc`/Vitest never catch. Split three ways: schema
  (client-safe), persistence (db logic), actions (`"use server"`). Reference:
  `src/lib/property-onboarding/{schema,submit-listing,actions}.ts`. T-3B02.
- **`pnpm test -- <path>` does not reliably scope to one file** — use
  `pnpm exec vitest run <path>` instead.
- **Stop the dev server before `pnpm test`.** A concurrent `pnpm dev`
  competes with Vitest's worker threads for CPU, causing flaky timeouts
  (85s w/ failures → 40s w/ none, confirmed by A/B). Pool cap under
  `process.env.VITEST` + `testTimeout: 15000` are secondary mitigations only.
  Discovered T-3B02, root-caused Phase 4 planning.
- **Multi-render test files need explicit `afterEach(() => cleanup())`**
  (`@testing-library/react`) — not wired in automatically. Also: Vitest runs
  test files in parallel against one real, non-transactional Postgres —
  an unscoped query in one file can see another file's in-flight fixture
  rows. Prefer mocking the query layer for page/component tests that don't
  need to re-prove DB correctness a lower-level test covers. T-4C01.
- **`reservation.guest_id`/`hotel.owner_id` have no `onDelete: cascade`** —
  use the shared `deleteTestUsers` helper (cleans up both) for any test
  creating either row, not bespoke local cleanup. Discovered live in T-6B02.

## Session Continuity

**Last Session Ended:** 2026-07-30 19:54
**Handoff File:** none
**Bootstrap Mode:** false
