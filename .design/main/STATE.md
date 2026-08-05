# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-08-05 07:30
**Phase:** 0 - Concept re-baseline + stack pivot
**Status:** Active

## Current Position

- **Task:** Stack replaced. Laravel 13 + Filament 5 + PostgreSQL/PostGIS + Redis, self-hosted monolith. TypeScript implementation removed (275 files) and preserved at tag `v0.1.34`. Project version 0.1.34 → 0.2.0.
- **Spec:** 23 specs, all `RFC`. 19 L1 are technology-neutral and unchanged by the pivot; the 3 L2 documents were rewritten. TZ coverage 134/134, registry parity clean.
- **Next Action:** Review the RFC set, then `/magic.task main`. Backend work is gated by `[TZ]` §98 (client approval of the DB structure) — now the only remaining blocker.

## Progress

```
Specification:  [23/23] re-baselined + re-targeted, all RFC, review pending
Plan:           NOT GENERATED — awaiting spec review
Implementation: RESET — new stack, no code yet (previous work at tag v0.1.34)
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-08-05 **Decision:** **Stack replaced: Laravel 13 + Filament 5 + PostgreSQL/PostGIS + Redis**, self-hosted monolith. Rationale in `l2-tech-stack.md` §1. The v1.x argument for Next.js was wrong on two counts — it conflated SEO indexability with client interactivity (Blade renders equally crawlable HTML), and it anchored on an existing codebase whose schema needed full replacement anyway. Decisive factor: §99–134 + §29–43 (back office + owner cabinet) are more than a third of the TZ, and Filament delivers both from one toolkit; ten packages cover eleven TZ sections. Go was evaluated and rejected — no Filament-class admin toolkit, and the performance premise does not hold at ~30–60k objects behind Redis.
- 2026-08-05 **Note:** Deployment fork CLOSED — self-hosted, driven by `[TZ]` §97/§131 (off-server backups, administrator-triggered restore). This unblocked the last of the three storage/queue/mail selections and the §98 backup-scheme deliverable.
- 2026-08-05 **Finding:** Second line-by-line TZ pass found **6 gaps** the first pass missed, all closed. New specs: `l1-public-api.md` (§19 — REST API/tokens/docs had zero coverage), `l1-home-page.md` (§4/§5 — 16-block composition unowned), `l2-data-model.md` (§21/§98). Amendments: favorites (§8), traffic-source analytics (§23), candidate-module catalogue (§23/§64). One was worse than a gap — `l1-advertising.md` §2 flatly excluded a self-service advertiser cabinet that TZ §23 actually *recommends*; corrected to a deferred candidate.
- 2026-08-05 **Decision:** Booking is **preserved, not deprecated**. Per explicit product direction and `[TZ]` §63–64, the reservation work (schema, checkout flow, Fondy adapter — preserved at tag `v0.1.34`) is retained behind an administrator-toggleable module registry (`l1-feature-modules.md`), disabled by default. Booking and payment are separate module rows, so "dated request + owner confirmation, no payment provider" is a supported intermediate state.
- 2026-08-05 **Decision:** Launch locales narrowed to **English + Russian only**; Romanian, Ukrainian, Georgian deferred until after project completion and activated from the back office (`l1-localization.md` §5.6). Content decision, not a capability one — translation tables, per-language slugs, hreflang, and fallback still ship in the first migration. Consequence: no launch country's own primary language is active at release, so country records reference inactive languages and must resolve via fallback rather than fail validation.
- 2026-08-05 **Decision:** Specs re-baselined against the client TZ. Product changed from hotel booking marketplace → 3-country multi-language tourism information portal. 3 renames (hotel-discovery→object-catalog, hotel-profile→object-profile, property-onboarding→object-onboarding), 10 new specs, 7 amended. All set to `RFC` (not auto-promoted to Stable) because the set carries unresolved TBDs — chiefly the deployment fork in `l2-tech-stack.md` §5.9.
- 2026-08-05 **Finding:** Stack audit by import analysis, not manifest reading: **11 packages are removable today with zero code change** (10 unused `@radix-ui/*` from the incomplete Base UI migration, plus `vaul`); `shadcn` CLI is misplaced in `dependencies`. Conversely **12 TZ-required capabilities have no dependency at all** (Redis, job queue, mail, image derivatives, map clustering, XLSX, 2FA/CAPTCHA, scoped RBAC, audit, soft delete, the `en` locale catalog). The stack is mis-provisioned, not excessive.
- 2026-08-05 **Decision:** Phase 6 (and the full 6-phase plan) closed. T-6T01's live browser check surfaced and fixed two real defects no automated test caught: a Base UI `nativeButton` console error on the "Оплатить" button-as-Link, and a genuinely deterministic (not flaky) bug in `reservation-query.test.ts` — every test in that file shares one fixed `guestId` but only cleaned up in one file-level `afterAll`, so an earlier test's own rows leaked into a later exact-count assertion. Full suite: 52 files / 158 tests, 0 failures.
- 2026-08-04 **Note:** Docker/Postgres access RESTORED — `docker` CLI reappeared on PATH, daemon was stopped, started via Docker Desktop + `docker compose up -d`; the named `postgres-data` volume preserved the schema through container recreation.
- 2026-08-04 **Decision:** T-6C01 (payment) built via an orchestrated multi-agent workflow (Ultracode posture) — 4 agents built provider/persistence/UI/tests in dependency order, 3 agents adversarially reviewed (correctness, auth, conventions) and found 8 real issues, all confirmed and fixed. See `archives/tasks/phase-6.md` T-6C01 Changes.

## Blockers

<!-- Empty if none. Format: [severity] description -->

- **[high] `[TZ]` §98 client schema approval not started** (`l2-data-model.md` §5.7) — backend development cannot begin without it. Now unblocked and the only remaining gate: 5 of 9 deliverables complete, 3 need column-level detail.

## Blocking Constraints

<!-- Anti-patterns discovered through real failures. MANDATORY reading. -->
<!-- Agent MUST explicitly acknowledge each constraint before working. -->

- **Engine bug:** `executor.js update-state` corrupts STATE.md fields on nearly
  every invocation (top-level `**Status:**`, `## Progress`). Always re-open
  STATE.md after `update-state`/`finalize` and manually verify both.
- **Public OSM tile servers are prohibited in production** by the OSMF Tile
  Usage Policy. Never ship pointing at `tile.openstreetmap.org` — use MapTiler,
  Stadia, or self-hosted tiles. The previous implementation shipped this
  violation unnoticed.
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

**Last Session Ended:** 2026-07-30 19:54
**Handoff File:** none
**Bootstrap Mode:** false
