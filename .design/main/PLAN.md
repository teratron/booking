# Implementation Plan

**Version:** 3.5.0
**Generated:** 2026-08-05
**Based on:** .design/main/INDEX.md v2.6.0
**Based on RULES:** .design/RULES.md v1.4.0
**Status:** Active — Phase 8 (delivery pipeline; the first phase planned against `Stable` specifications — see Plan Status below)

## Overview

Delivery plan for the international tourism portal: a self-hosted Laravel 13 monolith
serving visitors (browse objects, contact owners directly), object owners (self-service
cabinet), and portal staff (back office), across three countries and two launch
languages.

Phases 1 through 7 were planned and executed against 23 specifications, all at `RFC` at
the time — `[Bootstrap Plan]` marks that, and every one of those phases was tentative by
construction. That posture ended on 2026-08-20: six specifications reached `Stable`, and
**Phase 8 is the first phase in this plan built on `Stable` sources rather than
provisional ones.** The registry now holds 25 specifications, 6 `Stable` and 19 `RFC`;
the 19 are the set's real remaining design work rather than a status backlog, and none
of them is a Phase 8 input.

The previous plan — six phases delivered against a Next.js/TypeScript implementation of
the superseded hotel-booking product — is archived at
[archives/PLAN-v1-nextjs.md](archives/PLAN-v1-nextjs.md) with its task ledger at
[archives/TASKS-v1-nextjs.md](archives/TASKS-v1-nextjs.md). That implementation is
preserved at git tag `v0.1.34` and is **not** a migration source: the schema is created
from empty.

## Blocking Gates

**None.** Both gates that previously stood ahead of backend development are closed.

| Gate | Status |
| --- | --- |
| Deployment target (managed vs self-hosted) | **Closed** — self-hosted ([l2-tech-stack.md](specifications/l2-tech-stack.md) §5.10) |
| `[TZ]` §98 — client approval of the database structure | **Waived** — the client delegates the design to engineering judgment ([l2-data-model.md](specifications/l2-data-model.md) §5.7) |

The critical path runs straight from specification review into scaffolding.

## Build Order vs Client-Stated Delivery Stages

`[TZ]` §23 names the stages the client expects, recorded in
[l1-platform-foundation.md](specifications/l1-platform-foundation.md) §5.4:
specification → visual design → backend → frontend → owner cabinet → back office →
SEO → testing → content population → launch.

**This plan deliberately builds the back office before the public site**, inverting
stages 4–6. Recorded as a divergence rather than applied silently:

- The public site renders data that does not exist until the back office creates it —
  territories, object types, amenities, packages, objects, banners, content. Building
  the public site first means building it against fixtures and re-verifying it against
  real data later.
- `[TZ]` §134 places import/export in the **mandatory first release** precisely because
  content population (stage 9) is a real deliverable, and import is a back-office
  capability.
- [l2-tech-stack.md](specifications/l2-tech-stack.md) §6.4 requires scoped
  authorization before any panel screen, and §6.5 orders the Filament panels by
  `[TZ]` §134 priority. Both are back-office-first statements.

The client's list is a list of **delivery stages**, not a build order; every stage is
still delivered, and `[TZ]` §134's mandatory scope governs what release one contains.

## Phase 0 — Requirements (Layer 1: Concept)

*Abstract specifications — technology-agnostic contracts.*
*All at `RFC`; `Stable` promotion is blocked by the open questions each carries.*

