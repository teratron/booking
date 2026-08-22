# Project Instructions

International tourism portal-directory for Moldova, Ukraine, and Georgia. **Not a booking system** — it publishes objects and hands visitors directly to the owner's phone or messenger. Revenue is paid placement sold to object owners.

## Tech Stack

- **Language**: PHP 8.5+, `declare(strict_types=1)` in every file.
- **Framework**: Laravel 13+ — monolith. Blade + Livewire 4+ for the public site; no separate frontend application.
- **Admin & owner cabinet**: Filament 5+ — two panels from one toolkit. Both panel paths are runtime-configurable (`config/booking.php`, overridable via `ADMIN_PANEL_PATH` / `CABINET_PANEL_PATH`), never hardcoded — the staff panel deliberately does not sit at the conventional `/admin`, since a guessable staff address invites the credential-stuffing traffic its sign-in throttle then has to absorb. Default staff path is `portal-admin`; default owner-cabinet path is `cabinet`.
- **Database**: PostgreSQL 18+ + PostGIS. Extensions: `postgis`, `pg_trgm`, `unaccent`.
- **Cache / queue / session**: Redis 8+; queues via Laravel Horizon.
- **Object storage**: S3-compatible (MinIO locally, Cloudflare R2 / Backblaze B2 in production).
- **Frontend**: Blade + Livewire 4+ + Alpine.js + Tailwind CSS 4+, bundled by Vite.
- **Maps**: MapLibre GL JS with a paid or self-hosted tile provider.
- **Error tracking**: Sentry (`sentry/sentry-laravel`), production.
- **Package manager**: Composer (PHP), pnpm (asset pipeline via Vite, plus JS/TS lint and static analysis via Biome and Fallow).
- **Quality**: Pest (tests), PHPStan level 8+ via Larastan, Laravel Pint (formatting), Rector (upgrades).

Always install the latest stable release of every package; do not pin back a major version as a precaution.

## Required Packages

Each maps to a specification requirement — do not hand-build what these already cover.

| Package | Covers |
| --- | --- |
| `filament/filament` | Admin panel and owner cabinet |
| `spatie/laravel-permission` | Roles and permissions |
| `astrotomic/laravel-translatable` | Per-entity translations in **separate tables** |
| `spatie/laravel-medialibrary` | Media upload, conversions, thumbnails, ordering |
| `owen-it/laravel-auditing` | Action journal with old/new values |
| `spatie/laravel-backup` | Scheduled backups, retention, integrity checks |
| `spatie/laravel-sitemap` | Sitemap generation |
| `staudenmeir/laravel-adjacency-list` | Recursive territory hierarchy (CTE) |
| `laravel/sanctum` | API tokens |
| `laravel/horizon` | Queue monitoring |
| `laravel/pulse` | Production performance monitoring dashboard |
| `sentry/sentry-laravel` | Error and exception tracking |
| `laravel/scout` | Search abstraction (Postgres driver first, Typesense later) — **not yet installed**; the catalog runs on PostgreSQL full-text search directly until this is added |
| `filament/filament`'s native multi-factor auth | Two-factor authentication — built on `pragmarx/google2fa`, which Filament already depends on directly; the separate `pragmarx/google2fa-laravel` wrapper (facade, config, middleware) was removed as dead weight once the panel was wired to the native implementation instead |
| Filament import/export actions | XLSX / CSV import and export |

## Project Structure

```plaintext
app/
├── Models/                 # Eloquent models — relations, casts, and scopes only
│   ├── Concerns/           # Shared traits
│   └── Scopes/             # Global scopes (e.g. soft-delete, moderation visibility)
├── Http/
│   ├── Controllers/        # Thin controllers — public routes, versioned API, admin downloads
│   └── Middleware/
├── Providers/              # Service providers, including the Filament panel providers
├── Filament/
│   ├── Admin/              # Staff panel: resources, pages, widgets
│   └── Cabinet/            # Owner panel: resources scoped to the owner
├── Livewire/               # Public-site interactive components (catalog, map, filters)
├── Services/               # Business logic — ranking, bumps, banner targeting, statistics
├── Policies/               # Authorization, including geo/category-scoped rules
├── Jobs/                   # Queued work: expiry sweeps, notifications, rollups
├── Listeners/              # Event listeners
├── Exceptions/             # Domain exceptions for refused/invalid actions
├── Console/Commands/       # Scheduled entry points
└── Support/                # Cross-cutting helpers

resources/
├── views/                  # Blade templates (public site)
├── lang/                   # Interface translation catalogs (en, ru)
├── css/  js/               # Tailwind + Alpine, bundled by Vite

database/
├── migrations/
├── factories/
└── seeders/                # Registries: languages, countries, territory levels,
                            # object types, amenities, tiers, roles, permissions

tests/
├── Architecture/           # Pest arch() + content-scan convention checks
├── Feature/                # Grouped by surface: Admin, Api, Cabinet, Operations, Public
├── Unit/
└── Fixtures/               # Shared fixtures

docker/                     # Local infrastructure (Postgres init SQL, etc.)
docs/                       # Operational runbooks — see "Documentation" below
.github/workflows/          # CI pipeline
.design/                    # Specifications — read-only for implementation work
```

