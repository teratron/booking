# Technology Stack

**Version:** 0.1.0
**Status:** Draft
**Layer:** implementation
**Implements:** l1-platform-foundation.md

## Overview

Concrete technology selection for the Booking platform, resolving the stack
proposed in the initiating request (React/Next.js, TypeScript, pnpm, Vite,
Astryx-or-Tailwind+shadcn/ui, Fallow, Biome, PostgreSQL) against the requirements
in [l1-platform-foundation.md](l1-platform-foundation.md). Per the requester's own
framing, this stack is expected to evolve as more of the Figma mechanics are
worked through; this spec is the running record of that evolution, not a one-time
decision.

## Related Specifications

- [l1-platform-foundation.md](l1-platform-foundation.md) - Invariants this spec implements.

## 1. Motivation

The request named two open forks explicitly ("React or Next.js" + "Vite", and
"Astryx under consideration OR Tailwind CSS + shadcn/ui") and asked for the
remaining elements to be evaluated for currency, plus for new stack elements to be
proposed as they become necessary. This spec resolves both forks against an
objective tiebreaker — the discoverability and hotel/room data-hierarchy
invariants from the foundation spec — rather than leaving them as unresolved
alternatives, and proposes the one missing layer (data access) the original list
did not cover.

## 2. Constraints & Assumptions

- Single deployable web application for the initial release (no monorepo split)
  — see §7 for when that assumption should be revisited.
- All version numbers below reflect latest-stable at time of authoring
  (2026-07-30) and are expected to drift; treat them as a floor, not a pin.
- `Fallow` in the original request is confirmed (via package research) to be
  **Fallow — codebase intelligence for TypeScript/JavaScript** (dead code,
  duplication, architecture-boundary, and design-system-drift detection), not a
  backend framework or ORM as its name might suggest. It is a dev-time tool,
  paired below with Biome under developer tooling.

## 4. Invariant Compliance

| L1 Invariant | Implementation |
| --- | --- |
| Responsive parity | A single Next.js component tree per page, styled mobile-first; the Figma desktop/mobile frame pairs map to one responsive implementation rather than two separate templates. |
| Localization-ready | `next-intl` (or equivalent App Router i18n routing) externalizes all copy from day one, even though only `ru` ships initially. |
| Public discoverability | Next.js App Router with Server Components / SSR for catalog, hotel-profile, and article routes gives crawlable HTML without a separate rendering layer. |
| Catalog structure | Server-side query layer (via the ORM, §5.4) implements filter/sort/paginate against PostgreSQL directly; no client-only filtering of a full dataset. |
| Hotel/room hierarchy | Enforced at the schema level: `room.hotel_id` is a required foreign key with no nullable variant. |
| Content moderation checkpoint | Deferred to domain design — flagged here as a required data-model field (`status: pending \| published \| rejected`) on submitted hotels and reviews; workflow specifics remain <!-- TBD: see l1-platform-foundation.md --> until product resolves the open question. |
| Actor roles | Deferred — no auth library is selected yet <!-- TBD: pending the actor-role question in l1-platform-foundation.md; candidates to evaluate once resolved are Auth.js or Better Auth, both Next.js-native -->. |
| Media resilience | Next.js `<Image>` with explicit fallback states covers lazy-loading, sizing, and failure placeholders without hand-rolled logic. |

## 5. Detailed Design

### 5.1 Application Framework — Next.js (resolves the React-vs-Next.js / Vite fork)

**Decision**: Next.js (App Router), not a React + Vite single-page app.

**Reasoning**: the foundation spec's discoverability invariant requires that
catalog, hotel-profile, and article pages be independently crawlable and
shareable — a client-rendered Vite SPA needs a separate SSR/prerendering layer to
achieve that, while Next.js provides it natively. Next.js Route Handlers and
Server Actions also cover the backend needs visible in the design (search/filter
queries, the add-hotel submission form) without standing up a separate API
service. This is an objective tiebreaker, not a stylistic preference, so it is
recorded as a resolved decision rather than an open fork.

Latest stable at authoring time: **Next.js 16.2.x**, **React 19**.

Vite is not discarded outright — it remains the right tool if an isolated
non-Next surface appears later (a component workshop, a standalone docs site);
it is simply not the primary application bundler while Next.js's own bundler
(Turbopack, stable as of v16) serves that role.

