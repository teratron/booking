# Project Context

**Generated:** 2026-08-31

## Active Technologies

- Node.js

## Core Project Structure

```plaintext
.
├── .claude/
│   ├── rules/
│   ├── scheduled_tasks.lock
│   └── skills/
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
│   ├── load-testing-2026-08-26.md
│   ├── production-clearance.md
│   ├── qa-deep-findings.md
│   ├── qa-deep-plan.md
│   ├── qa-fix-specs-2026-08-26.md
│   ├── qa-fix-specs-2026-08-31.md
│   ├── qa-simulation-2026-08-26.md
│   ├── qa-simulation-2026-08-31.md
│   ├── qa-sweep-report.md
│   ├── qa-test-plan-2026-08-26.md
│   ├── qa-tz-conformance-2026-08-26.md
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
│   ├── php-fpm-capacity.md
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
│   ├── css/
│   ├── favicon.ico
│   ├── fonts/
│   ├── images/
│   ├── index.php
│   ├── js/
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

- Rewrote genuine `§N.N` leaks in the catalog Livewire component, the app provider, and two public views.

### Track F — Tests

- Rewrote genuine `§N.N` leaks in 4 test files.

### Track T — Mechanical Enforcement & Validation

- Added a TZ-aware PCRE SKIP/FAIL pattern to `ContainmentTest.php` that flags a bare `.design/`-spec `§N.N` reference while correctly leaving every `[TZ]` client-specification citation untouched — verified against all 56 real occurrences in the tree (23 `[TZ]`, 33 leak) before any file was rewritten, then run once before the cleanup (failed, listing exactly the 31 real-leak files) and once after (passed clean).

### Track G — Full-Suite Regression Gate

- 974 tests passed, 3 skipped, 0 failed across the full non-slow suite with all six tracks' fixes applied together, plus a clean `pint`/`composer analyse`/`composer audit`/`composer unused` pass.

Two of Track A/B/D's governing specifications — `l1-object-profile.md` and `l1-public-api.md` — carried a live, unrelated `TBD` that briefly blocked their tracks entirely (this SDD engine's spec-status gate is document-level, not section-level); `l1-platform-shell.md` carried a third. All three were closed and promoted `RFC → Stable` the same day: `l1-platform-shell`'s technical modeling question had a single defensible answer already matching shipped code, while `l1-object-profile`'s (review authorship) and `l1-public-api`'s (API consumer/rate-limit/licensing policy) were genuine product decisions put to the project owner directly rather than inferred.

