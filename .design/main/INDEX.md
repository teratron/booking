# Workspace Specifications Registry

**Version:** 2.8.0
**Status:** Active

## Overview

Local registry of specifications for this workspace.

The specification set was restructured on 2026-08-05 against the client technical
specification (`.drafts/booking.md`), which redefined the product from a hotel
booking marketplace to a multi-country tourism information portal. See
[l1-platform-foundation.md](specifications/l1-platform-foundation.md) §5.3 for the
scope-delta ledger.

**Status posture (stabilization pass, 2026-08-20):** that pass promoted 6 of 25
specifications to `Stable`, leaving 19 at `RFC`. The delivery pair left that set
briefly on 2026-08-21 — reverted to `RFC` by the constitution's amendment rule, then
re-promoted the same day once the findings that held them there were closed — so the
count is again **6 `Stable`, 19 `RFC`**. The Amendment Ledger below records the round
trip. The gate for the 19 is §2 of the project constitution —
`RFC → Stable` requires "no open questions", and 18 specifications still carry a live
inline `TBD` marker. Those markers are the real remaining design work: several ask
questions the implementation has already answered in practice without the answer ever
being written back. The nineteenth, `l1-localization.md`, is held for a different
reason — its §7 keeps per-country domains as "a documented later migration", which the
project owner retired on 2026-08-15 in favour of a single host with the language as
the leading path segment. Per-file reasons are in the Stabilization Ledger below.

