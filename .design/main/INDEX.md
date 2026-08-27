# Workspace Specifications Registry

**Version:** 2.13.0
**Status:** Active

## Overview

Local registry of specifications for this workspace.

The specification set was restructured on 2026-08-05 against the client technical
specification (`.drafts/booking.md`), which redefined the product from a hotel
booking marketplace to a multi-country tourism information portal. See
[l1-platform-foundation.md](specifications/l1-platform-foundation.md) §5.3 for the
scope-delta ledger.

**Status posture (review-submission-gating pass, 2026-08-23):** the count is
**8 `Stable`, 17 `RFC`** — `l1-object-profile` returned to `RFC` the day after this
same file's own TBD-closing promotion, not because that promotion was wrong but
because a QA sweep found a second, un-asked question underneath the one that was
closed: authorship (who may be named) was resolved; gating (what stops abuse) was
not. The Review-Submission-Gating Ledger below records the amendment and the one
deliberate TBD it leaves open. Before this pass:

**Status posture (Phase 9 remediation pass, 2026-08-22):** the count is **9 `Stable`,
16 `RFC`** — `l1-platform-shell`, `l1-object-profile`, and `l1-public-api` promoted the
same day, each closing a live `TBD` blocking Phase 9's Tracks A/B/D (see the note
above the `TBD` list below). Earlier the same day, before this pass:

**Status posture (branch-model pass, 2026-08-22):** the count is **6 `Stable`,
19 `RFC`** — the delivery pair returned to `RFC` on the same day it had reached 8/17,
this time to reconcile the registry with an owner decision recorded elsewhere in the
repository, not to correct a newly found defect. The Branch-Model Ledger below records
it, including where the ledger's own first draft mis-stated the decision's origin.

Earlier the same day the count was 8/17. The 2026-08-20 stabilization pass promoted 6
of 25; the delivery pair left that set briefly on 2026-08-21 — reverted to `RFC` by the
constitution's amendment rule, then re-promoted the same day once the findings that
held them there were closed — and the Amendment Ledger below records that round trip.
A same-day URL-grammar pass added `l1-localization.md` and `l1-seo.md`; the
URL-Grammar Ledger below records why.

