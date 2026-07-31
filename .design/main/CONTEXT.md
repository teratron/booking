# Project Context

**Generated:** 2026-07-31

## Active Technologies

- Node.js

## Core Project Structure

```plaintext
.
├── .claude/
│   └── rules/
├── .design/
│   ├── .version
│   ├── INDEX.md
│   ├── RULES.md
│   ├── main/
│   └── workspace.json
├── .env.example
├── .fallowrc.jsonc
├── .gitignore
├── .magic/
├── .markdownlint.json
├── AGENTS.md
├── CHANGELOG.md
├── CLAUDE.md
├── README.md
├── biome.json
├── components.json
├── docker-compose.yml
├── docs/
│   └── README.md
├── drizzle/
│   ├── 0000_gorgeous_triton.sql
│   ├── 0001_wonderful_korvac.sql
│   └── meta/
├── drizzle.config.ts
├── messages/
│   └── ru.json
├── next.config.ts
├── package.json
├── pnpm-lock.yaml
├── pnpm-workspace.yaml
├── postcss.config.mjs
├── src/
│   ├── app/
│   ├── components/
│   ├── hooks/
│   ├── i18n/
│   ├── lib/
│   └── no-hardcoded-copy.test.ts
├── tsconfig.json
└── vitest.config.ts
```

## Recent Changes

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