### 5.2 Language & Package Management

- **TypeScript** — strict mode, matching the original request; no change.
- **pnpm** — latest stable **10.30.x**; workspaces left unconfigured until a
  second deployable package exists (see §7).

### 5.3 UI / Styling Layer (resolves the Astryx-vs-Tailwind+shadcn/ui fork)

**Decision for v1**: Tailwind CSS + shadcn/ui.

**Reasoning**: Astryx (`facebook/astryx`) is confirmed real and credible — an
MIT-licensed, Meta-originated design system built on StyleX, 150+ components,
7 themes, explicitly interoperable with Tailwind — but it is currently in
**Beta**, ships chart components only under a `@canary` tag, and has a smaller
component surface than shadcn/ui's mature, Radix-based catalog. Reproducing the
pixel-specific catalog filters, room-detail popup, and multi-section onboarding
form seen in the Figma source is lower-risk against a mature, widely-documented
component base for a first release. This is not a rejection of Astryx — it is
recorded as a **Draft candidate to re-evaluate** once it exits Beta and its theme
set is confirmed to cover this project's visual language, since Astryx and
Tailwind are not mutually exclusive (Astryx explicitly supports Tailwind-class
overrides).

### 5.4 Data Layer (new element, proposed per the requester's invitation to add stack items as they emerge)

- **PostgreSQL** — latest stable major, **18.x**, per the original request.
- **Drizzle ORM** — proposed addition. The original list specified the database
  but not how the application talks to it. Drizzle is TypeScript-first,
  schema-as-code, and SQL-shaped rather than a heavy generated client —
  consistent with the fast, minimal-abstraction tooling philosophy already
  expressed by choosing Biome over ESLint+Prettier. Prisma is the noted
  alternative if a more batteries-included migration/studio experience is
  preferred later.

### 5.5 Developer Tooling

- **Biome** — lint + format, latest stable **v2.3+**, replacing ESLint/Prettier.
- **Fallow** — codebase-intelligence tool (dead code, duplication, architecture
  boundary, and design-system drift detection); dev-time only, no runtime
  footprint. Included as originally requested, with its role clarified per §2.

### 5.6 Project Structure

```plaintext
src/
├── app/                  # Next.js App Router routes
│   ├── (marketing)/      # home, catalog, hotel/[id], blog, blog/[id]
│   ├── add-hotel/
│   └── api/              # Route Handlers where a Server Action isn't a fit
├── components/           # shadcn/ui-based shared components
├── lib/
│   ├── db/                # Drizzle schema + client
│   └── i18n/
└── styles/
```

## 6. Implementation Notes

1. Framework + package manager + tooling (Next.js, pnpm, TypeScript, Biome,
   Fallow) — no open questions, safe to scaffold first.
2. Data layer (PostgreSQL + Drizzle schema for Hotel/Room per the foundation
   spec's entity relationship) — second, since domain specs depend on it.
3. UI layer (Tailwind + shadcn/ui) — alongside or after the data layer.
4. Auth — blocked on the actor-role open question; do not scaffold until
   resolved.

## 7. Drawbacks & Alternatives

- **Monorepo vs. single app**: a single Next.js app is chosen for v1 simplicity.
  Revisit (pnpm workspaces + a `packages/ui` split) only if a second deployable
  surface (e.g., an admin dashboard, a native app backend) is actually planned —
  not preemptively.
- **Astryx now instead of later**: rejected for v1 (see §5.3) on beta-maturity
  grounds, not on technical merit; this is the one recommendation most likely to
  change as the design system evolves, per the requester's own expectation.
- **Prisma instead of Drizzle**: viable alternative; Drizzle preferred for
  consistency with the lightweight-tooling direction already set by Biome.

## Canonical References

| Alias | Path | Purpose |
| --- | --- | --- |
| `[L1]` | `.design/main/specifications/l1-platform-foundation.md` | Invariants this stack must satisfy. |

## Document History

| Version | Date | Change |
| --- | --- | --- |
| 0.1.0 | 2026-07-30 | Initial draft; resolved framework and styling forks, proposed ORM addition, clarified "Fallow". |
