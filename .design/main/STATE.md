# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-05 14:08
**Phase:** 0 - Concept re-baseline + stack pivot
**Status:** Active

## Current Position

- **Task:** Stack replaced. Laravel 13 + Filament 5 + PostgreSQL/PostGIS + Redis, self-hosted monolith. TypeScript implementation removed (275 files) and preserved at tag `v0.1.34`. Project version 0.1.34 → 0.2.0.
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral and unchanged by the pivot; the 3 L2 documents were rewritten. TZ coverage 134/134, registry parity clean.
- **Next Action:** `/magic.task main` to generate the plan. No gates remain — §98 approval waived, deployment fork closed.

## Progress

```
Specification:  [23/23] re-baselined + re-targeted, all RFC, review pending
Plan:           NOT GENERATED — awaiting spec review
Implementation: RESET — new stack, no code yet (previous work at tag v0.1.34)
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-05 **Decision:** `[TZ]` §98 client approval of the DB structure **WAIVED** — the client is not a DB specialist and delegates all technical decisions to engineering judgment. The gate is gone; the artefacts remain, expressed as the migration set (`migrate:fresh --seed` is the field/type/key list) plus a generated ER diagram. Consequence to hold: §98 existed to protect the client from a self-serving schema, so that protection now rests entirely on the specification layer being auditable by someone who was not present for the decisions.
- 2026-08-05 **Language policy applied to `.design/`, archives included.** All prose is English with zero remaining Cyrillic, including quoted `[TZ]` excerpts (18 spec files, patch bumps) and the archived phase logs. The archives were edited by explicit client direction — the project may be sold, so every document must be readable by a developer anywhere, with no Russian text surviving anywhere in the tree — which overrides the engine's "archives are immutable" rule; the edit is recorded here so the log is not mistaken for silent tampering. Literal UI strings the deleted TypeScript app rendered (e.g. a "Sign in" button label) are now translated in place rather than quoted-and-glossed: an initial pass kept the original Cyrillic alongside a gloss, but the client confirmed the goal is full readability, not verbatim quotation, so the Russian original was dropped and only the English rendering kept. The one deliberate exception is `l1-geography.md` §5.2, which keeps per-country territory level names in their own languages because the section's entire point is that the vocabularies differ; English glosses are added there.
- 2026-08-05 **Standing instruction: continuous quality gates.** Lint, format, static analysis, tests, benchmarks, convention checks and docs run after every meaningful change — not at task end. Wired as `composer quality` so local and CI are identical. Conventions enforced by Pest `arch()` tests rather than review, including the SDD-containment rule. Benchmarks run against seeded realistic volume, never fixtures. Documentation in English for the client's future maintainer. Detail in CLAUDE.md "Engineering Discipline"; budgets in `l2-tech-stack.md` §5.9.
- 2026-08-05 **Standing instruction:** all page markup is built **from the Figma source via MCP**, never from a prose description. File `N2cVVIS5wvjHIviP27peuX`, page `0:1`; access verified working. Design tokens go into the Tailwind theme once; Figma governs visual language and composition only — behaviour comes from the specifications, and the specification wins on conflict.
- 2026-08-05 **Decision:** **Stack replaced: Laravel 13 + Filament 5 + PostgreSQL/PostGIS + Redis**, self-hosted monolith. Rationale in `l2-tech-stack.md` §1. The v1.x argument for Next.js was wrong on two counts — it conflated SEO indexability with client interactivity (Blade renders equally crawlable HTML), and it anchored on an existing codebase whose schema needed full replacement anyway. Decisive factor: §99–134 + §29–43 (back office + owner cabinet) are more than a third of the TZ, and Filament delivers both from one toolkit; ten packages cover eleven TZ sections. Go was evaluated and rejected — no Filament-class admin toolkit, and the performance premise does not hold at ~30–60k objects behind Redis.
- 2026-08-05 **Note:** Deployment fork CLOSED — self-hosted, driven by `[TZ]` §97/§131 (off-server backups, administrator-triggered restore). This unblocked the last of the three storage/queue/mail selections and the §98 backup-scheme deliverable.
- 2026-08-05 **Finding:** Second line-by-line TZ pass found **6 gaps** the first pass missed, all closed. New specs: `l1-public-api.md` (§19 — REST API/tokens/docs had zero coverage), `l1-home-page.md` (§4/§5 — 16-block composition unowned), `l2-data-model.md` (§21/§98). Amendments: favorites (§8), traffic-source analytics (§23), candidate-module catalogue (§23/§64). One was worse than a gap — `l1-advertising.md` §2 flatly excluded a self-service advertiser cabinet that TZ §23 actually *recommends*; corrected to a deferred candidate.
- 2026-08-05 **Decision:** Booking is **preserved, not deprecated**. Per explicit product direction and `[TZ]` §63–64, the reservation work (schema, checkout flow, Fondy adapter — preserved at tag `v0.1.34`) is retained behind an administrator-toggleable module registry (`l1-feature-modules.md`), disabled by default. Booking and payment are separate module rows, so "dated request + owner confirmation, no payment provider" is a supported intermediate state.
- 2026-08-05 **Decision:** Launch locales narrowed to **English + Russian only**; Romanian, Ukrainian, Georgian deferred until after project completion and activated from the back office (`l1-localization.md` §5.6). Content decision, not a capability one — translation tables, per-language slugs, hreflang, and fallback still ship in the first migration. Consequence: no launch country's own primary language is active at release, so country records reference inactive languages and must resolve via fallback rather than fail validation.
- 2026-08-05 **Decision:** Specs re-baselined against the client TZ. Product changed from hotel booking marketplace → 3-country multi-language tourism information portal. 3 renames (hotel-discovery→object-catalog, hotel-profile→object-profile, property-onboarding→object-onboarding), 10 new specs, 7 amended. All set to `RFC` (not auto-promoted to Stable) because the set carries unresolved TBDs (the deployment fork, since closed).

## Blockers

<!-- Empty if none. Format: [severity] description -->

(none)

## Blocking Constraints

<!-- Anti-patterns discovered through real failures. MANDATORY reading. -->
<!-- Agent MUST explicitly acknowledge each constraint before working. -->

- **Engine bug:** `executor.js update-state` corrupts STATE.md fields on nearly
  every invocation (top-level `**Status:**`, `## Progress`). Always re-open
  STATE.md after `update-state`/`finalize` and manually verify both.
