# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-05 06:26
**Phase:** 0 - Concept re-baseline (post-TZ restructure)
**Status:** Active

## Current Position

- **Task:** Specification set restructured against the client technical specification (`.drafts/booking.md`), then verified by a second line-by-line pass. 9 specs → 23; INDEX.md v1.0.0 → v2.0.0.
- **Spec:** All 23 are `RFC` pending review — see `l1-platform-foundation.md` §5.3 for the scope-delta ledger. Coverage verified: all 134 TZ sections cited by at least one spec; registry parity and cross-links clean.
- **Next Action:** Review the RFC set (`l1-platform-foundation.md` §5.3 → `l2-tech-stack.md` §5.1/§5.5/§5.9 → `l2-data-model.md` §5.7). Two decisions block backend work: the deployment fork and the §98 client schema approval. Then `/magic.task main`.

## Progress

```
Implementation (against the SUPERSEDED 6-phase plan — product has since changed):
Phase 1: [14/14] ████████ 100%  DONE — archived
Phase 2: [11/11] ████████ 100%  DONE — archived
Phase 3: [8/8]   ████████ 100%  DONE — archived
Phase 4: [7/7]   ████████ 100%  DONE — archived
Phase 5: [6/6]   ████████ 100%  DONE — archived
Phase 6: [6/6]   ████████ 100%  DONE — archived

Specification (current): [23/23] re-baselined, all RFC, TZ coverage 134/134, review pending
Plan: SUPERSEDED — awaiting /magic.task regeneration
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-05 **Finding:** Second line-by-line TZ pass found **6 gaps** the first pass missed, all closed. New specs: `l1-public-api.md` (§19 — REST API/tokens/docs had zero coverage), `l1-home-page.md` (§4/§5 — 16-block composition unowned), `l2-data-model.md` (§21/§98). Amendments: favorites (§8), traffic-source analytics (§23), candidate-module catalogue (§23/§64). One was worse than a gap — `l1-advertising.md` §2 flatly excluded a self-service advertiser cabinet that TZ §23 actually *recommends*; corrected to a deferred candidate.
- 2026-08-05 **BLOCKER (process):** `[TZ]` §98 — the client must approve the final DB structure **before main backend development starts**. That gate is itself blocked on the deployment fork (`l2-tech-stack.md` §5.9). Two decisions therefore sit on the critical path ahead of all backend work. Deliverable status in `l2-data-model.md` §5.7: 4 of 9 items complete, 3 need column-level detail, 1 (backup scheme) blocked on the fork.
- 2026-08-05 **Decision:** Booking is **preserved, not deprecated**. Per explicit product direction and `[TZ]` §63–64, the reservation work (schema, `src/lib/reservation/`, checkout, Fondy) is retained behind an administrator-toggleable module registry (`l1-feature-modules.md`), disabled by default. Booking and payment are separate module rows, so "dated request + owner confirmation, no payment provider" is a supported intermediate state.
- 2026-08-05 **Decision:** Launch locales narrowed to **English + Russian only**; Romanian, Ukrainian, Georgian deferred until after project completion and activated from the back office (`l1-localization.md` §5.6). Content decision, not a capability one — translation tables, per-language slugs, hreflang, and fallback still ship in the first migration. Consequence: no launch country's own primary language is active at release, so country records reference inactive languages and must resolve via fallback rather than fail validation.
- 2026-08-05 **Decision:** Specs re-baselined against the client TZ. Product changed from hotel booking marketplace → 3-country multi-language tourism information portal. 3 renames (hotel-discovery→object-catalog, hotel-profile→object-profile, property-onboarding→object-onboarding), 10 new specs, 7 amended. All set to `RFC` (not auto-promoted to Stable) because the set carries unresolved TBDs — chiefly the deployment fork in `l2-tech-stack.md` §5.9.
- 2026-08-05 **Finding:** Stack audit by import analysis, not manifest reading: **11 packages are removable today with zero code change** (10 unused `@radix-ui/*` from the incomplete Base UI migration, plus `vaul`); `shadcn` CLI is misplaced in `dependencies`. Conversely **12 TZ-required capabilities have no dependency at all** (Redis, job queue, mail, image derivatives, map clustering, XLSX, 2FA/CAPTCHA, scoped RBAC, audit, soft delete, the `en` locale catalog). The stack is mis-provisioned, not excessive.
- 2026-08-05 **Decision:** Phase 6 (and the full 6-phase plan) closed. T-6T01's live browser check surfaced and fixed two real defects no automated test caught: a Base UI `nativeButton` console error on the "Оплатить" button-as-Link, and a genuinely deterministic (not flaky) bug in `reservation-query.test.ts` — every test in that file shares one fixed `guestId` but only cleaned up in one file-level `afterAll`, so an earlier test's own rows leaked into a later exact-count assertion. Full suite: 52 files / 158 tests, 0 failures.
- 2026-08-04 **Note:** Docker/Postgres access RESTORED — `docker` CLI reappeared on PATH, daemon was stopped, started via Docker Desktop + `docker compose up -d`; the named `postgres-data` volume preserved the schema through container recreation.
- 2026-08-04 **Decision:** T-6C01 (payment) built via an orchestrated multi-agent workflow (Ultracode posture) — 4 agents built provider/persistence/UI/tests in dependency order, 3 agents adversarially reviewed (correctness, auth, conventions) and found 8 real issues, all confirmed and fixed. See `archives/tasks/phase-6.md` T-6C01 Changes.

## Blockers

<!-- Empty if none. Format: [severity] description -->

- **[high] Deployment fork unresolved** (`l2-tech-stack.md` §5.9) — self-hosted vs managed. Blocks the storage, queue, and mail selections, and the `[TZ]` §97 backup scheme.
- **[high] `[TZ]` §98 client schema approval not started** (`l2-data-model.md` §5.7) — backend development cannot begin without it. Depends on the fork above.

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