## Implementation Guidelines

- **Business logic lives in `app/Services/`**, not in controllers, Livewire components, Filament resources, or models. Models hold relations, casts, and scopes only.
- **Authorization is server-side, always.** Hiding a Filament action or a Blade block is a usability affordance and never an access control. Permissions may be scoped to a country, territory subtree, or object category — enforce that in Policies.
- **Every user-facing string is translatable.** No literal copy in Blade, Livewire, or Filament labels. Entity text lives in translation tables, not JSON columns.
- **Never hard-code the language or country count.** Both are runtime registries.
- **Catalog ordering is placement-tier first.** A lower-tier object must never outrank a higher-tier one except by an explicit administrator pin. Do not "improve" this into relevance-first ordering.
- **Soft delete by default** for objects, users, news, promotions, banners, articles. Filter in a global scope, not per query.
- **A moderation or visibility global scope applies to public-facing and catalog queries only** — never to a query resolving what an authenticated, already-authorized owner or staff member can reach about their own record. Strip it explicitly there, or the cabinet and admin panels silently can't see a user's own pending work.
- **Panel URL paths are configuration, never a literal string.** Read a panel's path from its config value in every redirect, link, robots rule, CSP directive, or sitemap entry — the staff panel in particular ships with a deliberately non-guessable default, so a hardcoded guess looks plausible while quietly missing the real, reachable path.
- **Scheduled work belongs in Jobs**, dispatched by the scheduler — never executed during a web request.
- Prefer Filament's own abstractions (resources, relation managers, actions, widgets) over custom pages. Reach for a custom page only when the resource model genuinely does not fit.

## Filament Conventions

- One panel provider per audience: `AdminPanelProvider` and `CabinetPanelProvider`.
- The cabinet panel scopes **every** resource query to the authenticated owner. Enforce it in the resource's base query and in the Policy — never in the UI alone.
- Register permissions as Filament resource policies, not as inline `visible()` closures.
- Moderation uses record versions: the published record stays untouched while a pending revision exists, so a rejected edit can never damage a live page.
- Bulk actions require a confirmation naming the affected record count.
- **Never mark a page's lifecycle hooks with PHP's `#[\Override]` attribute** — `beforeCreate`, `afterCreate`, `beforeSave`, `afterSave`, `beforeDelete`, `afterDelete`, and their `CreateRecord`/`EditRecord` equivalents are discovered by name at runtime, not inherited, so the class fatals the moment it loads. Genuine overrides of real parent methods (`mutateFormDataBeforeFill`, `handleRecordCreation`, and similar) keep `#[\Override]` as normal.

## Design Source — Figma First

Every page and component is built **against the Figma source**, not from a written description of it. Before writing markup for any screen:

- File: `N2cVVIS5wvjHIviP27peuX` — <https://www.figma.com/design/N2cVVIS5wvjHIviP27peuX/Booking>
- Local copy: `.drafts/Booking.fig`

Workflow: load the `figma-design-to-code` guidance, then pull the node with the Figma MCP tools (`get_design_context` for layout and tokens, `get_screenshot` to verify, `get_metadata` to locate nodes). Adapt the returned reference code to Blade + Tailwind and this project's existing components — never paste it verbatim.

Rules:

- **Design tokens come from Figma**, not from invented values. Colours, spacing, radii, and type scale go into the Tailwind theme once and are reused; no magic numbers in templates.
- **Extract shared components on second use**, not speculatively — the header, footer, object card, badge, and filter controls repeat across nearly every frame.
- **Figma governs visual language and page composition only.** Scope, domain rules, and behaviour come from `.design/` specifications. Where the two disagree, the specification wins and the divergence is noted.
- Frames exist in desktop and mobile pairs; build one responsive template per page, not two.
- Frames with the node prefix `1306:*` belong to an unrelated pasted document and are **out of scope**.