- [ ] **Platform Foundation** ([l1-platform-foundation.md](specifications/l1-platform-foundation.md)) [L1]
- [ ] **Feature Modules** ([l1-feature-modules.md](specifications/l1-feature-modules.md)) [L1]
- [ ] **Localization** ([l1-localization.md](specifications/l1-localization.md)) [L1]
- [ ] **Geography** ([l1-geography.md](specifications/l1-geography.md)) [L1]
- [ ] **Platform Shell** ([l1-platform-shell.md](specifications/l1-platform-shell.md)) [L1]
- [ ] **Home Page** ([l1-home-page.md](specifications/l1-home-page.md)) [L1]
- [ ] **Object Catalog** ([l1-object-catalog.md](specifications/l1-object-catalog.md)) [L1]
- [ ] **Object Profile** ([l1-object-profile.md](specifications/l1-object-profile.md)) [L1]
- [ ] **Availability Status** ([l1-availability-status.md](specifications/l1-availability-status.md)) [L1]
- [ ] **Content Publishing** ([l1-content-publishing.md](specifications/l1-content-publishing.md)) [L1]
- [ ] **SEO** ([l1-seo.md](specifications/l1-seo.md)) [L1]
- [ ] **Object Onboarding & Owner Cabinet** ([l1-object-onboarding.md](specifications/l1-object-onboarding.md)) [L1]
- [ ] **Back Office** ([l1-back-office.md](specifications/l1-back-office.md)) [L1]
- [ ] **Moderation & Governance** ([l1-moderation-governance.md](specifications/l1-moderation-governance.md)) [L1]
- [ ] **Notifications** ([l1-notifications.md](specifications/l1-notifications.md)) [L1]
- [ ] **Placement & Monetization** ([l1-placement-monetization.md](specifications/l1-placement-monetization.md)) [L1]
- [ ] **Advertising** ([l1-advertising.md](specifications/l1-advertising.md)) [L1]
- [ ] **Analytics** ([l1-analytics.md](specifications/l1-analytics.md)) [L1]
- [ ] **Public API** ([l1-public-api.md](specifications/l1-public-api.md)) [L1]
- [ ] **Technology Stack** ([l2-tech-stack.md](specifications/l2-tech-stack.md)) [L2]
- [ ] **Data Model** ([l2-data-model.md](specifications/l2-data-model.md)) [L2]
- [ ] **Third-Party Integrations** ([l2-third-party-integrations.md](specifications/l2-third-party-integrations.md)) [L2]

## Phase 1 — Foundation, Schema & Authorization `[Bootstrap]` — **Done**

*Everything that must exist before a single screen is built. The whole schema in one
pass from empty, the registries as data, and scoped authorization before any panel.*

- [x] **Technology Stack** §5.1–§5.4, §5.8–§5.10, §6.1 — scaffold, Docker stack, asset pipeline ([l2-tech-stack.md](specifications/l2-tech-stack.md)) [L2]
- [x] **Technology Stack** §5.9 — quality gates and benchmark harness ([l2-tech-stack.md](specifications/l2-tech-stack.md)) [L2]
- [x] **Data Model** — full migration set, index plan, deletion and retention rules ([l2-data-model.md](specifications/l2-data-model.md)) [L2]
- [x] **Localization** §5.1–§5.2, §6 — translation tables alongside their parents, language and country registries ([l1-localization.md](specifications/l1-localization.md)) [L1]
- [x] **Geography** §5.1–§5.2 — recursive territory hierarchy, per-country level vocabularies ([l1-geography.md](specifications/l1-geography.md)) [L1]
- [x] **Back Office** §5.2 — roles, permissions, scoped grants ([l1-back-office.md](specifications/l1-back-office.md)) [L1]
- [x] **Feature Modules** §5.1–§5.3, §5.7 — registry, resolution ladder, server-side gate ([l1-feature-modules.md](specifications/l1-feature-modules.md)) [L1]
- [x] **Platform Foundation** §3 — invariants made mechanically checkable ([l1-platform-foundation.md](specifications/l1-platform-foundation.md)) [L1]
- [x] **Third-Party Integrations** §5.1, §5.4 — local storage and mail equivalents ([l2-third-party-integrations.md](specifications/l2-third-party-integrations.md)) [L2]

## Phase 2 — Back Office Core — **Done**

*The staff panel in `[TZ]` §134 priority order, up to the point where a portal can be
operated: objects, owners, geography, taxonomy, moderation, and the action journal.*

- [x] **Back Office** §5.1, §5.3–§5.6 — panel shell, dashboard, object and owner management, settings ([l1-back-office.md](specifications/l1-back-office.md)) [L1]
- [x] **Moderation & Governance** — queue, diff review, action journal, archive, confirmation gates ([l1-moderation-governance.md](specifications/l1-moderation-governance.md)) [L1]
- [x] **Object Catalog** §3.1 — object type registry administration ([l1-object-catalog.md](specifications/l1-object-catalog.md)) [L1]
- [x] **Geography** §5.5 — territory administration, guarded reparenting ([l1-geography.md](specifications/l1-geography.md)) [L1]
- [x] **Localization** §5.4–§5.5 — interface catalogs, translation management, untranslated-material report ([l1-localization.md](specifications/l1-localization.md)) [L1]
- [x] **Availability Status** §5.4–§5.5 — administrator override, staleness filters, bulk reset ([l1-availability-status.md](specifications/l1-availability-status.md)) [L1]
- [x] **Feature Modules** §5.6 — module management screen, per-scope toggles, blast-radius confirmation ([l1-feature-modules.md](specifications/l1-feature-modules.md)) [L1]

