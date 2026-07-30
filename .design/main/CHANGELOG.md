# Workspace Changelog (main)

Internal phase journal — summarizes what each completed phase delivered, extracted from Done task `Changes` fields in `tasks/phase-{N}.md`. Distinct from the root `CHANGELOG.md` (user-facing release notes).

## Phase 1 — 2026-07-30

**Platform Foundation** — project scaffold, complete entity model, and the app shell every route inherits. 14/14 tasks done across four tracks.

### Track A — Scaffold & Tooling

- Next.js 16.2.12 (App Router, strict TypeScript, Turbopack) scaffolded via `create-next-app`.
- Biome 2.5.6 as the sole lint/format toolchain, scoped to `src/**` and root app configs.
- Vitest selected as the test framework (confirmed against Next.js's own current docs — async Server Components are documented as untestable with Vitest, an E2E concern).
- Tailwind CSS v4 + shadcn/ui (`base-nova`/Base UI preset) installed via the real `shadcn` CLI.
- Fallow wired for dev-time codebase intelligence — a standing quality gate for later phases.

### Track B — Data Layer

- 14-table Drizzle schema for the complete entity graph: `user`/`session`/`account`/`verification` match Better Auth's Drizzle adapter shape exactly; shared `amenity` taxonomy (hotel + room); `room.hotel_id` `NOT NULL` with a cascading FK (the hierarchy invariant verbatim).
- Local dev PostgreSQL via `docker-compose.yml` (postgres:18-alpine, host port 5433 — 5432 was already bound by a native Postgres 18 service on this machine).
- `src/lib/db/client.ts` (`drizzle-orm/node-postgres`), migration applied, hierarchy/moderation constraints proven against the live database.

### Track C — Shell

- Header/Footer/LanguageSwitcher (Server Components) wired into the root layout; verified rendering on `/` and an arbitrary nested route.
- Full i18n: `next-intl` in non-routing single-locale mode (only `ru` ships; no `[locale]` routing until a second locale actually exists); every UI string externalized to `messages/ru.json`; a permanent regression guard (`no-hardcoded-copy.test.ts`) scans for hardcoded Cyrillic.
- 404 page, `/privacy-policy` route, and a shared feedback popup (the phase's one Client Component, built on shadcn's `Dialog`).
- Responsive nav: native `<details>/<summary>` hamburger on mobile (zero JS), plain list on desktop; footer switches from stacked to row layout at `md`. Verified with a real browser (chrome-devtools MCP) at 375px and 1280px, not just class presence.

### Track T — Validation

- Entity model checked edge-by-edge against `l1-platform-foundation.md` §5.2; two deliberate, documented deviations (Location embedded on `hotel` rather than a separate table; no admin-audit FK on moderation actions).
- Shell invariants checked against `l1-platform-shell.md` §3; found and fixed two real test-coverage gaps (404 and privacy-policy pages had no catalog-completeness test).
- Phase-close `fallow audit`: 0 circular dependencies, 0 boundary violations.

### Notable mid-phase decisions

- Dependency policy: keep the whole stack on latest, including major bumps (TypeScript 5→7, `@types/node` 20→26) — fix real breakage as it surfaces rather than reverting versions.
- Async Server Components calling `next-intl/server`'s `getTranslations` cannot be unit-tested with Vitest at all (confirmed empirically, matches Next.js's own documented guidance) — verified via live dev-server/browser checks instead; unit tests cover catalog completeness and source-level regression guards.