## Specification Layer

`.design/` holds the specifications this project implements. Treat them as the source of truth for behaviour, and as **read-only** during implementation work — they change through the `/magic.spec` workflow, not by editing files directly.

Never reference specification artefacts from product code: no task IDs, no phase names, no `.design/…` paths, no spec file names in comments, identifiers, or strings. If design rationale matters at a code site, restate it in plain language.

## Engineering Discipline

Quality gates run **continuously, not terminally**. After every meaningful change — not once at the end of a task, and never only before a commit. A gate that runs late reports a pile of failures nobody wants to untangle; a gate that runs constantly reports one.

### Toolchain

Declared as Composer scripts so the commands are identical locally and in CI:

| Command | What it runs |
| --- | --- |
| `composer fix` | `pint` — format the codebase |
| `composer lint` | `pint --test` — fail on any formatting drift |
| `composer analyse` | `phpstan analyse` — Larastan, level 8 |
| `composer test` | `pest`, excluding the `slow` group — unit, feature, architecture |
| `composer test:arch` | Architecture tests only (fast convention check) |
| `composer test:coverage` | Coverage with the configured minimum, excluding the `slow` group |
| `composer test:slow` | The realistic-volume tests excluded from `test`/`test:coverage` — run before a release, not on every gate pass |
| `composer bench` | Performance benchmarks (§ below) |
| `composer audit` | `composer audit` — known security advisories |
| `composer unused` | Detect declared-but-unimported dependencies |
| `composer rector:dry` | Preview Rector-recommended upgrades — manual, periodic, not part of the gate |
| `composer rector` | Apply them |
| `composer quality` | `lint → analyse → test → test:coverage → audit → unused`, in that order — the pre-commit gate. (`fix`, `bench`, and `rector` are excluded: the first mutates the tree, the other two are manual checks.) |

CI runs `composer quality` on every push. Set it up during scaffolding, not later.

The JS/TS side runs its own gate, in parallel: `pnpm run fix` (Biome, format) / `pnpm run lint` (Biome, check), and `pnpm run analyse` (Fallow, static analysis) / `pnpm run audit` (Fallow, dependency audit) / `pnpm run review` (Fallow, review). `pnpm run quality` runs lint + analyse — run it alongside `composer quality`, not instead of it.

### Architecture Tests

Conventions are enforced by Pest tests in the Architecture suite — the `arch()` DSL for dependency-shape rules, and a plain content-scanning test where `arch()` has no primitive (the containment check below). Either way, a rule a machine cannot check is a rule that erodes. At minimum:

- `declare(strict_types=1)` in every file.
- No `dd`, `dump`, `var_dump`, `ray`, or `print_r` anywhere outside tests.
- Models carry no dependency on `App\Services`, `App\Http`, `App\Jobs`, `App\Filament`, or `App\Livewire` — `App\Models` stays thin.
- `App\Filament` and `App\Livewire` never use the `DB` facade directly — they go through `App\Services`.
- Controllers, jobs, and services are `final` unless deliberately extended.
- No `App\Policies` method accepts a caller-supplied boolean parameter — a policy's decision derives only from the acting user, the target, and the stored grant, never a flag the call site can pass to silently bypass the scope check ("Authorization is server-side, always" above, made mechanical).
- **No `.design` path, task ID, phase name, or specification filename appears anywhere in `app/`, `resources/`, `database/`, or `tests/`** — the containment rule below, made mechanical.

### Testing

- Pest for unit and feature tests; browser tests for the flows a broken selector would silently kill — contact-channel clicks, the availability toggle, moderation approve and reject.
- Every bug fix starts with a failing test that reproduces it.
- Seeders produce **realistic volume** for the tests that care about volume. The catalog ranking query behaves differently against 12 fixtures and against 50 000 objects; only the second tells you anything. These are the `slow`-group tests — run them explicitly (`composer test:slow`), since the default gate excludes them for speed.
- `php artisan migrate:fresh --seed` must apply cleanly from empty, every time.

### Benchmarking & Performance Budgets

`[TZ]` §18 and §94 make performance a requirement, not an aspiration. Budgets are measured, not assumed:

| Surface | Budget |
| --- | --- |
| Catalog / territory page, cache hit | < 100 ms TTFB |
| Catalog / territory page, cache miss | < 400 ms |
| Object page, cache miss | < 300 ms |
| Search, p95 | < 300 ms — the escalation trigger to Typesense |
| Any single request | ≤ 30 queries |

- **N+1 detection is enabled in development and fails the test run.** Eloquent plus Filament plus nested relations is the exact shape that produces them, and they do not announce themselves.
- Benchmark the catalog ranking query and territory subtree expansion against seeded volume whenever either changes — these are the portal's hottest paths.
- Laravel Pulse in production for ongoing visibility.
- Run a load test against catalog and territory pages before launch, not after.

### Documentation

Written in **English**, for a developer who did not build this and may be maintaining it for the client after handover.

- **Docblocks on every public service method**: what it guarantees, what it throws, what it assumes — not a line-by-line narration of the body.
- **Comment the *why*, never the *what*.** Non-obvious constraints, business rules with a surprising shape, and deliberate deviations get a sentence. Obvious code gets nothing — noise costs more than it explains.
- **`README.md`**: setup from zero to a running local instance, architecture map, common tasks.
- **`docs/`**: operational runbooks for a developer taking this to production — the applied database schema, backup and restore procedure (including a rehearsed restore against a real artefact), storage/CDN provisioning, mail relay and error tracking, and queue/scheduler observability. `[TZ]` §97 and §131 require the restore specifically to be documented and rehearsed, not just working backups. Add a new runbook whenever a new operational concern goes live, and keep `docs/README.md`'s index in sync.
- Filament labels, table columns, and form fields go through translation keys, never literal strings.

### Cleanliness

- **Delete, never comment out.** Git holds the history; commented-out blocks hold confusion.
- No dead code, no unused dependencies. The previous implementation of this project accumulated eleven unused packages before anyone noticed — `composer unused` and Rector's dead-code rules exist to prevent the repeat.
- A `TODO` carries plain-language context and an owner, never a task ID or a specification reference.
- Migrations are never edited after being applied to a shared environment — add a new one.
- **Write one explicit, literal `Schema::table('name', …)` or `Schema::create('name', …)` call per table**, even in a migration touching several — Larastan's schema-aware inference only recognizes the literal call; a table name resolved through a variable leaves every new column on it silently untyped everywhere it's used, with no warning from `composer analyse`.
- Prefer deleting an abstraction to generalizing it further.

## Release & Deployment

> **Interim policy, in effect since 2026-08-22: Git Flow is paused.** One developer,
> working across several machines, still pre-production — the branch-hop-and-wait
> ceremony below (feature → develop → master, a full quality-gate run at each hop)
> cost more in waiting than it returned in safety at this size. All work happens
> directly on `master`. `develop` and the `feature/*`/`release/*`/`hotfix/*` branches
> below do not currently exist; `master` carries no branch protection, so pushing to
> it does not wait on review or a status check. `quality.yml` still runs on every
> push to `master` and still reports — it just no longer blocks anything.
>
> Everything below is the target this project returns to — unedited, not deleted —
> before the project is handed to the client, or as soon as a second developer joins,
> whichever comes first. The exact working state this policy paused (full branch
> protection on both `master` and `develop`, the merge-back detector, `.github/CODEOWNERS`
> enforcing the sensitive-zone review boundary) is preserved at git tag
> `gitflow-archive-v0.2.76`. `docs/release/branching.md` and `docs/release/pipeline.md`
> carry the same note.

Git Flow over a single self-hosted production line — no blue/green pair, no release train, no manual deploys. This is the path an accepted change takes to reach the portal, and reversing that path when a release turns out wrong.

| Branch | Role |
| --- | --- |
| `feature/*` | Work in progress, branched from `develop`. Merged by pull request once the quality gate passes — a person's review grant only where the change is not "ordinary" (see below) — deleted on merge. |
| `develop` | Integration line — every accepted change lands here first. Gated, never deployed directly. |
| `release/x.y.z` | A frozen integration state, for stabilization only (a translation fix, a config correction) — not for new work. Merges to `master` **and** back to `develop`. |
| `master` | Production. Protected: no direct pushes, linear history, tagged on every merge. |
| `hotfix/x.y.z` | Urgent production fix, branched from `master`. Merges to `master` **and** back to `develop` — the merge-back is mandatory, not a courtesy. An unmerged hotfix is a bug scheduled to reappear. |

