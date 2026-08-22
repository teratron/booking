# Master Task Index (Registry)

**Version:** 1.9.0
**Generated:** 2026-08-05
**Based on:** .design/main/PLAN.md v3.8.0
**Based on RULES:** .design/RULES.md v1.4.0
**Execution Mode:** Parallel
**Status:** Active — Phase 8 (7 phases done, 1 active at 20/23; C12 quarantine lifted 2026-08-21; re-synchronized to registry v2.9.0 on 2026-08-22 with no task change)

## Overview

Tactical registry of all phases and their statuses. Atomic checklists (`T-XXXX`) live in
the per-phase files under `tasks/`.

**Decomposition state — Phase 8 active, Phases 1–7 done.** The first seven phases closed
at 21/21, 25/25, 23/23, 16/16, 18/18, 16/16, 16/16 — 135 tasks — every one of them
decomposed and executed under an `RFC`-status posture, and no Pre-flight gate ever HALTed
across that entire run. Their planning audits and track rationale are preserved in their
archived task files. A plan-wide L2 retrospective ran when Phase 7 closed on 2026-08-20;
see `RETROSPECTIVE.md` Session 1.

**Phase 8 is decomposed at 20 tasks across five tracks plus validation, and is the first
phase in this plan built on `Stable` specifications** — both of its sources reached
`Stable` in the 2026-08-20 stabilization pass, and both originate with the project owner
rather than `[TZ]`, which is silent on how code reaches a server. It is also the first
phase carrying tasks an agent must not perform: Track E's three items are
repository-administration work, marked `Assignment: User`, and they gate four tasks in
two other tracks.

Two findings from the retrospective remain open outside Phase 8: the suite-wide
`composer test:coverage` floor (78.3%, ~20 pre-existing Phase 1–6 files) as its own
separately-scoped follow-up, and the specifications still at `RFC` — **17 as of
2026-08-22** — which carry the set's real remaining design work and none of which is a
Phase 8 input.

**Re-planned on 2026-08-22, and no task moved.** `l1-localization` and `l1-seo` reached
`Stable`, closing the registry-to-plan `SYNC_GAP`; both had already been built in
Phases 5 and 6, and the amendment that promoted them corrected the record around a
decision without touching a single rule the implementation reads. The pass added one
`## Backlog` entry — the panel addresses are runtime configuration and no specification
says so — and no phase.

**Re-planned twice on 2026-08-21, and back where it started.** Both of Phase 8's
specifications were amended outside the workflow; the re-review that followed the
registry reconciliation held them at `RFC`, because the sensitive-zone boundary they
declare mechanical was narrower in enforcement than in text. Track F was added to close
that — three tasks — and ran under C12.1's stabilization exception while the rest of the
phase sat quarantined. All three closed the same day, the specifications returned to
`Stable` at v0.4.0, and the quarantine lifted: the three owner-only tasks are `Todo`
again. The count moved from 20 tasks to 23, which is the one permanent change.

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
| [Phase 4](archives/tasks/phase-4.md) | Owner cabinet — the second Filament panel, owner-scoped throughout | `Done (Archived)` (16/16) |
| [Phase 5](archives/tasks/phase-5.md) | Public site — shell, home, catalog, object profile, territory pages, built from Figma | `Done (Archived)` (18/18) |
| [Phase 6](archives/tasks/phase-6.md) | SEO, portal-wide reporting, public REST API | `Done (Archived)` (16/16) |
| [Phase 7](archives/tasks/phase-7.md) | Import/export, backups and rehearsed restore, production provisioning and observability, load test | `Done (Archived)` (16/16) |
| [Phase 8](tasks/phase-8.md) | Delivery pipeline — branch contract, release artefact and deployment, irreversibility scan, EN/RU/agent operator documentation, sensitive-zone gate integrity | `In Progress` (20/23) |

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

**Phase 5 is four-wide** (`A` shell/catalog-query/card foundation, `B` object profile,
`C` catalog/territory listings, `D` home/content surfaces), because the public site is
greenfield — Phases 1–4 built the schema, the admin panel, and the owner cabinet, but
no public-facing retrieval query, card, contact deep-link resolver, or map component
exists yet. `T-5A03` (`CatalogQueryService`) is the phase's hard gate: every listing
surface in Tracks B, C, and D is a caller of this one tier-ordered retrieval contract,
never a reimplementation — the same role `T-2A02` played for Phase 2. `T-5A01` (shell)
is a second, independent gate, since every page in the phase renders inside it. Full
rationale in [archives/tasks/phase-5.md](archives/tasks/phase-5.md) §Track Ordering.

