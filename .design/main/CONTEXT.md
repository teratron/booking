# Project Context

**Generated:** 2026-08-21

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
│   ├── TODO.md
│   └── booking.md
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


### Track D — Production Provisioning & Observability

- Production object storage and CDN in front of both the application and media, credential exposure checked against the committed tree.
- Production SMTP confirmed; Sentry/GlitchTip error tracking with personal-data scrubbing covering queue and scheduler failures.
- Horizon (five declared queues) and Pulse (Redis-backed ingest, zero added query cost) wired as first-class Docker Compose services alongside a real scheduler process.

### Track T — Validation & Launch Readiness

- Rehearsed restore: a real backup/restore cycle against a genuinely disposable fourth database, run twice in succession, with a recorded runbook.
- Import/export invariants: a real corrupt-and-reimport round trip, plus zero-automatic-merge and zero-unpermitted-column sweeps.
- Load test at 52,800 seeded objects — found and fixed a significant, previously invisible bug (`cache.serializable_classes` defaulting to `false` was silently corrupting every real Redis cache hit); surfaced two genuine budget breaches (search p95, catalog cache-miss) for a pre-launch decision.
- Coverage floor: both named services reached 100% individually; the suite-wide 78.3% floor remains open as a separate, tracked finding across roughly twenty pre-existing Phase 1–6 files — closed on its own literal scope with the project owner's confirmation.

Phase 7 closed the plan: all seven phases done, 135/135 tasks. A plan-wide retrospective ran on close — see `RETROSPECTIVE.md` for DORA metrics, observations, and recommendations, most notably promoting qualifying specifications to `Stable` and scoping the residual coverage gap as its own follow-up.

