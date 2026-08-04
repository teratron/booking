# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-04 21:24
**Phase:** 6 - Room Reservation and Payment
**Status:** Active

## Current Position

- **Task:** Phase 6 complete (6/6) — the last phase this project's plan scheduled
- **Spec:** l1-room-reservation.md v0.2.0 + l2-third-party-integrations.md v0.2.0 §5.2 — all 6 tasks DB-verified and live-checked
- **Next Action:** Plan complete — no Draft/unregistered specs remain (9/9 Stable, Backlog empty). Author new scope via /magic.spec, or /magic.status for a briefing.

## Progress

```
Phase 1: [14/14] ████████ 100%  DONE — archived
Phase 2: [11/11] ████████ 100%  DONE — archived
Phase 3: [8/8]   ████████ 100%  DONE — archived
Phase 4: [7/7]   ████████ 100%  DONE — archived
Phase 5: [6/6]   ████████ 100%  DONE — archived
Phase 6: [6/6]   ████████ 100%  DONE — archived
Overall: [6/6]   ████████ 100%  (6 of 6 planned phases complete)
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-05 **Decision:** Phase 6 (and the full 6-phase plan) closed. T-6T01's live browser check surfaced and fixed two real defects no automated test caught: a Base UI `nativeButton` console error on the "Оплатить" button-as-Link, and a genuinely deterministic (not flaky) bug in `reservation-query.test.ts` — every test in that file shares one fixed `guestId` but only cleaned up in one file-level `afterAll`, so an earlier test's own rows leaked into a later exact-count assertion. Full suite: 52 files / 158 tests, 0 failures.
- 2026-08-04 **Note:** Docker/Postgres access RESTORED — `docker` CLI reappeared on PATH, daemon was stopped, started via Docker Desktop + `docker compose up -d`; the named `postgres-data` volume preserved the schema through container recreation.
- 2026-08-04 **Decision:** T-6C01 (payment) built via an orchestrated multi-agent workflow (Ultracode posture) — 4 agents built provider/persistence/UI/tests in dependency order, 3 agents adversarially reviewed (correctness, auth, conventions) and found 8 real issues, all confirmed and fixed. See `archives/tasks/phase-6.md` T-6C01 Changes.

## Blockers

<!-- Empty if none. Format: [severity] description -->

(none)

## Blocking Constraints

<!-- Anti-patterns discovered through real failures. MANDATORY reading. -->
<!-- Agent MUST explicitly acknowledge each constraint before working. -->

- **Router Cache staleness / stale `useState` after auth mutations:** pair a
  client-side sign-up/sign-in/sign-out redirect with `router.refresh()` (a
  plain `fetch()` doesn't invalidate the Router Cache); also reset
  "pending/loading" `useState` flags in the success path, not just on error.
  Discovered T-2B03.
- **`biome.json` breaks on a `//` comment before `"overrides"`** — verify
  with unscoped `pnpm exec biome check .`. Discovered T-2C02.
- **`src/components/` has no clean vendor/first-party boundary** — a new
  first-party file there needs both `biome.json`/`.fallowrc.jsonc` negation
  lists updated.
- **Engine bug:** `executor.js update-state` corrupts STATE.md fields on
  nearly every invocation (top-level `**Status:**`, `## Progress`). Always
  re-open STATE.md after `update-state`/`finalize` and manually verify both.
- **Server Actions must live in their own file**, AND a mutation must never
  run as a side effect of a GET-triggered Server Component render. Split
  three ways: schema (client-safe), persistence (db logic), actions
  (`"use server"`). Reference: `src/lib/property-onboarding/*`. T-3B02/T-6C01.
- **An UPDATE's own precondition must be atomic with the write** — fold it
  into that UPDATE's `WHERE` clause + `returning().length`, not a separate
  pre-transaction SELECT. Discovered T-6C01.
- **`pnpm test -- <path>` does not reliably scope** — use `pnpm exec vitest
  run <path>`. Stop the dev server before `pnpm test`. Running two
  `pnpm exec <cmd>` processes concurrently can EPERM-fail on a shared
  dependency install — run pnpm-wrapped commands sequentially.
- **Vitest's parallel workers share one real, non-transactional Postgres** —
  an unscoped query in one file can see another's in-flight rows. A test
  file whose tests share one fixed fixture `guestId` must clean up
  per-test (`afterEach`), not only in one file-level `afterAll` — an
  earlier test's own rows for that id are otherwise still present later.
  `reservation.guest_id`/`hotel.owner_id` have no `onDelete: cascade` — use
  `deleteTestUsers` (cleans up both). T-4C01/T-6B02/T-6T01.
- **shadcn `Button` rendered as a Link** (`render={<Link/>}`) needs
  `nativeButton={false}` — Base UI's `Button` otherwise assumes its root DOM
  node is a native `<button>`. Discovered T-6T01 (pre-existing since Phase 3,
  unfixed there — cross-phase, left alone).

## Session Continuity

**Last Session Ended:** 2026-07-30 19:54
**Handoff File:** none
**Bootstrap Mode:** false
