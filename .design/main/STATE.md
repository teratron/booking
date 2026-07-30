# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-07-30 16:17
**Phase:** 1 — Platform Foundation
**Status:** Active

## Current Position

- **Task:** T-1A05 Wire Fallow for dev-time codebase intelligence (Done — last completed)
- **Spec:** l1-platform-foundation.md, l1-platform-shell.md, l2-tech-stack.md
- **Next Action:** Execute T-1B01 (Track B: Drizzle schema) and T-1C01 (Track C: root layout shell) via /magic.run main — both depend only on T-1A01's scaffold, already Done

## Progress

```
Phase 1: [5/14]  ███░░░░░ 36%   (Track A complete: T-1A01–T-1A05)
Overall: [0/9]   ░░░░░░░░ 0%    (specs implemented; 9/9 specs Stable)
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-07-30 **Decision:** Dependency policy — keep the whole stack on latest, including major bumps (`pnpm update --latest`), not conservatively pinned. Ran it post-Track-A: TypeScript 5→7 (native compiler), `@types/node` 20→26, React 19.2.4→19.2.8. Fixed the one real incompatibility this surfaced (below); everything else verified clean (`tsc`, `biome`, `pnpm test`, `next dev`).
- 2026-07-30 **Pattern:** TypeScript 7's native compiler doesn't expose the compiler API Next.js 16.2.12 expects by default — fixed via `experimental.useTypeScriptCli: true` in `next.config.ts` (Next's own documented escape hatch), not by downgrading TypeScript. Two harmless, expected peer-metadata lags remain (`tsconfck@3.1.6` peers `typescript@^5`; a `rolldown`/`@napi-rs/wasm-runtime` WASI-only fallback chain wants an alpha `@emnapi/*` line) — both transitive, both empirically inert on this platform.
- 2026-07-30 **Decision:** Six implementation phases ordered by hard dependency — identity and the shared data model precede every feature that reads or writes them.
- 2026-07-30 **Pattern:** Single shared entity model — the Phase 1 Drizzle schema covers the full relationship graph in one pass so later phases extend it instead of restructuring it.
- 2026-07-30 **Pattern:** Phase execution shape `A → (B ‖ C) → T` — parallel tracks are declared only where they touch disjoint files.

## Blockers

<!-- Empty if none. Format: [severity] description -->

- none

## Blocking Constraints

<!-- Anti-patterns discovered through real failures. MANDATORY reading. -->
<!-- Agent MUST explicitly acknowledge each constraint before working. -->

- none recorded

## Session Continuity

**Last Session Ended:** 2026-07-30 15:21
**Handoff File:** none
**Bootstrap Mode:** false