The gate for the remaining 17 is §2 of the project constitution — `RFC → Stable`
requires "no open questions", and each still carries a live inline `TBD` marker. Those
markers are the real remaining design work, and they are not uniform: several ask
questions the implementation has already answered in practice without the answer ever
being written back, while others are genuinely open product decisions. Per-file
reasons are in the Stabilization Ledger below.

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
| [l1-platform-foundation.md](specifications/l1-platform-foundation.md) | Foundation. Cross-cutting invariants: delivery (incl. accessibility), reach, domain, governance, commerce, evolution, privacy; delivery stages | Stable | 1 | 1.5.3 |
| [l1-feature-modules.md](specifications/l1-feature-modules.md) | Foundation. Administrator-toggleable capability modules; scoping ladder, dependencies, inertness, candidate modules | RFC | 1 | 0.2.1 |
| [l1-localization.md](specifications/l1-localization.md) | Foundation. Countries, languages (launch: EN + RU), per-entity translation model, phased activation | Stable | 1 | 0.3.0 |
| [l1-geography.md](specifications/l1-geography.md) | Foundation. Recursive territory hierarchy, per-country level vocabularies, landing pages | RFC | 1 | 0.1.1 |
| [l1-platform-shell.md](specifications/l1-platform-shell.md) | Public. Header, data-driven navigation, language and country switchers, footer, cookie notice, 404, legal pages | Stable | 1 | 0.3.1 |
| [l1-home-page.md](specifications/l1-home-page.md) | Public. Front-page block inventory, data sources, curation, four-viewport behaviour | Stable | 1 | 0.1.1 |
| [l1-object-catalog.md](specifications/l1-object-catalog.md) | Public. Object type registry, search, filters, tier-governed ordering, map | RFC | 1 | 1.1.1 |
| [l1-object-profile.md](specifications/l1-object-profile.md) | Public. Object page; direct-contact conversion contract, rooms, prices, services, reviews | RFC | 1 | 1.3.0 |
| [l1-availability-status.md](specifications/l1-availability-status.md) | Public. Owner-asserted "vacancies available" flag, staleness management | Stable | 1 | 0.2.0 |
| [l1-content-publishing.md](specifications/l1-content-publishing.md) | Public. Articles, news, and promotions; shared publication pipeline | RFC | 1 | 1.0.0 |
| [l1-seo.md](specifications/l1-seo.md) | Public. URL grammar, metadata, indexation policy, structured data, sitemaps, redirects | Stable | 1 | 0.2.0 |
| [l1-object-onboarding.md](specifications/l1-object-onboarding.md) | Owner. Object submission and the full owner cabinet lifecycle | RFC | 1 | 1.2.1 |
| [l1-back-office.md](specifications/l1-back-office.md) | Operator. Portal administration, scoped RBAC, staff account and grant administration, bulk operations, import/export, settings | RFC | 1 | 0.2.0 |
| [l1-moderation-governance.md](specifications/l1-moderation-governance.md) | Operator. Moderation modes and queue, audit journal, soft deletion, confirmation gates | RFC | 1 | 0.1.1 |
| [l1-notifications.md](specifications/l1-notifications.md) | Operator. Notification model, channel adapters, automated schedules, broadcasts | RFC | 1 | 0.1.1 |
| [l1-placement-monetization.md](specifications/l1-placement-monetization.md) | Commerce. Four placement tiers, packages, the granting act, bump mechanics, expiry, financial ledger | RFC | 1 | 0.2.0 |
| [l1-advertising.md](specifications/l1-advertising.md) | Commerce. Geo/language-targeted banners, slots, scheduling, promotional labels | RFC | 1 | 0.2.1 |
| [l1-analytics.md](specifications/l1-analytics.md) | Commerce. Event model, aggregation, traffic sources, owner and operator reporting, privacy bounds | RFC | 1 | 0.2.1 |
| [l1-public-api.md](specifications/l1-public-api.md) | Integration. Outward-facing REST contract, issued tokens, scoping, rate limits, documentation | Stable | 1 | 0.2.0 |
| [l1-release-operations.md](specifications/l1-release-operations.md) | Delivery. Promotion path, gate obligations, release records, the two reversal paths, operator documentation set, agent-decided vs. human-decided release actions, scoped development-phase gate-construction exception, standing autonomous-operation grant with its sensitive-zone circuit breaker, interim single-line branch state | RFC | 1 | 0.5.1 |
| [l1-room-reservation.md](specifications/l1-room-reservation.md) | Optional module — **disabled by default**. Booking: calendars, requests, prepaid checkout | RFC | 1 | 1.0.1 |
| [l2-data-model.md](specifications/l2-data-model.md) | Implementation. Consolidated table inventory, conventions, index plan, deletion and archival rules, schema deliverables | RFC | 2 | 0.3.1 |
| [l2-tech-stack.md](specifications/l2-tech-stack.md) | Implementation. Laravel 13 + Filament 5 + PostgreSQL/PostGIS + Redis; package set, bespoke surface, quality gates (incl. WCAG 2.2 AA + ARIA) and performance budgets, self-hosted deployment, dev/production environment configuration | Stable | 2 | 2.4.1 |
| [l2-third-party-integrations.md](specifications/l2-third-party-integrations.md) | Implementation. External services: storage, CDN, map tiles, SMTP, CAPTCHA, error tracking, dormant payment | RFC | 2 | 2.1.0 |
| [l2-release-pipeline.md](specifications/l2-release-pipeline.md) | Implementation. Git Flow branch contract, two GitHub Actions workflows, image digest as release artefact, pull-based deploy with health-assertion rollback, destructive-migration scan, automation identity permissions, sensitive-zone enforcement, EN/RU/agent documentation tree | RFC | 2 | 0.5.1 |

## Review-Submission-Gating Ledger (2026-08-23)

A functional QA sweep of the whole running instance (`.drafts/qa-deep-findings.md`)
found the review module had no submission path at all — reading and moderating a
review existed; nothing could create one. `l1-object-profile.md` §2 had already
flagged half the gap ("no public submission surface exists yet") but only resolved
review *authorship* (guest vs. registered), not what stops abuse once a surface
exists. This dispatch closes that second half, deciding it the same way the sweep's
own analysis reached it: neither "open, CAPTCHA-only" nor "gated behind a prior
contact click" dominates the other for a directory with no transactional record to
verify against, so both ship as an administrator-selectable portal setting rather
than one being picked once for the client — the same shape of decision
`moderation.default_mode` and `presentation.within_tier_order` already are.

