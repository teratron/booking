# Technology Stack

**Version:** 1.0.0
**Status:** RFC
**Layer:** implementation
**Implements:** l1-platform-foundation.md

## Overview

The concrete technology selection for the portal, re-evaluated end to end against the
client technical specification. This revision is a **major restructure**: the previous
version resolved a stack for a single-country hotel booking marketplace, and the
product it was solving for no longer exists
([l1-platform-foundation.md](l1-platform-foundation.md) §5.3).

The headline finding contradicts the framing of the question that prompted it. The
stack is **not excessive**. It is *mis-provisioned*: it carries a measurable amount of
verifiable dead weight, it duplicates one layer, and it is materially
**under-provisioned** for what the technical specification actually requires. Removing
dependencies and adding dependencies are both correct answers here, applied to
different parts of the same list.

## Related Specifications

- [l1-platform-foundation.md](l1-platform-foundation.md) - Invariants this stack must satisfy.
- [l2-third-party-integrations.md](l2-third-party-integrations.md) - Sibling L2; selects auth, storage, mail, queue, maps, and the conditional payment provider.
- [l1-back-office.md](l1-back-office.md) - The requirement that settles the admin-framework question.
- [l1-localization.md](l1-localization.md) - Five-language requirement driving the i18n layer.
- [l1-geography.md](l1-geography.md) - Recursive hierarchy driving a data-layer decision.
- [l1-analytics.md](l1-analytics.md) - High-volume event storage driving a partitioning decision.
- [l1-notifications.md](l1-notifications.md) - Scheduled jobs driving the background-execution decision.
- [l1-seo.md](l1-seo.md) - Rendering-strategy constraints.
- [l1-feature-modules.md](l1-feature-modules.md) - Runtime gating this stack must enforce server-side.

## 1. Motivation

Three questions were asked of the stack: is it excessive, can anything be replaced
with something better, and can the dependency count be reduced — without harming
quality. Answering them honestly required auditing actual imports rather than reading
the manifest, because a manifest records intent and imports record reality.

The audit found the two diverging in a specific, correctable way (§4), and it found
something more consequential: several capabilities the technical specification states
as requirements have **no dependency at all** behind them (§5.5). A stack review that
only subtracted would have left the project further from deliverable than it started.

## 2. Constraints & Assumptions

- Version numbers reflect latest-stable at authoring time (2026-08-05) and are a
  floor, not a pin.
- Findings in §4 were verified by import analysis against `src/`, not inferred from
  `package.json`. Each row states its evidence.
- The deployment target is **unresolved** and is the single largest open decision in
  this spec — see §5.9. Three selections in
  [l2-third-party-integrations.md](l2-third-party-integrations.md) depend on it.
- "Fallow" is confirmed as **Fallow — codebase intelligence for
  TypeScript/JavaScript** (dead code, duplication, architecture-boundary, and
  design-system-drift detection), a dev-time tool, not a backend framework.

## 4. Invariant Compliance

| L1 Invariant | Implementation |
| --- | --- |
| Responsive parity (4 viewports) | One Next.js component tree per page, styled mobile-first; Tailwind breakpoints cover phone/tablet/laptop/desktop from a single template. |
| Localization-completeness | `next-intl` for interface catalogs; per-entity translation **tables** in PostgreSQL for content ([l1-localization.md](l1-localization.md) §5.2). The former exists; the latter does not yet — see §5.5. |
| Public discoverability | App Router Server Components render catalog, territory, object, and article pages as crawlable HTML with no separate rendering layer. |
| Geographic scoping | Recursive territory hierarchy resolved via a materialized path column with a GIN-indexed ancestor lookup — see §5.4. |
| Object as central entity, type-varying attributes | Typed core columns plus a validated JSONB attribute bag keyed by the type's declared field set — see §5.4. |
| Paid-placement ordering | Server-side ordered query with scoped bump lookup; composite indexes per [l1-placement-monetization.md](l1-placement-monetization.md) §5.2. |
| Configuration over code | Settings, registries, and module state read from PostgreSQL at request time and cached; no build-time constants. |
| Capability modules toggleable | Server-side resolution at the route boundary; gated routes reject rather than hide ([l1-feature-modules.md](l1-feature-modules.md) §6.2). |
| Soft deletion, accountability | Schema-level `deleted_at` plus an append-only audit table with revoked UPDATE/DELETE privileges. |
| Privacy-minimal measurement | Date-partitioned raw event table compacted into daily aggregates ([l1-analytics.md](l1-analytics.md) §5.1). |
| Performance at scale | Redis-backed page and query caching, image derivatives, map clustering, and the index set in `[TZ]` §94 — none of which currently exist (§5.5). |
| Defense baseline | Better Auth rate limiting, two-factor, and CAPTCHA plugins; Drizzle's parameterized queries; React's default output escaping. |