A second, line-by-line pass over `[TZ]` on the same date closed six coverage gaps and
added three specifications: `l1-home-page.md` (§4/§5), `l1-public-api.md` (§19), and
`l2-data-model.md` (§21/§98 schema deliverables; the client has since waived that
section's approval gate). All 134 `[TZ]` sections are cited by at least one
specification.

All prose in this workspace is written in English with no Cyrillic anywhere, including
quoted `[TZ]` excerpts and the archived phase logs, so that any developer can read the
design record without knowing Russian. The one exception is `l1-geography.md` §5.2,
which keeps per-country territory level names in their own languages — including
Cyrillic and Georgian script — because the section exists to show that those
vocabularies differ; it is stored **data**, not prose, and carries English glosses in
place.

**Stack change (2026-08-05):** the implementation stack was replaced with
**Laravel 13 + Filament 5 + PostgreSQL/PostGIS + Redis**, self-hosted. The 19 Layer-1
specifications are technology-neutral and unaffected; the three Layer-2 documents were
rewritten. The previous Next.js/TypeScript implementation is preserved at git tag
`v0.1.34` and is not a migration source.

**Delivery pipeline (2026-08-20):** two specifications were added covering the one
domain nothing else described — how a change reaches production. `[TZ]` is silent here
(§22 constrains the server, §23 names the development stages, neither describes
delivery), so `l1-release-operations.md` and `l2-release-pipeline.md` originate with
the project owner rather than the client specification. They are written as a delta
against the repository's verified state: a quality gate and a commit hook already
exist; deployment, the branch contract, reversal, and operator-audience documentation
do not.

Grouping below is editorial (Foundation → Public → Owner/Operator → Commerce →
Delivery → Optional → Implementation); the registry itself is flat.

## Domain Specifications

| File | Description | Status | Layer | Version |
| --- | --- | --- | --- | --- |
| [l1-platform-foundation.md](specifications/l1-platform-foundation.md) | Foundation. Cross-cutting invariants: delivery (incl. accessibility), reach, domain, governance, commerce, evolution, privacy; delivery stages | Stable | 1 | 1.5.2 |
| [l1-feature-modules.md](specifications/l1-feature-modules.md) | Foundation. Administrator-toggleable capability modules; scoping ladder, dependencies, inertness, candidate modules | RFC | 1 | 0.2.1 |
| [l1-localization.md](specifications/l1-localization.md) | Foundation. Countries, languages (launch: EN + RU), per-entity translation model, phased activation | RFC | 1 | 0.2.1 |
| [l1-geography.md](specifications/l1-geography.md) | Foundation. Recursive territory hierarchy, per-country level vocabularies, landing pages | RFC | 1 | 0.1.1 |
| [l1-platform-shell.md](specifications/l1-platform-shell.md) | Public. Header, data-driven navigation, language and country switchers, footer, cookie notice, 404, legal pages | RFC | 1 | 0.3.0 |
| [l1-home-page.md](specifications/l1-home-page.md) | Public. Front-page block inventory, data sources, curation, four-viewport behaviour | Stable | 1 | 0.1.1 |
| [l1-object-catalog.md](specifications/l1-object-catalog.md) | Public. Object type registry, search, filters, tier-governed ordering, map | RFC | 1 | 1.1.1 |
| [l1-object-profile.md](specifications/l1-object-profile.md) | Public. Object page; direct-contact conversion contract, rooms, prices, services, reviews | RFC | 1 | 1.1.1 |
| [l1-availability-status.md](specifications/l1-availability-status.md) | Public. Owner-asserted "vacancies available" flag, staleness management | Stable | 1 | 0.2.0 |
| [l1-content-publishing.md](specifications/l1-content-publishing.md) | Public. Articles, news, and promotions; shared publication pipeline | RFC | 1 | 1.0.0 |
| [l1-seo.md](specifications/l1-seo.md) | Public. URL grammar, metadata, indexation policy, structured data, sitemaps, redirects | RFC | 1 | 0.1.2 |
| [l1-object-onboarding.md](specifications/l1-object-onboarding.md) | Owner. Object submission and the full owner cabinet lifecycle | RFC | 1 | 1.2.1 |
| [l1-back-office.md](specifications/l1-back-office.md) | Operator. Portal administration, scoped RBAC, bulk operations, import/export, settings | RFC | 1 | 0.1.1 |
| [l1-moderation-governance.md](specifications/l1-moderation-governance.md) | Operator. Moderation modes and queue, audit journal, soft deletion, confirmation gates | RFC | 1 | 0.1.1 |
| [l1-notifications.md](specifications/l1-notifications.md) | Operator. Notification model, channel adapters, automated schedules, broadcasts | RFC | 1 | 0.1.1 |
| [l1-placement-monetization.md](specifications/l1-placement-monetization.md) | Commerce. Four placement tiers, packages, bump mechanics, expiry, financial ledger | RFC | 1 | 0.1.1 |
| [l1-advertising.md](specifications/l1-advertising.md) | Commerce. Geo/language-targeted banners, slots, scheduling, promotional labels | RFC | 1 | 0.2.1 |
| [l1-analytics.md](specifications/l1-analytics.md) | Commerce. Event model, aggregation, traffic sources, owner and operator reporting, privacy bounds | RFC | 1 | 0.2.1 |
| [l1-public-api.md](specifications/l1-public-api.md) | Integration. Outward-facing REST contract, issued tokens, scoping, rate limits, documentation | RFC | 1 | 0.1.1 |
| [l1-release-operations.md](specifications/l1-release-operations.md) | Delivery. Promotion path, gate obligations, release records, the two reversal paths, operator documentation set, agent-decided vs. human-decided release actions, scoped development-phase gate-construction exception, standing autonomous-operation grant with its sensitive-zone circuit breaker | Stable | 1 | 0.4.0 |
| [l1-room-reservation.md](specifications/l1-room-reservation.md) | Optional module — **disabled by default**. Booking: calendars, requests, prepaid checkout | RFC | 1 | 1.0.1 |
| [l2-data-model.md](specifications/l2-data-model.md) | Implementation. Consolidated table inventory, conventions, index plan, deletion and archival rules, schema deliverables | RFC | 2 | 0.3.1 |
| [l2-tech-stack.md](specifications/l2-tech-stack.md) | Implementation. Laravel 13 + Filament 5 + PostgreSQL/PostGIS + Redis; package set, bespoke surface, quality gates (incl. WCAG 2.2 AA + ARIA) and performance budgets, self-hosted deployment, dev/production environment configuration | Stable | 2 | 2.4.1 |
| [l2-third-party-integrations.md](specifications/l2-third-party-integrations.md) | Implementation. External services: storage, CDN, map tiles, SMTP, CAPTCHA, error tracking, dormant payment | RFC | 2 | 2.0.0 |
| [l2-release-pipeline.md](specifications/l2-release-pipeline.md) | Implementation. Git Flow branch contract, two GitHub Actions workflows, image digest as release artefact, pull-based deploy with health-assertion rollback, destructive-migration scan, automation identity permissions, sensitive-zone enforcement, EN/RU/agent documentation tree | Stable | 2 | 0.4.0 |

## Rename Map (2026-08-05)

- `l1-hotel-discovery.md` → [l1-object-catalog.md](specifications/l1-object-catalog.md) — domain generalized from hotels to the administrator-managed object type registry.
- `l1-hotel-profile.md` → [l1-object-profile.md](specifications/l1-object-profile.md) — same, plus the conversion path changed to direct contact.
- `l1-property-onboarding.md` → [l1-object-onboarding.md](specifications/l1-object-onboarding.md) — same, plus widened from an intake form to the full owner cabinet.

`l1-room-reservation.md` was **not** renamed or deprecated; it was re-scoped as an
optional module (see [l1-feature-modules.md](specifications/l1-feature-modules.md)).

## Stabilization Ledger (2026-08-20)

Layer-ordered pass over all 25 specifications: L1 evaluated first, then L2 — which is
what let both L2 promotions satisfy the rule that an implementation spec needs a
`Stable` concept parent.

**Promoted (6).** `l1-platform-foundation`, `l1-home-page`, `l1-availability-status`,
`l1-release-operations`, then `l2-tech-stack` (parent: platform-foundation) and
`l2-release-pipeline` (parent: release-operations). All six: no open questions, no
hard-dependency cycle, layer constraint satisfied, Overview plus substantive design
sections present, Canonical References filled.

**Skipped — live `TBD` marker (18).** The constitution's §2 gate is "no open
questions", and each of these still carries one inline: `l1-advertising` (2),
`l1-analytics`, `l1-back-office`, `l1-content-publishing`, `l1-feature-modules`,
`l1-geography`, `l1-moderation-governance`, `l1-notifications`, `l1-object-catalog`,
`l1-object-onboarding`, `l1-object-profile`, `l1-placement-monetization`,
`l1-platform-shell`, `l1-public-api`, `l1-room-reservation`, `l1-seo`,
`l2-data-model`, `l2-third-party-integrations`.

These are not uniform. Some ask questions the delivered implementation has already
answered in practice — the answer was simply never written back into the specification,
which is the drift the plan-wide retrospective recorded. Others are genuinely open
product decisions (`l1-room-reservation`'s commission model, `l1-public-api`'s absent
consumer and rate limits). Closing them is design work, not a status edit, and it is
the precondition for the next stabilization pass.

**Skipped — superseded content (1).** `l1-localization` §7 keeps per-country domains
as "a documented later migration". The project owner retired that on 2026-08-15: one
host, language as the leading path segment, no subdomains and no per-country domains.
Promoting the spec would ratify a decision that has been reversed. `l1-seo` §2 carries
the same stale expectation and is already held above for its own `TBD`.

**Advisory, non-blocking.** `l1-platform-foundation` §5.1 still frames the URL grammar
as an open choice — "prefix vs. domain vs. subdomain" — and delegates it to
`l1-localization` §5.3 and `l1-seo` §5.1. The delegation is structurally right and the
duplication rule says the decision belongs in those files, not restated here; but both
referents are `RFC` and stale on exactly that point. Amending them resolves the
phrasing here at the same time.

## Amendment Ledger (2026-08-21)

Both delivery specifications were amended to 0.3.0 outside the specification workflow:
the file headers and their `Document History` rows were written, the registry entries
here were not. This pass reconciled the registry to the files and, because §2 of the
constitution makes a substantive minor bump a `Stable → RFC` transition, re-reviewed
both rather than restoring the badge they carried.

**Reconciled.** `l1-release-operations` and `l2-release-pipeline`, both 0.2.0 → 0.3.0
in this registry. The amendment itself is the owner's standing autonomous-operation
grant (L1 §5.5.2) and the matching extension of the automation identity's permissions
to `master` (L2 §5.10), plus L1 §3.9's interim clause naming the credential reality
the grant is made against.

**Re-review verdict: held at `RFC`.** Three findings block re-promotion. None of them
disputes the grant itself — the owner's decision stands as written; they concern the
gap between what §5.5.2 declares mechanical and what is mechanically true today.

- **L2 has no section for the mechanism that enforces §5.5.2.** The circuit breaker was
  implemented as `.github/CODEOWNERS` plus an architecture test asserting coverage, and
  documented for developers in `docs/release/branching.md` — after both specifications
  reached 0.3.0. No implementation specification describes it, so the one component that
  decides whether a change may merge unattended exists only in code. L2 is where that
  belongs.
- **The declared zone set and its enforced form diverge.** §5.5.2 names migrations and
  seeders touching `personal_access_tokens`, and "the Filament resources built on"
  the financial and placement services. Neither is matched by any `CODEOWNERS` pattern:
  the migration globs cover `*permission*` and `*role*` only, and no pattern names
  `app/Filament/` at all. Both are live money and credential surfaces that would merge
  unattended under the grant.
- **§5.5.2 names a path that does not exist.** `app/Http/Middleware/Authenticate*` has
  no referent in this application — the framework owns that middleware and it was never
  published into the tree. A declared zone with no file behind it cannot be checked.

The coverage test itself is not the guard §5.5.2 describes: it proves that a
hand-maintained list of fifteen representative paths is covered, not that the zone set
the specification declares is. That is why the first two findings can be true while the
suite is green.

**Path to `Stable`.** The remediation is code and L2 prose together — a `CODEOWNERS`
extension, a coverage check derived from the declared set rather than sampled beside
it, and an L2 section describing the mechanism. Only the last is writable from this
workflow; the rest is scheduled through the plan. Until then both specifications stay
`RFC`, which is accurate rather than inconvenient: the grant they describe goes live at
the first production release, and the gap should close before it does, not after.

**Quarantine (C12).** `l1-release-operations` dropping to `RFC` cascades to every
specification declaring it as parent — `l2-release-pipeline` alone, already `RFC` here
on its own amendment. No further dependents exist.

**Closed the same day — both re-promoted to `Stable` at 0.4.0.** All three findings
were resolved, and a fourth surfaced while resolving them.

- **Enforcement now covers what the policy declares.** The ownership file gained the
  credential-token migrations and every admin surface over money, its patterns became
  globs rather than one line per file, and it owns its own two halves. The coverage
  check was inverted to walk the real tree per zone instead of checking a hand-written
  path list — confirmed to genuinely fail by removing the money patterns and watching
  it name 19 uncovered files that no list had contained.
- **The empty zone is now declared as empty.** `app/Http/Middleware/Authenticate*` has
  no file behind it because the framework owns that middleware; L1 §5.5.2 now says so
  and explains why the path is declared regardless.
- **The mechanism has an L2 section.** New `l2-release-pipeline.md` §5.11 carries the
  ownership file, the tree-derived check, and the protection settings that make either
  bite — plus a compliance row in §4, which had none for §5.5.2. L1 delegates to it
  rather than restating it, so there is one description rather than two that can drift.
- **Fourth finding, surfaced by the read-back the third one required.** `CODEOWNERS`
  takes effect only where a branch requires a code owner's review. `develop` had that
  enabled; `master` did not, and required one approving review on every change instead —
  so the standing grant was not operative on `master` at all, while the sensitive-zone
  boundary was absent from it entirely. Both branches are now configured identically
  (`require_code_owner_reviews: true`, `required_approving_review_count: 0`), applied in
  that order deliberately: the reverse order opens a window with neither guarantee. §5.2's
  topology table, which still described the superseded configuration, was corrected to
  match, and §5.11 records the ordering constraint so a future revision inherits it.

The lesson is recorded in L1 §5.5.2 rather than only here: declaring a zone is not
enforcing it, both halves fail quietly, and neither may be inferred from the other.

## Meta Information

- **Maintainer**: Core Team
- **Last Updated**: 2026-08-21 (delivery pair reconciled, remediated, and re-promoted to `Stable` at 0.4.0 — 25 specifications)