- **Public OSM tile servers are prohibited in production** by the OSMF Tile
  Usage Policy. Never ship pointing at `tile.openstreetmap.org` — use MapTiler,
  Stadia, or self-hosted tiles. The previous implementation shipped this
  violation unnoticed, which is how it was found.
- **Local Postgres occupies host port 5432** — the Docker service is mapped to
  5433. `postgres:18+` images store data under major-version subdirectories of
  `/var/lib/postgresql`, not `/var/lib/postgresql/data`.
- **PostgreSQL ships no full-text dictionary for Georgian or Ukrainian.**
  Trigram matching carries name search; stemmed FTS will be incomplete.
  Escalation trigger to Typesense is recorded in `l2-tech-stack.md` §5.7.
- **Hiding a Filament action or Blade block is never an access control.**
  `[TZ]` §121 permissions are scoped by country/territory/category and must be
  enforced in Policies, server-side, on every read and write.
- **Catalog ordering is placement-tier first** — never "improve" it into
  relevance-first. A lower-tier object outranking a higher-tier one breaks the
  revenue model (`[TZ]` §25.2).

## Session Continuity

**Last Session Ended:** 2026-08-05 — specification re-baseline + stack pivot
**Handoff File:** none (state is complete in this file, PLAN.md, and INDEX.md)
**Bootstrap Mode:** false

**Reading order for a fresh session:**

1. This file — position, decisions, blockers, blocking constraints.
2. `CLAUDE.md` — stack, conventions, Figma-first rule, engineering discipline.
3. `.design/main/INDEX.md` — 23 specs, all RFC.
4. `.design/main/PLAN.md` — no plan yet; gates all closed.
5. `l2-tech-stack.md` §1 — why Laravel, and why the earlier Next.js answer was wrong.

**Do not carry forward** anything about Next.js, TypeScript, Drizzle, Better Auth,
react-admin, or Vercel — that stack is superseded and preserved only at tag `v0.1.34`.