## 5. Detailed Design

### 5.1 Audit: Verified Dead Weight

Each row was confirmed by counting importing files under `src/`.

| Package | Manifest | Imports found | Finding |
| --- | --- | --- | --- |
| `@radix-ui/react-accordion` | dependency | 0 | Remove |
| `@radix-ui/react-avatar` | dependency | 0 | Remove |
| `@radix-ui/react-checkbox` | dependency | 0 | Remove |
| `@radix-ui/react-dialog` | dependency | 0 | Remove |
| `@radix-ui/react-dropdown-menu` | dependency | 0 | Remove |
| `@radix-ui/react-radio-group` | dependency | 0 | Remove |
| `@radix-ui/react-select` | dependency | 0 | Remove |
| `@radix-ui/react-separator` | dependency | 0 | Remove |
| `@radix-ui/react-switch` | dependency | 0 | Remove |
| `@radix-ui/react-tooltip` | dependency | 0 | Remove |
| `vaul` | dependency | 0 | Remove |
| `@radix-ui/react-label` | dependency | 1 | Migrate to Base UI, then remove |
| `@radix-ui/react-popover` | dependency | 2 | Migrate to Base UI, then remove |
| `@radix-ui/react-slot` | dependency | 1 | Migrate to Base UI, then remove |
| `shadcn` | **dependency** | CLI, not imported | Move to devDependencies |

**Eleven packages are removable immediately** with no code change. Three more carry a
single vendored-file dependency each and go once migrated.

The cause is legible: the codebase migrated from Radix primitives to `@base-ui/react`
(19 files import Base UI, 3 import Radix), and the manifest was never pruned. This is
not architectural excess — it is an incomplete migration, and it is the only place the
"too many dependencies" concern is literally true.

### 5.2 Audit: The Admin-Kit Cluster

`lodash`, `inflection`, `diacritic`, `query-string`, `react-router`, `ra-core`,
`ra-data-simple-rest`, `ra-i18n-polyglot`, and `ra-language-english` — nine packages —
exist solely to support 107 vendored `shadcn-admin-kit` components under
`src/components/`, referenced by 84 files.

At first read this is the stack's heaviest cluster and the obvious candidate for
removal. **The audit reverses that conclusion**, on requirements rather than taste.

When the admin surface was four moderated resources, nine packages was a poor trade.
The technical specification now specifies **twenty-four back-office sections**
(`[TZ]` §102) with filtering, sorting, pagination, bulk operations, saved filters,
breadcrumbs, unsaved-change guards, preview, import/export, and scoped permissions —
and `[TZ]` §134 makes sixteen of them mandatory for release one
([l1-back-office.md](l1-back-office.md) §5.8). Hand-building that surface is a
multi-month project whose output would be strictly worse than the framework's.

Two properties bound the cost, and both were verified:

1. **The cluster is admin-only.** None of the nine packages is imported outside
   `src/components/` and `src/app/admin/`. Under route-level code splitting they do
   not enter the public marketplace bundle — the SEO-critical pages
   ([l1-seo.md](l1-seo.md)) are unaffected.
