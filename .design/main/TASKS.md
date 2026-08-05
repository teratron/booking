# Master Task Index (Registry)

**Version:** 1.0.0
**Generated:** 2026-08-05
**Based on:** .design/main/PLAN.md v3.0.0
**Based on RULES:** .design/RULES.md v1.4.0
**Execution Mode:** Parallel
**Status:** Active

## Overview

Tactical registry of all phases and their statuses. Atomic checklists (`T-XXXX`) live in
the per-phase files under `tasks/`.

**Decomposition state.** Phase 1 is decomposed into atomic tasks. Phases 2 through 7
carry frontmatter, a strategic goal, and their scope — no `T-XXXX` items yet. This is
deliberate, not truncation: each is decomposed by the `/magic.task` invocation that
activates it, against the specification set as it stands at that point. All 23
specifications are `RFC`, so decomposing seven phases now would produce six phases of
tasks derived from contracts that have not been reviewed.

## Active Phases

| Phase | Description | Status |
| --- | --- | --- |
| [Phase 1](tasks/phase-1.md) | Foundation, schema, registries, scoped authorization, module gating, quality gates | `Todo` |
| [Phase 2](tasks/phase-2.md) | Back office core — staff panel, objects, owners, geography, taxonomy, moderation, action journal | `Todo` |
| [Phase 3](tasks/phase-3.md) | Commerce, advertising, analytics ingest, notifications, content pipeline | `Todo` |
| [Phase 4](tasks/phase-4.md) | Owner cabinet — the second Filament panel, owner-scoped throughout | `Todo` |
| [Phase 5](tasks/phase-5.md) | Public site — shell, home, catalog, object profile, territory pages, built from Figma | `Todo` |
| [Phase 6](tasks/phase-6.md) | SEO, portal-wide reporting, public REST API | `Todo` |
| [Phase 7](tasks/phase-7.md) | Import/export, backups and rehearsed restore, production provisioning, load test | `Todo` |

## Execution Notes

**Parallel mode (C3)** is the default and applies from Phase 2 onward. Phase 1's tracks
are **not** independent — the real ordering is `A → B → (C ∥ D) → T`, giving an
effective parallel degree of two. Treating Phase 1 as four-way parallel would stall on
the scaffold.

**Critical path.** `T-1D01` (scoped authorization resolution) blocks every screen in
Phases 2 and 4. `T-1B05` (index plan) blocks every performance claim in Phases 5 and 6.
Neither is deferrable.

**Quality gates run continuously.** `composer quality` after every meaningful change,
not at task boundaries and not only before a commit. `T-1A03` wires it, and every
subsequent task is verified against it.

## Meta Information

- **Last Updated**: 2026-08-05
- **Maintainer**: Core Team