**Amended (2).**

- `l1-object-profile.md` **1.2.0 → 1.3.0**, `Stable → RFC` by the amendment rule
  (substantive new invariant, not a typo). §2 gains the submission-gating decision
  and its reasoning; §3.4 gains the terse invariant plus the explicit server-side-
  enforcement requirement (matching this project's own "hiding a control is a
  usability affordance, never an access control" posture, applied here for the first
  time to a public-facing gate rather than a back-office one); §5.4's lifecycle
  diagram gains the gate as its own decision node ahead of submission, with an
  explicit note that the gate and the moderation checkpoint are independent controls.
  One `<!-- TBD -->` remains — an object with no active contact channel is
  permanently unreachable for review submission in `contact_gated` mode, and the
  right fallback is a product decision the spec defers rather than guesses at. That
  TBD is why the file stays `RFC` rather than re-promoting in the same pass.
- `l2-third-party-integrations.md` **2.0.0 → 2.1.0**, stays `RFC` (was not `Stable`).
  §5.5's blanket "Turnstile on reviews" is narrowed to the `open` mode only — the
  `contact_gated` mode's own click gate is the friction that mode relies on, and
  stacking Turnstile on top of it would be a second control on the weaker of the two
  signals, not genuine defence in depth.

**Quarantine (C12).** No specification declares `l1-object-profile.md` as its L1
parent (`Implements:` field checked against all four L2 files) — no cascade.

**Not addressed here.** Which of the twenty-two remaining `qa-deep-findings.md`
entries are pure implementation bugs against specifications that already state the
correct behaviour — confirmed against this pass's own reading of `l1-back-office.md`
(§5.1 already requires "Users & roles" and "Reviews" sections; §5.2 already leaves
the exact role-to-permission seed mapping as illustrative data, not a spec
commitment; §5.6 already requires the backup screen to raise failure notifications
rather than crash) — versus genuine specification gaps, is `/magic.task`'s
decomposition to make, not a further round of this workflow.

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

**Skipped — live `TBD` marker (18, now 14).** The constitution's §2 gate is "no open
questions", and each of these still carries one inline: `l1-advertising` (2),
`l1-analytics`, `l1-back-office`, `l1-content-publishing`, `l1-feature-modules`,
`l1-geography`, `l1-moderation-governance`, `l1-notifications`, `l1-object-catalog`,
`l1-object-onboarding`, ~~`l1-object-profile`~~, `l1-placement-monetization`,
~~`l1-platform-shell`~~, ~~`l1-public-api`~~, `l1-room-reservation`, ~~`l1-seo`~~,
`l2-data-model`, `l2-third-party-integrations`.

`l1-seo` left this list on 2026-08-22 — its `TBD` was the domain question, closed by
the URL-Grammar Ledger below. `l1-platform-shell` left it the same day: its TBD
(country-switcher navigate-vs-rescope, §2) had a single valid answer given the
constraint that an object page belongs to exactly one country — recorded as the
confirmed model rather than an open question. See its own Document History (v0.3.1).

`l1-object-profile` and `l1-public-api` left the same day, both via a direct project
owner decision rather than an inference from existing code. `l1-object-profile` §2's
review-authorship TBD is resolved as "administrator-configurable portal setting, not
a fixed policy" — the schema already supports either mode, and no public review-
submission surface exists yet to enforce it against. `l1-public-api` §2's TBD (named
consumer, rate-limit figures, republishing rights) is resolved by locking in the
spec's own already-described conservative shape as the settled decision rather than
an open question. See each spec's Document History (v1.2.0, v0.2.0).

These are not uniform. Some ask questions the delivered implementation has already
answered in practice — the answer was simply never written back into the specification,
which is the drift the plan-wide retrospective recorded. Others are genuinely open
product decisions (`l1-room-reservation`'s commission model, `l1-public-api`'s absent
consumer and rate limits). Closing them is design work, not a status edit, and it is
the precondition for the next stabilization pass.