2. **The weight is already vendored.** The 107 components are source in this
   repository, not a versioned black box; they can be pruned to the resources actually
   used without fighting a dependency.

**Decision: keep.** The one worthwhile refinement is replacing full `lodash` with
`es-toolkit` (a modern, TypeScript-native, substantially smaller equivalent) across
the eight vendored files that use `get`, `isEqual`, `matches`, and `pickBy`. That is a
mechanical change with a real bundle benefit and no behavioural risk — but it is
optimization, not correction, and it ranks below §5.5.

### 5.3 Application Framework, Language, Tooling

Unchanged and re-confirmed against the new requirements.

- **Next.js (App Router)** — latest stable **16.2.x**, **React 19**. The
  discoverability invariant is now larger, not smaller: territory landing pages across
  three countries and five languages are the portal's primary organic surface
  ([l1-seo.md](l1-seo.md)). Server-rendered HTML without a separate rendering layer is
  the right answer, and more clearly so than before.
- **TypeScript** — strict, **7.x** (the Go-native compiler; substantially faster
  checking on a codebase this size).
- **pnpm** — **10.30.x**.
- **Tailwind CSS 4 + shadcn/ui on Base UI** — retained. The
  `class-variance-authority` + `cn()` composition model in §5.7 is what makes the
  administrator-configurable card decorations in
  [l1-advertising.md](l1-advertising.md) §5.5 expressible without a component per
  variant.
- **Biome 2.x**, **Fallow 3.x**, **Vitest 4.x** — retained.

Fallow deserves a note: it detects dead code and unused exports, and §5.1's eleven
unused packages are exactly the class of drift it exists to catch. Adding a manifest-
versus-imports check to the audit gate would have surfaced this without a manual pass.

### 5.4 Data Layer

- **PostgreSQL 18.x** and **Drizzle ORM** — retained. Drizzle's schema-as-code suits a
  model this index-heavy (`[TZ]` §94 names fourteen indexed fields), and its SQL-shaped
  API matters for the ordering query in
  [l1-placement-monetization.md](l1-placement-monetization.md) §5.2, which an
  abstraction-heavy ORM would obscure.

Three modelling decisions that the new requirements force:

**Territory hierarchy** ([l1-geography.md](l1-geography.md) §5.4). Descendant
expansion runs on every catalog view. A recursive CTE per request is the wrong cost;
a **materialized path** column with a denormalized `country` reference answers ancestor
and descendant queries with an index scan. A closure table is the alternative, and
loses on write amplification during the bulk territory imports `[TZ]` §127 expects.

**Type-varying object attributes**
([l1-object-catalog.md](l1-object-catalog.md) §5.5). `[TZ]` §69 requires new object
types without a developer, and §109 requires different field sets per type. Frequently
filtered attributes stay as typed columns; the type-specific remainder lives in a
**JSONB bag validated against the type's declared schema**, with GIN indexes on the
filterable keys. Full EAV is rejected — it makes every catalog query a self-join
against the portal's hottest table.

**Translation tables** ([l1-localization.md](l1-localization.md) §5.2). Per-entity
translation rows, uniquely keyed on `(entity, language)`, indexed on
`(language, slug)`. This is the largest schema change the new specification demands,
and it is the one that must land in the same migration as the entities it localizes.

**Statistics** ([l1-analytics.md](l1-analytics.md) §5.1). Date-partitioned raw events
compacted into daily aggregates; native PostgreSQL partitioning, no additional
dependency.

### 5.5 Missing Capabilities

This is the section that answers "is the stack excessive" with a plain no. Each row is
a technical-specification requirement with **nothing currently behind it**.