Decomposed into 25 atomic tasks across five tracks in [archives/tasks/phase-2.md](archives/tasks/phase-2.md),
which carries this phase's own planning audit — the shared resource contract, not the
resource count, is the task that decides the phase.

## Phase 3 — Commerce, Advertising & Platform Services — **Done**

*The revenue mechanics and the background machinery both panels depend on.*

- [x] **Placement & Monetization** — tiers, packages, placements, scoped bumps, expiry, financial ledger ([l1-placement-monetization.md](specifications/l1-placement-monetization.md)) [L1]
- [x] **Advertising** — banners, slots, targeting and specificity ranking, promotional labels ([l1-advertising.md](specifications/l1-advertising.md)) [L1]
- [x] **Analytics** §5.1–§5.3 — batched ingest, partitioned events, daily rollup and compaction ([l1-analytics.md](specifications/l1-analytics.md)) [L1]
- [x] **Notifications** — notification and dispatch model, channel adapters, scheduled sweeps ([l1-notifications.md](specifications/l1-notifications.md)) [L1]
- [x] **Content Publishing** §5.1–§5.2, §5.4 — articles, news, promotions and their shared pipeline ([l1-content-publishing.md](specifications/l1-content-publishing.md)) [L1]

Decomposed into 23 atomic tasks across five tracks plus validation in
[archives/tasks/phase-3.md](archives/tasks/phase-3.md).

## Phase 4 — Owner Cabinet — **Done**

*The second Filament panel: the same toolkit, owner-scoped in both query and policy.*

- [x] **Object Onboarding & Owner Cabinet** — the full cabinet lifecycle ([l1-object-onboarding.md](specifications/l1-object-onboarding.md)) [L1]
- [x] **Availability Status** §5.3 — the one-tap toggle, no form, no save step ([l1-availability-status.md](specifications/l1-availability-status.md)) [L1]
- [x] **Analytics** §5.4 — cabinet dashboard and statistics surfaces ([l1-analytics.md](specifications/l1-analytics.md)) [L1]
- [x] **Content Publishing** §3.3–§3.4 — owner-authored news and promotions ([l1-content-publishing.md](specifications/l1-content-publishing.md)) [L1]

## Phase 5 — Public Site — **Done**

*Built against the Figma source, node by node. Server-rendered Blade with Livewire for
catalog interactivity.*

- [x] **Platform Shell** — header, data-driven navigation, both switchers, footer, 404, legal pages ([l1-platform-shell.md](specifications/l1-platform-shell.md)) [L1]
- [x] **Home Page** — the sixteen-block composition across four viewport classes ([l1-home-page.md](specifications/l1-home-page.md)) [L1]
- [x] **Object Catalog** §5.1–§5.4 — search, filters, tier-governed ordering, clustered map ([l1-object-catalog.md](specifications/l1-object-catalog.md)) [L1]
- [x] **Object Profile** — page composition and the contact rail conversion contract ([l1-object-profile.md](specifications/l1-object-profile.md)) [L1]
- [x] **Geography** §5.3 — territory landing pages ([l1-geography.md](specifications/l1-geography.md)) [L1]
- [x] **Content Publishing** §5.3 — public blog, news, and promotion surfaces ([l1-content-publishing.md](specifications/l1-content-publishing.md)) [L1]
- [x] **Analytics** §3.1–§3.2 — first-party event emission from every measured surface ([l1-analytics.md](specifications/l1-analytics.md)) [L1]
- [x] **Third-Party Integrations** §5.3, §5.5 — map tiles and CAPTCHA ([l2-third-party-integrations.md](specifications/l2-third-party-integrations.md)) [L2]

Delivered as 18 atomic tasks across four tracks plus validation, archived at
[archives/tasks/phase-5.md](archives/tasks/phase-5.md). Two deliberate deferrals are
recorded there and collected by Phase 6: the public route group ships an ID-addressed
placeholder grammar rather than the SEO specification's nested per-language slug paths,
and the object page's query budget (68) exceeds the ≤30 target — documented with a
regression guard rather than silently loosened.

## Phase 6 — Discovery, Reporting & Public API — **Done**

*Findability and legibility: the addressing the public site deferred, the reporting the
aggregate tier already supports, and a read-only contract for consumers outside it.*

