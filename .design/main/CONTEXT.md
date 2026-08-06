# Project Context

**Generated:** 2026-08-06

## Active Technologies

- Node.js

## Core Project Structure

```plaintext
.
├── .claude/
│   ├── rules/
│   └── scheduled_tasks.lock
├── .design/
│   ├── .version
│   ├── INDEX.md
│   ├── RULES.md
│   ├── main/
│   └── workspace.json
├── .drafts/
│   ├── TODO.md
│   └── booking.md
├── .editorconfig
├── .env.example
├── .fallowrc.jsonc
├── .gitattributes
├── .githooks/
│   └── pre-commit
├── .github/
│   └── workflows/
├── .gitignore
├── .magic/
├── .markdownlint.json
├── .npmrc
├── AGENTS.md
├── CHANGELOG.md
├── CLAUDE.md
├── README.md
├── app/
│   ├── Console/
│   ├── Exceptions/
│   ├── Filament/
│   ├── Http/
│   ├── Jobs/
│   ├── Livewire/
│   ├── Models/
│   ├── Policies/
│   ├── Providers/
│   ├── Services/
│   └── Support/
├── artisan
├── biome.json
├── bootstrap/
│   ├── app.php
│   ├── cache/
│   └── providers.php
├── composer.json
├── composer.lock
├── config/
│   ├── app.php
│   ├── audit.php
│   ├── auth.php
│   ├── cache.php
│   ├── database.php
│   ├── filesystems.php
│   ├── logging.php
│   ├── mail.php
│   ├── permission.php
│   ├── queue.php
│   ├── services.php
│   ├── session.php
│   └── translatable.php
├── database/
│   ├── .gitignore
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docker/
│   ├── app/
│   ├── nginx/
│   └── postgres/
├── docker-compose.yml
├── docs/
│   ├── README.md
│   └── database-schema.md
├── package.json
├── phpstan.neon
├── phpunit.xml
├── pint.json
├── pnpm-lock.yaml
├── public/
│   ├── .htaccess
│   ├── favicon.ico
│   ├── index.php
│   └── robots.txt
├── rector.php
├── resources/
│   ├── css/
│   ├── js/
│   ├── lang/
│   └── views/
├── routes/
│   ├── console.php
│   └── web.php
├── storage/
│   ├── app/
│   ├── framework/
│   └── logs/
├── tests/
│   ├── Architecture/
│   ├── Feature/
│   ├── Pest.php
│   ├── TestCase.php
│   └── Unit/
└── vite.config.js
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

