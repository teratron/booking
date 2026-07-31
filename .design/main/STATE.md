# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-07-31 19:33
**Phase:** 5 - Hotel Profile and Content Publishing
**Status:** Active

## Current Position

- **Task:** T-5T01 Validate independent section degradation and the moderation checkpoint (Done — Phase 5 complete)
- **Spec:** l1-hotel-profile.md v0.2.0 + l1-content-publishing.md v0.2.0 — decomposed into 6 tasks across 3 tracks
- **Next Action:** Plan complete — run /magic.task main to plan new scope

## Progress

```
Phase 1: [14/14] ████████ 100%  DONE — archived
Phase 2: [11/11] ████████ 100%  DONE — archived
Phase 3: [8/8]   ████████ 100%  DONE — archived
Phase 4: [7/7]   ████████ 100%  DONE — archived
Phase 5: [6/6]   ████████ 100%  DONE — archived
Overall: [5/6]   ███████░ 83%   (5 of 6 planned phases complete)
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-07-31 **Decision:** Phase 4 (Discovery & Catalog) closed, all 7 tasks Done — full decision record archived in `archives/tasks/phase-4.md`.
- 2026-07-31 **Decision:** Phase 5 pairs Hotel Profile + Content Publishing (one article pipeline, two render sites). Room-summary cards ship with no booking CTA yet (Phase 6 owns the popup), no synthetic nearby-POI pins, review submission and article authoring both out of scope — see `tasks/phase-5.md` Decisions for all five [DR] resolutions.
- 2026-07-31 **Pattern:** `ResultCard`'s translation strings live in their own `"ResultCard"` namespace, not borrowed from whichever page built it first.

## Blockers

<!-- Empty if none. Format: [severity] description -->

- none

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
- **Server Actions must live in their own file**, separate from the schema
  and the persistence logic: a "use server" function sharing a file with
  server-only helpers (anything importing `db`) breaks the build the moment
  a Client Component imports it — and even after splitting, importing
  *anything else* from the persistence file still pulls `db`→`pg`→Node
  builtins into the client bundle, a runtime 500 `tsc`/Vitest never catch.
  Split three ways: schema (client-safe), persistence (db logic), actions
  (`"use server"`, imports persistence + schema types). Reference shape:
  `src/lib/property-onboarding/{schema,submit-listing,actions}.ts`.
  Discovered live in T-3B02 — only a real dev-server request caught it.
- **`pnpm test -- <path>` does not reliably scope to one file** — use
  `pnpm exec vitest run <path>` instead.
- **Stop the dev server before `pnpm test`.** A concurrent `pnpm dev`
  (Turbopack watching/compiling) competes with Vitest's worker threads for
  CPU and reliably causes flaky timeouts (85s w/ failures → 40s w/ none,
  confirmed by A/B). `db/client.ts`'s pooled `max` cap under
  `process.env.VITEST` and `vitest.config.ts`'s `testTimeout: 15000` are
  secondary mitigations only. Discovered T-3B02, root-caused Phase 4 planning.
- **Multi-render test files need explicit `afterEach(() => cleanup())`**
  (`@testing-library/react`) — no global setup wires this in automatically.
  Also: Vitest runs test files in parallel workers against one real,
  non-transactional Postgres instance — an unfiltered/loosely-scoped query in
  one file can see another concurrently-running file's in-flight fixture
  rows. Prefer mocking the query layer (`vi.mock` + `importOriginal`) for
  page/component tests that don't need to re-prove DB-level correctness a
  lower-level test already covers; reserve real-DB fixtures for the module
  that owns that query logic. Discovered live in T-4C01.

## Session Continuity

**Last Session Ended:** 2026-07-30 19:54
**Handoff File:** none
**Bootstrap Mode:** false
