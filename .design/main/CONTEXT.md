# Project Context

**Generated:** 2026-08-23

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
├── .dockerignore
├── .drafts/
│   ├── .prompt.md
│   ├── TODO.md
│   ├── booking.md
│   ├── production-clearance.md
│   ├── qa-deep-findings.md
│   ├── qa-deep-plan.md
│   ├── qa-sweep-report.md
│   ├── qa-tz-conformance.md
│   └── review-submission-design-decision.md
├── .editorconfig
├── .env.example
├── .env.production.example
├── .fallowrc.jsonc
├── .gitattributes
├── .githooks/
│   └── pre-commit
├── .github/
│   ├── CODEOWNERS
│   └── workflows/
├── .gitignore
├── .magic/
├── .markdownlint.json
├── .npmrc
├── .secrets/
│   └── .gitignore
├── CHANGELOG.md
├── CLAUDE.md
├── README.md
├── app/
│   ├── Console/
│   ├── Exceptions/
│   ├── Filament/
│   ├── Http/
│   ├── Jobs/
│   ├── Listeners/
│   ├── Livewire/
│   ├── Models/
│   ├── Policies/
│   ├── Providers/
│   ├── Services/
│   ├── Support/
│   └── View/
├── artisan
├── biome.json
├── bootstrap/
│   ├── app.php
│   ├── cache/
│   └── providers.php
├── composer-unused.php
├── composer.json
├── composer.lock
├── config/
│   ├── app.php
│   ├── audit.php
│   ├── auth.php
│   ├── backup.php
│   ├── booking.php
│   ├── cache.php
│   ├── database.php
│   ├── filesystems.php
│   ├── horizon.php
│   ├── logging.php
│   ├── mail.php
│   ├── notifications.php
│   ├── permission.php
│   ├── pulse.php
│   ├── queue.php
│   ├── sentry.php
│   ├── services.php
│   ├── session.php
│   ├── sitemap.php
│   └── translatable.php
├── database/
│   ├── .gitignore
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docker/
│   ├── app/
│   ├── deploy/
│   ├── nginx/
│   └── postgres/
├── docker-compose.production.yml
├── docker-compose.yml
├── docs/
│   ├── README.md
│   ├── backups.md
│   ├── database-schema.md
│   ├── mail-and-error-tracking.md
│   ├── operations/
│   ├── production-provisioning.md
│   ├── queues-and-observability.md
│   ├── release/
│   └── restore-rehearsal.md
├── package.json
├── phpstan.neon
├── phpunit.xml
├── pint.json
├── pnpm-lock.yaml
├── pnpm-workspace.yaml
├── public/
│   ├── .htaccess
│   ├── favicon.ico
│   ├── images/
│   ├── index.php
│   └── robots.txt
├── rector.php
├── resources/
│   ├── css/
│   ├── js/
│   ├── lang/
│   └── views/
├── routes/
│   ├── api_v1.php
│   ├── console.php
│   └── web.php
├── storage/
│   ├── app/
│   ├── framework/
│   ├── logs/
│   └── media-library/
├── tests/
│   ├── Architecture/
│   ├── Feature/
│   ├── Fixtures/
│   ├── Pest.php
│   ├── TestCase.php
│   └── Unit/
└── vite.config.js
```

## Recent Changes

- `ResolvedMetadata` now carries per-language alternate URLs, computed through the identical `LocaleSwitchResolver::targetUrl()` call the language switcher itself uses, so hreflang tags and the switcher can never independently drift. The public layout emits one `<link rel="alternate">` per active language plus one `x-default`.

### Track E — Cabinet Settings Crash

- `cabinet/settings` 500d for every owner — the one cabinet route with no tenant segment, inside a panel whose full layout (sidebar, topbar, tenant menu) builds tenant-scoped URLs unconditionally with no null-tenant guard anywhere in that shared Filament chrome. The first, narrower patch fixed the one reported crash and immediately surfaced two more unguarded call sites in the same layout; switched to Filament's own `isSimple: true` default for tenant-independent pages instead of patching each vendor call site in turn.

### Track F — Test-Suite Correction

- `PublicRootEntryTest` asserted a fallback-to-primary-language branch its own bare `$this->get('/')` never actually reached — the HTTP test client silently attaches a default `Accept-Language` header that already matches. Fixed the assumption, not the resolver: `PublicEntryLocaleResolver` was already correct.

### Track G — Full-Suite Regression Gate

- 974 tests passed, 3 skipped, 0 failed across the full non-slow suite with all six tracks' fixes applied together, plus a clean `pint`/`composer analyse`/`composer audit`/`composer unused` pass.

Two of Track A/B/D's governing specifications — `l1-object-profile.md` and `l1-public-api.md` — carried a live, unrelated `TBD` that briefly blocked their tracks entirely (this SDD engine's spec-status gate is document-level, not section-level); `l1-platform-shell.md` carried a third. All three were closed and promoted `RFC → Stable` the same day: `l1-platform-shell`'s technical modeling question had a single defensible answer already matching shipped code, while `l1-object-profile`'s (review authorship) and `l1-public-api`'s (API consumer/rate-limit/licensing policy) were genuine product decisions put to the project owner directly rather than inferred.