| Requirement | Source | Gap | Selection |
| --- | --- | --- | --- |
| Caching | `[TZ]` §18, §22 | None | **Redis** (named explicitly by the client) |
| Job queue and scheduling | `[TZ]` §22, §52, §62, §117, §123, §97 | None | **BullMQ** on the same Redis — see §5.6 |
| Transactional email | `[TZ]` §49, §62, §124, §130 | None | Provider-agnostic SMTP — [l2-third-party-integrations.md](l2-third-party-integrations.md) §5.6 |
| Object storage | `[TZ]` §22, §75, §97 | `@vercel/blob` (5 files) — platform-locked | S3-compatible — §5.9 |
| Image derivatives | `[TZ]` §33, §75, §130 | None | **sharp** for stored thumbnails and size limits |
| Map clustering | `[TZ]` §15 | `leaflet` present, no clustering | **supercluster**; MapLibre GL as the scale escalation |
| XLSX / CSV import-export | `[TZ]` §96, §127, §128 | None | **ExcelJS** (reads and writes both formats) |
| Full-text search | `[TZ]` §14, §94 | None | PostgreSQL FTS + `pg_trgm` GIN — escalate only on measurement |
| Two-factor, rate limiting, CAPTCHA | `[TZ]` §17, §100, §130 | None | Better Auth plugins — no new vendor |
| Scoped RBAC | `[TZ]` §73, §121 | `role` enum only | Own schema — [l1-back-office.md](l1-back-office.md) §5.2 |
| Audit journal, soft delete | `[TZ]` §91, §95 | None | Own schema, no dependency |
| Four of five locales | `[TZ]` §1.4 | Only `messages/ru.json` exists | Content, not dependency |

Two entries deserve emphasis.

**Search is deliberately not a new service.** PostgreSQL full-text search with
trigram indexes will very likely satisfy `[TZ]` §14 at this data volume, and adding
Meilisearch or Typesense pre-emptively would introduce an index to synchronize, a
service to operate, and a consistency problem to debug. The escalation trigger should
be a measured p95 latency on the real catalog, not an assumption.

**The RBAC gap is larger than it looks.** Better Auth supplies roles and permissions;
it does not supply `[TZ]` §121's scoping of a permission to a country, territory
subtree, or object category. That resolution logic is this project's to build, and it
sits on the authorization path of every back-office request.

### 5.6 Background Execution — the Architectural Finding

[l1-notifications.md](l1-notifications.md) §5.4 enumerates recurring work: placement
expiry sweeps, staleness sweeps, availability-confirmation prompts, promotion
archival, dispatch retries, statistics rollups, sitemap regeneration, and backups.

None of these is a request. They are scheduled and long-running, and several must
survive a deployment mid-run.

**This is the first genuine second deployable this project has had.** The previous
revision deferred a workspace split until "a second deployable surface actually
exists" and correctly ruled that the admin panel — a route inside the app — was not
one. A background worker is: a separate process, a separate lifecycle, a separate
scaling profile, and a separate failure mode.

**Decision**: introduce a worker process sharing the schema and business logic in
`lib/`, run from the same repository. The concrete shape — a second entry point, a
pnpm workspace split, or a separate deploy target — is bounded by §5.9 and should be
settled with it rather than independently.

**BullMQ over pg-boss**: pg-boss avoids Redis entirely and is attractive when Redis
would be a new component. Here `[TZ]` §18 and §22 name Redis as a requirement for
caching regardless, so BullMQ reuses infrastructure the portal must operate anyway,
rather than adding a second job substrate on top of the primary database — which is
also the database serving every catalog query.

### 5.7 Project Structure