**Phase 6 is three-wide**, not four: `(A → B) ∥ C ∥ D → T`. Track C (portal-wide
reporting) reads the aggregate tier Phase 3 built and Phase 4 already reports against,
and Track D (public API) layers over Phase 5's `CatalogQueryService` — neither consumes
the SEO addressing chain, so both start immediately. Only Track B waits, and it waits
entirely on `T-6A01`.

`T-6A01` (URL grammar and slug resolution) is the phase's hard gate, and it differs in
kind from the gates before it: `T-2A02` and `T-5A03` were greenfield contracts that
later work adopted, while this one **retrofits** addressing that 21 existing files
already depend on. A broken landing fails Phase 5's whole feature suite at once, so it
is verified against that existing suite rather than only against its own new test.

One cross-track contract is scheduled rather than left to be discovered: `T-6B02` and
`T-6D03` must share a single published-only visibility filter. Both specifications ask
for it in their own vocabulary — the indexation policy excludes non-public records, the
API applies visibility in the shared query layer — and two implementations would drift,
with the drift exposing unmoderated content on whichever side was forgotten. Full
rationale in [tasks/phase-6.md](archives/tasks/phase-6.md) §Track Ordering.

**Phase 7 is three-wide**: `(A → B) ∥ C ∥ D → T`. Track A (data-type registry and the
import pipeline), Track C (backups, integrity, restore) and Track D (production
provisioning and observability) share no code and start together; Track B (export) is the
only waiting track, and it waits on `T-7A01` alone rather than on the rest of Track A.

`T-7A01` (the transferable data-type registry) is the phase's hard gate and its
highest-cascade task — six of sixteen tasks read it — but it differs in kind from the
gates before it: `T-2A02`, `T-5A03` and `T-6A01` were machinery, and this one is a
declaration. The specification names the same thirteen entity kinds twice, once as import
targets and once as export targets; building two inventories from one list produces a
silent one-column drift, which is exactly the defect a round-trip test (`T-7T02`) exists
to catch and a reviewer does not.

Two cross-track contracts are scheduled rather than left to be discovered: `T-7C01`'s
backup destination and `T-7D01`'s media bucket must resolve to **different** disks (a
backup beside what it protects is not a backup, and `T-7D01` is the task most likely to
collapse them while consolidating production configuration); and `T-7D03` must configure
Pulse's recorders against the territory page's ≤30-query ceiling, which has zero headroom
— otherwise the cost surfaces in `T-7T03` looking like a regression in already-completed
public-site work. Full rationale in [tasks/phase-7.md](archives/tasks/phase-7.md) §Track Ordering.

`T-7T03` (load test) is the one task whose *ordering* is specified rather than derived:
the load test runs before launch, not after. It sits last by dependency, which makes it
the natural casualty of a compressed schedule — it is not optional.

**Phase 8 is four-wide at the start and one-wide at the end**: `(A ∥ D ∥ E ∥ B01) → B02
→ B03 → B04 → T03`. It has six tracks and calling it six-wide would be false — Track B
is a chain after its first task, and the acceptance task waits on everything.

It breaks two patterns every earlier phase held. **Its central deliverable cannot be
proven by `composer quality`** — a deploy job, a rollback, a branch protection rule and
an approval gate are observable only against a real repository, registry and host, so
roughly half its tasks defer behavioural proof to `T-8T03` in their own `Verify` lines
rather than claiming a check they cannot perform. And **it is the first phase with tasks
an agent must not perform**: Track E (automation identity, the `production` environment
and its reviewers, the three secret tiers) is repository-administration work whose
permissions `l2-release-pipeline.md` §5.10 deliberately withholds from automation, so
that the identity which would benefit from approving a release cannot grant it.

`T-8E02` (the `production` environment and its reviewers) is the phase's highest-cascade
task — `T-8B02`, `T-8B03`, `T-8B04` and `T-8T03` all stop without it — and it is blocked
by administration authority, not engineering effort. `T-8B01` (the production image stage
and the `.dockerignore` this repository does not yet have) is the highest-cascade agent
task; the same four tasks address the artefact it emits, by digest.

Three dependencies are scheduled rather than left to be discovered:
`.github/workflows/quality.yml` is edited by `T-8A03`, `T-8C01` and `T-8T02` across three
tracks, so `T-8A03` is ordered first and the other two append to a settled layout;
`docs/README.md` would have been edited by five Track D tasks, so the index edit is
collapsed into `T-8D05` alone; and `T-8T03` (the rehearsal) sits last by dependency, the
same position `T-7T03` occupied and with the same hazard — it is the natural casualty of
a compressed schedule, and it is the specification's own acceptance criterion. Full
rationale in [tasks/phase-8.md](tasks/phase-8.md) §Track Ordering.

## Meta Information

- **Last Updated**: 2026-08-20 (Phase 8 planned, 0/20 — 7 phases done, 1 active)
- **Maintainer**: Core Team