**Skipped — superseded content (1). Closed 2026-08-22.** `l1-localization` §7 kept
per-country domains as "a documented later migration". The project owner retired that
on 2026-08-15: one origin, language as the leading path segment, no subdomains and no
per-country domains. Promoting the spec would have ratified a decision that had been
reversed. `l1-seo` §2 carried the same stale expectation and was held above for its
own `TBD`. Both are resolved in the URL-Grammar Ledger below.

**Advisory, non-blocking. Closed 2026-08-22.** `l1-platform-foundation` §5.1 framed the
URL grammar as an open choice — "prefix vs. domain vs. subdomain" — and delegated it to
`l1-localization` §5.3 and `l1-seo` §5.1. The delegation was structurally right and the
duplication rule puts the decision in those files rather than restated here; but both
referents were `RFC` and stale on exactly that point. Amending them resolved the
phrasing here at the same time, and surfaced that one of the two delegation targets was
wrong — see below.

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

## URL-Grammar Ledger (2026-08-22)

Three specifications still described per-country domains as a live deferred migration
five weeks after the project owner retired the option. This pass reconciled the record
to the decision. Nothing in the delivered design changed — `l1-seo` §5.1 already
specified the single-origin, language-prefixed grammar the owner chose — so the whole
correction is to the framing around it, and to what that framing was obliging
downstream sections to preserve.

**Amended (3).**

- `l1-localization` **0.2.1 → 0.3.0**, `RFC → Stable`. §7 records country-per-domain as
  retired rather than deferred, and states the consequence: downstream addressing rules
  are no longer obliged to stay portable to a multi-origin layout. This was the sole
  reason the specification was held back on 2026-08-20; with it closed and no inline
  `TBD` anywhere in the file, the §2 gate is satisfied.
- `l1-seo` **0.1.2 → 0.2.0**, `RFC → Stable`. §2's `TBD` — the domain question — is
  replaced by the settled constraint plus its effect on §3.1 and §3.3: a canonical URL
  and its alternates are always same-origin. §7 records the subdomain/domain option as
  retired. This was the file's only `TBD`.
- `l1-platform-foundation` **1.5.2 → 1.5.3**, stays `Stable`. §5.1 no longer frames the
  grammar as an open three-way choice. Patch, not minor: no invariant was added,
  changed, or removed, so the amendment rule's `Stable → RFC` transition does not fire
  and no C12 cascade reaches `l2-tech-stack`.

**Surfaced while amending.** §5.1 delegated the URL grammar to `l1-localization` §5.3
*and* `l1-seo` §5.1. The first reference is wrong — §5.3 specifies language resolution
and fallback and has never defined a URL shape. The grammar has exactly one owner,
`l1-seo` §5.1, and §5.1 now says so. A delegation pointing at a section that does not
contain the delegated content reads as correct for as long as nobody follows it, which
is why it survived three prior passes over this file.

**Open, not addressed here.** `l1-platform-foundation` §5.1's site map still lists the
back office at `/admin/**` and the owner cabinet at `/cabinet/**` as literal paths.
Both are configuration in the delivered system, and the staff panel's default is
deliberately *not* `/admin` — a guessable staff address attracts the credential-stuffing
traffic the sign-in throttle then absorbs. No specification in this workspace states
that requirement at all, so writing it into §5.1 would be a new requirement rather than
a correction: minor bump, `Stable → RFC`, and a C12 cascade quarantining
`l2-tech-stack`. That is a deliberate amendment to schedule, not a side effect to take
on inside a URL-grammar pass. `l1-back-office` is the natural home for the requirement
and is already `RFC`, so siting it there costs no cascade at all.

## Branch-Model Ledger (2026-08-22)

A live read of the repository, prompted by the project owner asking whether Phase 8 was
really the only outstanding work, found the delivery pair describing a repository that no
longer exists.

**What the read returned.** `master` is the sole remote head; `develop` does not exist.
`branches/master/protection` returns `404 Branch not protected`, `branches/develop/protection`
returns `404 Branch not found`, and `rulesets` returns `[]`. The repository is public and
on a personal account, so branch protection is available and its absence is not a plan
limitation. `.github/CODEOWNERS` is present in the tree. `T-8A01` had closed on a live
verification that returned real protection data for both branches, and `T-8F02`/`T-8F03`
on a read-back confirming the two branches matched field-for-field.