- [x] **SEO** — URL grammar, metadata resolution, indexation policy, structured data, sitemaps, redirects ([l1-seo.md](specifications/l1-seo.md)) [L1]
- [x] **Analytics** §5.3, §5.4, §5.6 — portal-wide reporting, exports, traffic sources ([l1-analytics.md](specifications/l1-analytics.md)) [L1]
- [x] **Public API** — versioned read contract, issued tokens, scoping, rate limits, generated documentation ([l1-public-api.md](specifications/l1-public-api.md)) [L1]

Decomposed into 16 atomic tasks across four tracks plus validation in
[archives/tasks/phase-6.md](archives/tasks/phase-6.md), which carries this phase's own planning audit.
The phase is genuinely three-wide — `(A → B) ∥ C ∥ D → T` — because reporting layers
over the aggregate tier and the API layers over the catalog retrieval contract, and
neither touches the SEO addressing chain that gates Track B.

## Phase 7 — Operations & Launch Readiness — **Done**

*The last phase. Everything between a working portal and an operable one: moving data
in and out, protecting it, provisioning the services it runs on, and measuring it under
load before launch rather than after.*

- [x] **Back Office** §5.7 — import and export pipeline with column mapping and error report ([l1-back-office.md](specifications/l1-back-office.md)) [L1]
- [x] **Back Office** §5.6 — backups, retention, integrity verification, rehearsed restore ([l1-back-office.md](specifications/l1-back-office.md)) [L1]
- [x] **Third-Party Integrations** §5.1, §5.2, §5.4, §5.8 — object storage, CDN, SMTP, error tracking ([l2-third-party-integrations.md](specifications/l2-third-party-integrations.md)) [L2]
- [x] **Technology Stack** §5.4, §5.9, §5.10 — queue and scheduler topology, production performance visibility, load test against the stated budgets ([l2-tech-stack.md](specifications/l2-tech-stack.md)) [L2]

Decomposed into 16 atomic tasks across four tracks plus validation in
[archives/tasks/phase-7.md](archives/tasks/phase-7.md), which carries this phase's own planning audit.
The phase is three-wide — `(A → B) ∥ C ∥ D → T` — because data transfer, backup
protection, and service provisioning share no code with each other, and only export
waits, waiting entirely on `T-7A01`.

`T-7A01` (the transferable data-type registry) is the phase's hard gate, and it is the
cheapest gate in the plan relative to its blast radius: six of sixteen tasks read it,
and it is a declaration rather than machinery. The specification names the same thirteen
entity kinds twice — once for import, once for export — and two inventories built from
one list will drift a column apart silently.

Two cross-track contracts are scheduled rather than left to be discovered: the backup
destination and the media bucket must resolve to different disks (`T-7C01` ∥ `T-7D01`),
and Pulse's recorders must not cost the territory page a query, since its budget test
sits at exactly its ≤30 ceiling with no headroom (`T-7D03`).

