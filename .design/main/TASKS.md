# Master Task Index (Registry)

**Version:** 1.2.0
**Generated:** 2026-08-05
**Based on:** .design/main/PLAN.md v3.1.1
**Based on RULES:** .design/RULES.md v1.4.0
**Execution Mode:** Parallel
**Status:** Active

## Overview

Tactical registry of all phases and their statuses. Atomic checklists (`T-XXXX`) live in
the per-phase files under `tasks/`.

**Decomposition state.** Phases 1 through 3 are complete (21/21, 25/25, 23/23) and
archived. Phase 4 is decomposed into 16 atomic tasks across four tracks plus validation
in [tasks/phase-4.md](tasks/phase-4.md), against the same `RFC`-status posture the first
three phases were successfully decomposed and executed under — no Pre-flight gate has
ever HALTed on it. Phases 5 through 7 still carry frontmatter, a strategic goal, and
their scope only — no `T-XXXX` items yet; each is decomposed by the `/magic.task`
invocation that activates it, against the specification set as it stands at that point.

The superseded Next.js-era archives now live under `archives/tasks/v1-nextjs/`. They
previously occupied the filenames `archives/tasks/phase-1.md` through `phase-6.md`,
which is exactly where this project's own completed phases are archived — the next
archival would have overwritten them.

## Active Phases

| Phase | Description | Status |
| --- | --- | --- |
| [Phase 1](archives/tasks/phase-1.md) | Foundation, schema, registries, scoped authorization, module gating, quality gates | `Done (Archived)` |
| [Phase 2](archives/tasks/phase-2.md) | Back office core — staff panel, objects, owners, geography, taxonomy, moderation, action journal | `Done (Archived)` (25/25) |
| [Phase 3](archives/tasks/phase-3.md) | Commerce, advertising, analytics ingest, notifications, content pipeline | `Done (Archived)` (23/23) |
| [Phase 4](tasks/phase-4.md) | Owner cabinet — the second Filament panel, owner-scoped throughout | `In Progress` (4/16) |
| [Phase 5](tasks/phase-5.md) | Public site — shell, home, catalog, object profile, territory pages, built from Figma | `Todo` |
| [Phase 6](tasks/phase-6.md) | SEO, portal-wide reporting, public REST API | `Todo` |
| [Phase 7](tasks/phase-7.md) | Import/export, backups and rehearsed restore, production provisioning, load test | `Todo` |

## Execution Notes

**Parallel mode (C3)** is the default. Phase 1's tracks were **not** independent — the
real ordering was `A → B → (C ∥ D) → T`, an effective parallel degree of two. Phase 2 is
the first phase with genuine parallelism, and it is three-wide rather than five:
`A → (B ∥ C ∥ D) → T`. Track A is a hard gate, because every resource in the other three
tracks is built on the shared resource contract it establishes.

**Critical path.** Within Phase 2, `T-2A02` (shared resource contract) is upstream of
twenty-two tasks — a contract changed after ten resources adopt it is a ten-file
rewrite. `T-2D01` (moderation mode resolution) is second: its snapshot semantics cannot
be retrofitted once requests exist in the table, and `T-2B02`'s return-for-revision
action consumes it. `T-2A01`'s sign-in journal is the one item that cannot be
backfilled — the events it records will already have happened.

**Quality gates run continuously.** `composer quality` after every meaningful change,
not at task boundaries and not only before a commit. `T-1A03` wired it, and every
subsequent task is verified against it. The toolchain runs inside the container —
`docker compose exec app …` — because the host carries no PHP or Composer.

**Phase 3 is five-wide**, one track per specification (`A` placement/monetization,
`B` advertising, `C` analytics, `D` notifications, `E` content publishing), because
Phase 2 already built the shared resource contract every one of these tracks builds on
top of — no track here has to establish it first, unlike Phase 2's Track A gate. Two
task-level edges are scheduled across tracks rather than discovered mid-run: `T-3D01`
before `T-3A04` (the expiry sweep raises notifications against a model that must
already exist), and `T-3C01` before `T-3B02` (banner impressions/clicks are `StatEvent`
rows, not a second counting scheme). Full rationale in
[tasks/phase-3.md](archives/tasks/phase-3.md) §Track Ordering.

## Meta Information

- **Last Updated**: 2026-08-13
- **Maintainer**: Core Team
