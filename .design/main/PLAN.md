# Implementation Plan

**Version:** 3.1.1
**Generated:** 2026-08-05
**Based on:** .design/main/INDEX.md v2.4.2
**Based on RULES:** .design/RULES.md v1.4.0
**Status:** Active (Bootstrap — tentative)

## Overview

Delivery plan for the international tourism portal: a self-hosted Laravel 13 monolith
serving visitors (browse objects, contact owners directly), object owners (self-service
cabinet), and portal staff (back office), across three countries and two launch
languages.

The plan is built from 23 specifications, all at `RFC`. No specification has reached
`Stable`, and `[Bootstrap Plan]` marks that: every phase below is planned tentatively
and is finalized as the specification set matures through its lifecycle. Phase 1 is the
least exposed to that uncertainty — infrastructure, schema, registries, and
authorization are stable under all but two of the open questions, both listed in
§Open Questions Carried into Phase 1.

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

## Phase 4 — Owner Cabinet — **Active**

*The second Filament panel: the same toolkit, owner-scoped in both query and policy.*

- [ ] **Object Onboarding & Owner Cabinet** — the full cabinet lifecycle ([l1-object-onboarding.md](specifications/l1-object-onboarding.md)) [L1]
- [ ] **Availability Status** §5.3 — the one-tap toggle, no form, no save step ([l1-availability-status.md](specifications/l1-availability-status.md)) [L1]
- [ ] **Analytics** §5.4 — cabinet dashboard and statistics surfaces ([l1-analytics.md](specifications/l1-analytics.md)) [L1]
- [ ] **Content Publishing** §3.3–§3.4 — owner-authored news and promotions ([l1-content-publishing.md](specifications/l1-content-publishing.md)) [L1]

## Phase 5 — Public Site

*Built against the Figma source, node by node. Server-rendered Blade with Livewire for
catalog interactivity.*

- [ ] **Platform Shell** — header, data-driven navigation, both switchers, footer, 404, legal pages ([l1-platform-shell.md](specifications/l1-platform-shell.md)) [L1]
- [ ] **Home Page** — the sixteen-block composition across four viewport classes ([l1-home-page.md](specifications/l1-home-page.md)) [L1]
- [ ] **Object Catalog** §5.1–§5.4 — search, filters, tier-governed ordering, clustered map ([l1-object-catalog.md](specifications/l1-object-catalog.md)) [L1]
- [ ] **Object Profile** — page composition and the contact rail conversion contract ([l1-object-profile.md](specifications/l1-object-profile.md)) [L1]
- [ ] **Geography** §5.3 — territory landing pages ([l1-geography.md](specifications/l1-geography.md)) [L1]
- [ ] **Content Publishing** §5.3 — public blog, news, and promotion surfaces ([l1-content-publishing.md](specifications/l1-content-publishing.md)) [L1]
- [ ] **Analytics** §3.1–§3.2 — first-party event emission from every measured surface ([l1-analytics.md](specifications/l1-analytics.md)) [L1]
- [ ] **Third-Party Integrations** §5.3, §5.5 — map tiles and CAPTCHA ([l2-third-party-integrations.md](specifications/l2-third-party-integrations.md)) [L2]

## Phase 6 — Discovery, Reporting & Public API

- [ ] **SEO** — URL grammar, metadata resolution, indexation policy, structured data, sitemaps, redirects ([l1-seo.md](specifications/l1-seo.md)) [L1]
- [ ] **Analytics** §5.3, §5.5–§5.6 — portal-wide reporting, exports, traffic sources ([l1-analytics.md](specifications/l1-analytics.md)) [L1]
- [ ] **Public API** — versioned read contract, issued tokens, scoping, rate limits, generated documentation ([l1-public-api.md](specifications/l1-public-api.md)) [L1]

## Phase 7 — Operations & Launch Readiness

- [ ] **Back Office** §5.7 — import and export pipeline with column mapping and error report ([l1-back-office.md](specifications/l1-back-office.md)) [L1]
- [ ] **Back Office** §5.6 — backups, retention, integrity verification, rehearsed restore ([l1-back-office.md](specifications/l1-back-office.md)) [L1]
- [ ] **Third-Party Integrations** §5.2, §5.6–§5.8 — CDN, error tracking, production provisioning ([l2-third-party-integrations.md](specifications/l2-third-party-integrations.md)) [L2]
- [ ] **Technology Stack** §5.9 — load test against the stated budgets, Laravel Pulse in production ([l2-tech-stack.md](specifications/l2-tech-stack.md)) [L2]

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

## Next Step

`/magic.task main` — decompose Phase 4 (Owner Cabinet) into atomic tasks; phase registry
in [TASKS.md](TASKS.md). Phases 1 through 3 are complete and archived at
[archives/tasks/phase-1.md](archives/tasks/phase-1.md),
[archives/tasks/phase-2.md](archives/tasks/phase-2.md), and
[archives/tasks/phase-3.md](archives/tasks/phase-3.md) respectively.

Phases 4 through 7 carry frontmatter and scope only. They are decomposed into atomic
tasks by the `/magic.task` invocation that activates each, so the decomposition is
derived from the specification set as it stands then rather than as it stands now.