```plaintext
src/
├── app/
│   ├── [lang]/               # language-prefixed public routes
│   │   ├── (marketing)/      # home, territory pages, catalog, object, blog, news
│   │   ├── cabinet/          # owner cabinet
│   │   └── (legal)/
│   ├── admin/                # back office mount point
│   └── api/
│       ├── admin/            # REST surface the back office reads
│       └── auth/
├── components/
│   ├── ui/                   # shadcn primitives on Base UI
│   └── admin/                # vendored shadcn-admin-kit (§5.2)
├── lib/
│   ├── db/                   # Drizzle schema, client, migrations
│   ├── auth/                 # Better Auth + scoped RBAC resolution
│   ├── i18n/                 # catalogs + entity translation resolution
│   ├── geo/                  # territory hierarchy, scope expansion
│   ├── catalog/              # retrieval, ordering, filters
│   ├── placement/            # packages, bumps, expiry
│   ├── advertising/          # banner targeting and selection
│   ├── moderation/           # queue, audit journal
│   ├── analytics/            # event capture, rollups
│   ├── notifications/        # notification model, channel adapters
│   ├── modules/              # feature-module resolution
│   ├── reservation/          # dormant booking module (gated)
│   └── seo/                  # metadata, sitemaps, redirects
├── worker/                   # [ADDED] scheduled jobs (§5.6)
└── styles/
```

Business logic stays in `lib/`, shared verbatim between the web app and the worker —
which is the property that makes §5.6's split cheap rather than a fork.

`lib/reservation/` is retained and gated, not deleted
([l1-room-reservation.md](l1-room-reservation.md) §6.1).

### 5.8 Component Architecture Principles

Retained from the previous revision and now load-bearing rather than aspirational:
`[TZ]` §113 lets an administrator define card decorations — border colour, badge
colour, icon, position — at runtime. That is only expressible against a variant-driven
component model. Every UI element is built reusable, composable, and extensible by
default; variant and size axes are expressed through `class-variance-authority` rather
than duplicated components; `cn()` (`clsx` + `tailwind-merge`) allows call-site
override without prop-drilling; primitives wrap Base UI so new visual variants are a
styling change, not new interaction logic.

The boundary is unchanged: this governs shared, cross-feature UI. A genuinely
page-local one-off is not required to be generalized ahead of a second use site.

### 5.9 Deployment — the Open Fork

`[TZ]` §22 requires PostgreSQL, Redis, queues, cron, and file storage. `[TZ]` §97
requires daily automated database backups, separate media backups, several retained
generations, integrity verification, and a documented restore procedure. `[TZ]` §131
requires an administrator-triggered restore.

Those requirements point away from a purely managed platform and toward a
self-operated deployment, but the choice is the client's, not this spec's — and it
determines three selections in
[l2-third-party-integrations.md](l2-third-party-integrations.md).

| | Managed platform | Self-hosted (VPS / Docker) |
| --- | --- | --- |
| Worker (§5.6) | Separate service or external scheduler | Native second process |
| Storage | Platform blob store (current `@vercel/blob`) | MinIO or an S3-compatible provider |
| Redis | Managed add-on | Native |
| Backup / restore (`[TZ]` §97, §131) | Provider-dependent; §131's admin-triggered restore is awkward | Direct control |
| Operating cost | Higher at traffic; lower in effort | Lower at traffic; requires operations capability |

**Recommendation**: choose an **S3-compatible storage interface regardless of the
answer**. Cloudflare R2, MinIO, and Backblaze all speak it, and so do managed
platforms via a compatible bucket. That single decision makes storage portable and
keeps `@vercel/blob` from becoming a migration this project has to pay for later.

<!-- TBD: the deployment target is the highest-value unresolved decision in this
     spec. It is a client/business decision with no technical tiebreaker: [TZ] §22
     and §97 are satisfiable both ways at different cost profiles. -->

## 6. Implementation Notes

Sequenced by dependency, not by visibility.

1. **Prune §5.1.** Eleven packages, zero code change. Add a manifest-versus-imports
   check to the Fallow audit gate so the drift cannot silently return.
2. **Settle §5.9.** Three integration selections are blocked behind it, and the answer
   is cheap to obtain and expensive to reverse.
3. **Re-model the schema**: territories, object types with JSONB attributes,
   translation tables, contact channels, placement, banners, moderation, audit,
   statistics. Model the whole graph in one pass — every domain spec reads from it,
   and the current hotel-centric schema cannot be extended into it incrementally.