Two items extend the phase's originally-scoped list, both recorded in
[archives/tasks/phase-7.md](archives/tasks/phase-7.md) §Planning Audit rather than applied silently:
**Horizon** (required by the stack specification's background-execution and deployment
sections and by the project's package list, with no later phase to catch it) is folded
into `T-7D03`; and the **coverage floor** — `composer test:coverage` at 78.9% against
its own 80% minimum, pre-existing debt in two content-lifecycle services — is closed by
`T-7T04`, because a launch-readiness phase whose own quality gate fails is not launch
ready.

## Phase 8 — Delivery Pipeline & Operator Documentation — **Active**

*The implementation is finished and the portal has never been released. This phase
builds the path a change takes from an accepted branch to a serving production portal,
the path back when a release turns out wrong, and the documentation that lets someone
other than its author walk either one.*

- [ ] **Release Operations** §3, §5.1–5.6 — promotion path, gate obligations by transition, release records, the two reversal paths, the operator documentation matrix, and the boundary between agent-decided and human-decided release actions ([l1-release-operations.md](specifications/l1-release-operations.md)) [L1]
- [ ] **Release Pipeline** §5.2–5.10 — Git Flow branch contract, one new tag-triggered workflow beside the existing gate, an immutable image digest as the release artefact, pull-based deploy with health-assertion rollback, the destructive-migration scan, and the EN/RU/agent documentation tree with enforced parity ([l2-release-pipeline.md](specifications/l2-release-pipeline.md)) [L2]

Decomposed into 20 atomic tasks across five tracks plus validation in
[tasks/phase-8.md](tasks/phase-8.md), which carries this phase's own planning audit.
The phase is **four-wide at the start and one-wide at the end** — `(A ∥ D ∥ E ∥ B01) →
B02 → B03 → B04 → T03`. Calling it six-wide because it has six tracks would be false:
Track B is a chain after its first task, and the acceptance task waits on everything.

**This phase differs in kind from the seven before it, in two ways that shape every
task in it.**

First, it is the only phase whose central deliverable **cannot be proven by
`composer quality`**. A deploy job, a rollback, a branch protection rule and an approval
gate are observable only against a real repository, a real registry and a real host.
Roughly half the tasks are verifiable in the working copy; the other half say so in
their own `Verify` lines and defer behavioural proof to `T-8T03` rather than claiming a
check they cannot perform.

Second, it is the only phase with tasks an agent **must not** perform. Branch
protection, the `production` environment's reviewer list, the automation identity, and
the three secret tiers are operator work — not as a convenience boundary but as an
invariant. [l2-release-pipeline.md](specifications/l2-release-pipeline.md) §5.10
withholds exactly these permissions from automation so the identity that would benefit
from approving a release cannot grant that approval. Track E carries them, every task in
it marked `Assignment: User`.

`T-8E02` (the `production` environment and its reviewers) is the phase's
highest-cascade task — four tasks across two tracks stop without it — and its blocker is
repository-administration authority, not engineering effort. `T-8B01` (the production
image and the `.dockerignore` this repository does not yet have) is the highest-cascade
agent task; the same four tasks address the artefact it emits, by digest. The asymmetry
is worth stating plainly: this phase can have every agent task finished and still deliver
nothing, because a release nobody may approve is not a release.

Three dependencies are scheduled rather than left to be discovered:
`.github/workflows/quality.yml` is edited by three tasks in three different tracks, so
`T-8A03` is ordered ahead of `T-8C01` and `T-8T02`; `docs/README.md` would have been
edited by five Track D tasks, so the index edit is collapsed into `T-8D05` alone; and
`T-8T03` (the rehearsal) sits last by dependency, which makes it the natural casualty of
a compressed schedule exactly as `T-7T03`'s load test did — and it is no more optional
than that one was.

## Backlog

Registered specifications not scheduled into an active phase.

- [l1-room-reservation.md](specifications/l1-room-reservation.md) — **dormant module, deliberately deferred.** Its three tables (`reservations`, `room_availabilities`, `booking_settings`) and its `booking` / `payment` / `guest_accounts` registry rows ship **disabled** in Phase 1, and Phase 1's inertness test proves the module is absent rather than hidden. The capability itself is not in `[TZ]` §134's mandatory first release, and the previous implementation is explicitly not a migration source ([l2-data-model.md](specifications/l2-data-model.md) §2), so building the flow is scoped as its own future phase rather than smuggled into release one.

## Planning Audit

Findings from the adversarial review of this draft, recorded rather than resolved
silently.

**Optimism bias.** Phase 1 carries the entire ~50-table schema in one pass. That is not
a choice — [l2-data-model.md](specifications/l2-data-model.md) §6.1 requires it, and
translation tables must be created alongside their parents rather than retrofitted. The
risk is mitigated by splitting the migration work into four domain-grouped tasks rather
than one, so partial progress is visible. The realistic-volume seeder is the second
underestimation risk: generating tens of thousands of objects with spatial points and
per-language translations is real work, and every later benchmark depends on it, which
is why it stays in Phase 1 rather than being deferred to the first benchmark that needs
it.

**Hidden dependencies.** Phase 1's four tracks are **not** independent, and claiming
four-way parallelism would be false. The real shape is `A → B → (C ∥ D) → T`: the
scaffold must exist before migrations, migrations before seeders and models, and the
validation track consumes all three. Effective parallel degree in Phase 1 is two, not
four. Phases 2 through 7 have genuine parallel tracks; Phase 1 does not.

**Cascade risk.** `T-1D01` (scoped authorization resolution) is the single
highest-cascade task in the plan. Every screen in Phases 2 and 4 is permission-checked
against it, and [l2-tech-stack.md](specifications/l2-tech-stack.md) §6.4 states plainly
that retrofitting authorization is how authorization gaps happen. If it slips, Phases 2
and 4 stop entirely. `T-1B05` (index plan) has the second-largest blast radius: the
catalog ordering contract and the territory subtree expansion are the portal's hottest
paths, and both are unmeasurable without it.

**Plan stability.** Because no specification is `Stable`, every phase is provisional.
Phase 1 is the exception worth stating: of the twenty open questions the specification
set carries, only two touch Phase 1, and both are listed below. Phases 2 through 7
should be re-derived by `/magic.task` as the specifications mature, not treated as
settled.

## Open Questions Carried into Phases 1 and 2

Both questions that touched Phase 1's schema are now Phase 2 behaviour questions. The
schema was modelled to satisfy either reading, so neither costs a migration; both change
code and one changes a test matrix.

| Question | Spec | Exposure |
| --- | --- | --- |
| Do region-scoped permissions apply transitively down the territory subtree, or as explicit per-node grants? | [l1-back-office.md](specifications/l1-back-office.md) §2 | Phase 1 modelled it transitively, matching the geography spec's scoping semantics, and `role_scopes` shipped that way. Phase 2 exposure is `T-2T01`'s territory case, which asserts the transitive reading directly. Still the highest-value question to close. |
| Is partial acceptance of a moderated change set field-level, or whole-request only? | [l1-moderation-governance.md](specifications/l1-moderation-governance.md) §2 | `moderation_requests` stores previous and proposed data as snapshots, which supports both. Resolved inside `T-2D03` as implemented behind a portal setting, defaulting off. Whole-request-only would remove the setting and nothing else. |

The remaining eighteen open questions land in Phase 3 and later and are not on the
critical path out of Phase 2.

## Plan Status: Phase 8 Active

**Phases 1 through 7 are done — 135 of 135 tasks**, closed on 2026-08-20. A plan-wide L2
retrospective ran on that close — Signal 🟢 Green — and is recorded in
[RETROSPECTIVE.md](RETROSPECTIVE.md) Session 1.

**Phase 8 opens the plan again, and it was not a phase this plan anticipated.** Its two
specifications were authored on 2026-08-20 and are the only ones in the registry not
derived from `[TZ]` — the client technical specification constrains the server and names
the project's development stages, but is silent on how code reaches that server. The
requirement originates with the project owner: a configured CI/CD pipeline, a branch
model with automated dispatch, and deployment documentation written for a reader with no
technical background, in both launch languages, in a form an automated agent can also
consume. Both specifications reached `Stable` in the same pass, which makes Phase 8 the
first phase here planned against settled sources rather than provisional ones.

The gap it closes is narrow and total: the repository already carries half a pipeline —
a quality gate on every push and pull request, and a versioned commit hook — and nothing
at all beyond "accepted". No deployment, no release definition, no branch contract, no
protection rule, no rollback path, and no operator-audience or Russian-language
documentation. A quality gate with no delivery path behind it verifies changes that then
sit still.

**Next step:** `/magic.run main` — Phase 8's 20 tasks are decomposed and ready in
[tasks/phase-8.md](tasks/phase-8.md). Track E's three tasks are operator-performed and
gate four others; they can be started immediately and in parallel with the agent tracks.

Three items remain outside this phase deliberately, none of them blocking it. The
suite-wide `composer test:coverage` floor (78.3% against its own 80% minimum — a long
tail of ~20 pre-existing Phase 1–6 `Policy`/`Model` files) is scoped as its own future
cross-phase task; whether `composer test:coverage` should read `--group=slow` coverage
is an open quality-tooling question for whoever next revises the composer scripts; and
the 19 specifications still at `RFC` carry the set's real remaining design work — 18 of
them a live inline `TBD` — which `/magic.spec` addresses on its own schedule. None of
the three is a Phase 8 input, and folding any of them in would make a delivery phase
depend on work that has nothing to do with delivery.

Phase registry in [TASKS.md](TASKS.md). The first seven phases are archived at
[archives/tasks/phase-1.md](archives/tasks/phase-1.md),
[archives/tasks/phase-2.md](archives/tasks/phase-2.md),
[archives/tasks/phase-3.md](archives/tasks/phase-3.md),
[archives/tasks/phase-4.md](archives/tasks/phase-4.md),
[archives/tasks/phase-5.md](archives/tasks/phase-5.md),
[archives/tasks/phase-6.md](archives/tasks/phase-6.md), and
[archives/tasks/phase-7.md](archives/tasks/phase-7.md) respectively.