- **`master` is the only production line.** A release ships only through the pipeline — triggered by pushing a version tag, built once, deployed, then health-checked before it counts as live. Editing the production host directly, applying a migration by hand, or deploying from a working copy is an incident to record and reverse, never a faster route.
- **One release at a time.** Production deploys are serialized; a second release never starts while one is still in flight. A queued release waits rather than overlapping with one that's still migrating.
- **Every release leaves a record.** What was deployed, the commit it was built from, who or what authorized it, and the outcome. A rollback is a release too, and is recorded the same way — an action whose actor can't be named later didn't happen accountably.
- **Reversal is a redeploy, not a rebuild — and it has a floor.** Every release must be returnable to the previous released state without rebuilding it and without whoever shipped it being involved. A migration a rollback cannot undo must be declared irreversible **before** the release ships; that routes it to the administrator-gated backup restore instead of an ordinary rollback, and the decision is never made mid-incident.
- **A rollback that doesn't restore health escalates — it never retries.** If the site is still unhealthy after redeploying the last known-good release, the release wasn't the fault. Stop, hold the site in maintenance mode, notify, and hand off to a person, instead of redeploying in a loop chasing a fix that isn't there.
- **An ordinary bug fix may travel unattended from a work line through acceptance into `master`, without a person granting review** — the owner's standing authorization for routine operation, not a one-time exception. "Ordinary" is decided mechanically, not by the agent's own judgement: the change must touch none of a declared sensitive-zone path set (authentication, authorization/policies, financial records and placement/commerce, secrets and credential wiring in `.env*`/CI workflows) and carry no undeclared irreversible migration. Either condition routes the change back to requiring a person's review grant, same as before this authorization existed.
- **Reaching `master` is never deploying it, and starting a deploy is always a person's explicit act** — pushing the release tag (or whatever later replaces that mechanism), unconditionally, regardless of whether the change that reached `master` used the automation above or a human review. A person must also grant review for any non-ordinary change, declare a release irreversible, and initiate a backup restore — none of that shifts with the automation above; it only removes the review-grant step ahead of an *ordinary* change's own acceptance.
- Full policy, the sensitive-zone list, and the reasoning behind the boundary: `.design/main/specifications/l1-release-operations.md` §5.5.2.
- **Never let one actor both accept a release and declare it irreversible.** That combination can produce a state nothing under its own control can undo — the whole point of separating the two decisions.
- **Operator documentation covers three audiences, in both launch languages.** The client operator gets plain-language, no-technical-background procedures in English and Russian. An AI agent gets the same procedures rendered as machine-addressed `*.prompt.md` files — explicit preconditions, explicit expected outcomes, and an explicit condition for when it must stop and hand back to a person. Developers get the existing English technical runbooks. All three renderings describe the same steps; a change to one is incomplete until the others match — a stale rendering is worse than none, because someone trusts it.
- **Secrets never travel in code or images.** No credential, token, or key is committed, baked into a build artefact, or written into a release record or pipeline log. Every secret reaches the running system from its own host, supplied at the moment it's needed.

## Completion Protocol (Mandatory Checklist)

Before declaring any task complete, verify every item:

- [ ] **Quality gates**: `composer quality` passes end to end — Pint, PHPStan level 8, Pest (including architecture tests), coverage minimum, `composer audit`, `composer unused`.
- [ ] **Migrations**: `php artisan migrate:fresh --seed` applies cleanly from empty.
- [ ] **Tests**: new behaviour has tests; every bug fix has a test that failed before it.
- [ ] **Performance**: no N+1 introduced; touched hot paths still meet their budget.
- [ ] **Documentation**: public service methods have docblocks; non-obvious decisions have a *why* comment; README and `docs/` updated if setup or operations changed.
- [ ] **Language policy**: all code, identifiers, comments, documentation, and commit messages in English; all chat interaction in Russian.
- [ ] **Architecture**: business logic in `app/Services/`; models thin; authorization enforced in Policies; no logic in Filament resources or Blade.
- [ ] **Localization**: no hard-coded user-facing strings; no hard-coded language or country counts.
- [ ] **Ordering**: placement-tier precedence preserved wherever objects are listed.
- [ ] **Design fidelity**: markup built against the Figma node, tokens from the Tailwind theme, no magic values.
- [ ] **Specification containment**: no `.design/` references, task IDs, or spec file names in product code.
- [ ] **Cleanliness**: nothing commented out, no dead code, no stray `dd()`/`dump()`, no unused dependency added.
- [ ] **Formatting**: no horizontal rules (`---`) in document bodies except in a footer.