4. **Add the missing infrastructure** (§5.5): Redis, BullMQ and the worker, mail,
   S3-compatible storage, sharp, ExcelJS, supercluster.
5. **Build scoped RBAC** before any back-office screen. Every screen depends on it and
   retrofitting authorization is how authorization gaps happen.
6. **Then the surfaces**, in `[TZ]` §134's priority order.
7. Keep `lib/reservation/` under test with its module both on and off
   ([l1-feature-modules.md](l1-feature-modules.md) §6.3).

## 7. Drawbacks & Alternatives

**Rebuilding on a different stack entirely.** The product definition changed enough to
make the question fair. It does not survive contact with the audit: Next.js, Drizzle,
PostgreSQL, Tailwind, Better Auth, and react-admin are all *more* justified under the
new requirements than the old ones — five languages, deep SEO, twenty-four admin
sections, and runtime-configurable everything all play to this stack's strengths. What
changed is the data model and the missing infrastructure, and neither is a framework
problem.

**Dropping react-admin to cut nine dependencies.** The most attractive-looking saving
in the manifest, and the analysis in §5.2 inverts it: the admin surface grew sixfold,
the cluster is admin-only, and hand-building `[TZ]` §134's sixteen mandatory sections
would cost far more than nine admin-scoped packages.

**pg-boss instead of Redis and BullMQ.** Genuinely appealing — one fewer component —
and rejected because `[TZ]` §18 and §22 require Redis for caching anyway, so pg-boss
would add a second job substrate rather than remove infrastructure, and would put job
polling on the database serving every catalog query.

**Adding a dedicated search engine now.** Meilisearch or Typesense would be
comfortably better than PostgreSQL FTS at some scale. Adding one before measuring buys
an index to synchronize and a service to operate against a bottleneck that has not
been demonstrated. §5.5 records the escalation trigger instead.

**Keeping `@vercel/blob`.** Fine if the deployment answer is Vercel and a liability
otherwise. The S3-compatible recommendation in §5.9 costs nothing today and removes a
migration risk regardless of how §5.9 resolves.

**Full EAV for type-varying attributes.** Maximally flexible, and it would make the
catalog query — the portal's hottest path — a self-join over its largest table. The
typed-columns-plus-validated-JSONB model in §5.4 keeps filterable attributes indexable.

## Canonical References

| Alias | Path | Purpose |
| --- | --- | --- |
| `[TZ]` | `.drafts/booking.md` | §14–§22, §69, §94, §97, §102, §109, §121, §127–§131, §134 — requirements driving these selections. |
| `[L1]` | `.design/main/specifications/l1-platform-foundation.md` | Invariants this stack must satisfy. |
| `[L2-INTEGRATIONS]` | `.design/main/specifications/l2-third-party-integrations.md` | Auth, storage, mail, queue, maps, and conditional payment selections. |

## Document History

| Version | Date | Change |
| --- | --- | --- |
| 0.1.0 | 2026-07-30 | Initial draft; resolved framework and styling forks, proposed ORM addition, clarified "Fallow". |
| 0.2.0 | 2026-07-30 | Reconciled §4 against l2-third-party-integrations.md; added admin and auth entries to the structure. |
| 0.3.0 | 2026-07-30 | Added Component Architecture Principles; showed the `components/ui/` split. |
| 0.3.1 | 2026-07-30 | Clarification: showed the `api/admin/` REST surface. |
| 1.0.0 | 2026-08-05 | Major restructure against the client technical specification. Added the verified dependency audit (§5.1, eleven removable packages), the admin-kit keep decision on requirement grounds (§5.2), three forced data-model decisions (§5.4), the missing-capability ledger (§5.5), the background-worker finding that triggers the first genuine second deployable (§5.6), and the deployment fork (§5.9). Restructured rather than delta-edited: the product premise the previous version resolved against no longer exists, leaving no section unaffected. |