**The decision, and which side was wrong.** The owner had settled this in an earlier
session and restated it here: while the project is in development and has not been handed
to the client, work goes directly into `master`, with additional branches only on genuine
need. Full Git Flow, with partial or preferably full automation of the development flow,
arrives at the client's production launch. So the repository is correct and the
specifications were stale — the opposite of the usual direction, and the reason it is
worth recording rather than quietly amending.

**Amended (2), both `Stable → RFC` by the amendment rule.**

- `l1-release-operations` **0.4.0 → 0.5.0**. §3.3 gains a bounded interim clause: the
  multi-line promotion path is the target state, reached at launch, and until then there
  is one line. The obligations are not suspended — the transitions they govern do not
  occur. §5.5.2 records what the deferral costs, without softening it.
- `l2-release-pipeline` **0.4.0 → 0.5.0**. §5.2's table is reframed as the target state
  and the interim's own two rules are stated, so the period has a contract rather than an
  absence of one. §5.1's Current State row is corrected against the live read. §5.11
  gains an inertness notice and doubles as the restoration procedure.

**The finding that outlives the amendment.** With no protection rule on the production
line, `CODEOWNERS` takes effect nowhere, so both halves of the sensitive-zone boundary
are inert and §5.5.2's standing grant runs without the mechanical guard its own text
assumes. What stands in its place is a condition rather than a control: nothing is in
production, so the money, credential, and authorization surfaces have no live data behind
them and no deploy path in front of them. That is why the deferral is acceptable and
exactly why it cannot outlive itself — the breaker must be operative **before** the first
release, not restored after one.

`l1-release-operations` §5.5.2 now carries a third failure mode alongside the two the
2026-08-21 round trip produced. Declared-but-not-enforced and enforced-but-incomplete
were both about enforcement never built; this one is enforcement that was built,
verified live, and then deliberately taken down in the same decision that opened the
interim state — not lost to drift, but identical in effect. A declared zone, an
enforced zone, and a *currently* enforced zone are three different claims, and a live
read is what distinguishes them — cross-checked against the decision record that
explains what it should currently show, since a project's own operating decisions can
suspend enforcement on purpose.

**Quarantine (C12).** `l1-release-operations` dropping to `RFC` cascades to
`l2-release-pipeline`, already `RFC` here on its own amendment. No further dependents.

**Correction, same day (0.5.0 → 0.5.1, both specs).** This ledger's own first pass, and
the 0.5.0 specification text it summarized, framed the pause as newly discovered — "a
live read... found", "enforcement that was built, verified live, and later ceased to
exist... found on 2026-08-22". That is not what happened. The pause was decided and
recorded the same day, before this pass began, in the project's own engineering-
conventions document and branch-model runbook, with the prior working state (both
branches protected, `develop` present) preserved at a named git tag. The live read this
pass performed confirmed a documented decision; it did not uncover an undocumented one.
The paragraphs above are corrected in place rather than left with the wrong framing,
since — unlike the specification files, which carry their own dated history — this
registry entry has none. §3.3 also gained the resumption condition both external
documents already carried and this ledger's first pass omitted: a second developer
joining ends the interim as surely as client launch does, since the reasoning is about
headcount, not launch status.

**Not addressed here — plan layer.** `T-8A01`, `T-8F02`, and `T-8F03` stand `Done` with
`Verify` lines that no longer pass. Task records are `/magic.task`'s to reconcile, not
this workflow's.

## Meta Information

- **Maintainer**: Core Team
- **Last Updated**: 2026-08-23 (Review-submission-gating pass — `l1-object-profile` amended 1.2.0 → 1.3.0, `Stable → RFC` on a new invariant with one deliberate open TBD; `l2-third-party-integrations` amended 2.0.0 → 2.1.0, stays `RFC`; 25 specifications, 8 `Stable`)
- **Previously**: 2026-08-22 (Phase 9 remediation pass — `l1-platform-shell`, `l1-object-profile`, `l1-public-api` promoted RFC → Stable, closing their live TBDs; 25 specifications, 9 `Stable`. Earlier the same day: branch model reconciled to the owner's single-line development posture, then corrected same-day to attribute the pause to its actual recorded decision rather than to newly found drift; delivery pair back to `RFC` at 0.5.1)
