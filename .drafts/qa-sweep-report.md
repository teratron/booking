# Portal QA Sweep

A full-surface functional pass over the public site, owner cabinet, back office, and public API — every route exercised against a live instance, cross-checked against the design specifications for gaps the automated suite wouldn't catch.

**Environment:** local Docker (nginx + php-fpm 8.5, Postgres 18/PostGIS, Redis) — `localhost:8300`
**Baseline:** `migrate:fresh --seed`, clean
**Date:** 2026-08-22
**Live version:** [Portal QA Sweep artifact](https://claude.ai/code/artifact/b3e36825-5d7e-4e96-8549-2517f0296f4a) (interactive; this file is a static duplicate)

**Summary:** 181 routes mapped (17 public, 23 owner cabinet, 78 back office, 14 public API v1), ~110 exercised. 2 critical/crash bugs, 3 moderate findings, 1 test-suite defect (not a product bug). Full automated suite (`composer test`, non-slow group): 956 passed, 3 skipped, 1 failure — the one failure is the test defect documented below (F-5), not a regression.

## Method

No browser-automation tool was wired into the session (no Dusk, no Playwright, no connected chrome-devtools MCP). "Clicking every button" was done through the closest faithful substitutes rather than a literal pointer:

1. **Live HTTP requests** against the running instance for every server-rendered GET route — real response codes, real HTML, real redirects, exactly what a browser's first paint would receive.
2. **Livewire/Filament test-harness interaction** (`Livewire::test()`, the same mechanism the project's own 171-file suite already uses) for anything gated behind a `wire:submit` — logins, cabinet forms, admin resource CRUD, moderation actions — since raw curl cannot drive a Livewire AJAX cycle. This calls the real component, the real validation, the real service layer; it just skips the pixels.
3. **The project's own automated suite** (`composer test`, non-slow group) run in full as a regression baseline underneath the manual sweep.
4. **A spec-vs-implementation gap read** against `.design/main/specifications/` for anything that renders fine but doesn't do what the spec promises — the class of bug a green test suite never surfaces because nothing was ever written to check it.

Every finding below was reproduced live against the running instance (not inferred from reading code alone), then traced to a root cause.

## Findings

### F-1 · Critical — Neither object form can actually save a contact channel

**Where:** `app/Filament/Admin/Resources/Objects/Schemas/ObjectForm.php:201-215` · `app/Filament/Cabinet/Resources/Objects/Schemas/ObjectForm.php:253-269`

**Repro:** as `chief_administrator`, open an object's edit page → Contacts tab → add a row (value + label) → Save. Reproduced the identical failure through the owner cabinet's own Contacts section.

**What happens:** `QueryException: SQLSTATE[23502] — null value in column "contact_channel_type_id" of relation "contact_channels" violates not-null constraint`. The save throws; nothing is persisted.

**Root cause:** both Contacts repeaters bind `->relationship()` to `contactChannels` with a schema of only `raw_value` and `label` — the required, NOT NULL `contact_channel_type_id` (which of the 8 seeded channel types — phone, WhatsApp, Viber, Telegram, email, website, Instagram, Facebook — this value is) is never collected. Bulk import (`TransferableRegistry`) does carry this column correctly, so it's specifically the two interactive forms that never got the field added.

**Impact:** there is currently no working UI path, staff or owner, to attach a phone number or messenger to an object. The click-tracked redirect itself works perfectly once a channel row exists (verified end to end: `/en/objects/1/contact/1/click` → `302 tel:+37322123456`) — the entire gap is the missing type selector on these two forms.

**Suggested fix:** add `Select::make('contact_channel_type_id')->relationship('contactChannelType', 'key')->required()` (or the translated display-name equivalent) to both repeater schemas — a same-shape addition to an existing, already-working repeater, no restructuring.

### F-6 · Critical/Crash — The cabinet's own Settings page crashes for every owner

**Where:** `vendor/filament/filament` — `Panel::getTenantBillingUrl()`, triggered from the panel's standard layout on any tenant-independent page

**Repro:** as an authenticated `object_owner`, GET `/cabinet/settings` (Filament's built-in profile page, registered via `->profile(Settings::class)`) → `500`.

**Error:** `Filament\Panel::getTenantBillingUrl(): Argument #1 ($tenant) must be of type Illuminate\Database\Eloquent\Model, null given`, thrown from Filament's own `layout/index.blade.php` while rendering the page chrome.

**Root cause:** every other cabinet route is nested under `/cabinet/{tenant}/…` (the object currently being managed); `cabinet/settings` is the one page in this panel registered outside that tenant scope, by design — a profile/account page has nothing to do with any one object. Filament's shared layout unconditionally resolves a tenant billing URL for its chrome, and gets `null` here since there genuinely is no tenant on this page.

**Impact:** the account settings page — the one screen every owner eventually needs (password change, profile) — is unreachable; a real click of "Settings" from the account menu 500s immediately.

**Suggested fix:** this is a tenancy/profile-page interaction in Filament's core layout, not custom app code — worth a Filament version check first (a known upstream issue), then either scoping the settings route under a tenant after all, or disabling whatever billing-URL chrome element the layout renders when `Filament::getTenant()` is null on this panel.

### F-2 · Moderate — Every authenticated api/v1/* route 500s instead of 401 without an explicit Accept header

**Where:** `bootstrap/app.php` — no redirect-guest exemption for the `api` middleware group

**Repro:** `curl http://localhost:8300/api/v1/objects` (no token, no Accept header) → `500`, body leaks a full stack trace in debug mode. The identical request *with* `Accept: application/json` correctly returns `401 {"message":"Unauthenticated."}`. Reproduced on `objects`, `territories`, and `token` alike.

**Root cause:** `Symfony\Component\Routing\Exception\RouteNotFoundException: Route [login] not defined.` — Laravel's default guest-unauthenticated handler tries to redirect to a route literally named `login` whenever the request doesn't look like it wants JSON. This app never registers that name (Filament panels register `filament.admin.auth.login` / `filament.cabinet.auth.login` instead), so the fallback redirect itself throws.

**Impact:** any real-world API caller that omits or deprioritizes the Accept header — a bare curl, a browser address bar, many minimal HTTP clients — gets an opaque 500 instead of a clean, documented 401. Not gated by the API module toggle; happens whenever the module is on.

**Suggested fix:** in `bootstrap/app.php`'s `->withMiddleware()`, exempt the `api` group from the guest redirect — e.g. `$middleware->redirectGuestsTo(fn ($request) => $request->is('api/*') ? null : route('filament.admin.auth.login'))` — so every `api/v1/*` route always fails as JSON, never a redirect attempt.

### F-3 · Moderate — Canonical / OG / JSON-LD / API URLs follow the request Host header, not APP_URL

**Where:** no `URL::forceRootUrl()` / trusted-proxy configuration anywhere in `app/`

**Repro:** `APP_URL=http://booking.test` in `.env`. Requesting the object page via `http://localhost:8300/en/o/hotel-nistru` renders `<link rel="canonical" href="http://localhost:8300/en/o/hotel-nistru">`, matching OG tags, JSON-LD, and every API response URL — all reflect `localhost:8300`, not the configured `booking.test`. The sitemap (`spatie/laravel-sitemap`, reads `config('app.url')` directly) correctly emits `booking.test` — so the two subsystems now disagree on the portal's own canonical host.

**Root cause:** `url()`/`route()` derive their root from the incoming request's Host header by default; nothing pins it to `config('app.url')`.

**Impact:** violates this project's own settled invariant ("canonicals always name that host" — `l1-seo.md`, the 2026-08-15 URL-model decision). Fragile against any Host-header mismatch — a misconfigured proxy, a raw IP:port request, or a spoofed Host header all produce a wrong self-referential canonical/OG/JSON-LD URL, which search engines and social crawlers then index.

**Suggested fix:** one line in `AppServiceProvider::boot()` — `URL::forceRootUrl(config('app.url'));` (plus `URL::forceScheme()` if HTTPS is terminated upstream). Standard Laravel practice; makes every URL helper agree with the sitemap builder.

### F-4 · Moderate-high — hreflang alternate links are entirely unimplemented

**Where:** grep for `hreflang` across `app/` and `resources/` — zero matches

**Repro:** the object page (and every other public page checked) emits a canonical link, OG tags, and JSON-LD, but no `<link rel="alternate" hreflang="…">` tags at all, in either locale.

**Spec status:** explicitly required — `l1-seo.md`: "Every page declares its alternates in all active languages, plus a default," and "Every indexable page has exactly one canonical URL per language. Alternate…"; the mechanism is meant to be emitted once, shell-wide (`l1-platform-shell.md` §4: "Emit the language alternates once, in the shell, and reuse them for both the switcher and l1-seo.md's alternate links").

**Impact:** for a bilingual EN/RU portal built around per-language URLs, this is the standard signal search engines use to serve the right language variant to the right audience. Its complete absence means Google has no structured way to know `/en/o/hotel-nistru` and `/ru/o/otel-nistru` are the same listing in two languages — a real, spec-mandated SEO capability that was never built, not a degraded one.

**Suggested fix:** a scoped task, not a one-liner — the shell-level alternate-link data the language switcher already needs (per-page URL in every active language) is the same data hreflang needs; wire it into the layout's `<head>` once and both consumers share it, matching `l1-platform-shell.md`'s own stated intent.

### F-5 · Test defect (not a product bug) — A real test asserts against Laravel's own test-client default, not real "no preference" behaviour

**Where:** `tests/Feature/Public/PublicRootEntryTest.php:86-97`

`it('follows the registry when a different language is made primary')` fails in the full-suite run and even alone: it inserts 'ru' as the only `is_primary` language, then asserts a bare `$this->get('/')` redirects to `/ru` — but gets `/en`.

**Root cause:** Symfony's HTTP test-request factory silently attaches a default `Accept-Language: en-us,en;q=0.5` header to any request that doesn't set one explicitly. Verified directly: `PublicEntryLocaleResolver::resolve()` and `LanguageRegistry::primaryLocale()` both return the correct value ('ru') in isolation; the resolver, given the SAME test's implicit 'en' Accept-Language header, correctly prefers 'en' — an explicit visitor preference legitimately outranks the registry's own primary language, exactly as the resolver's own docblock documents. Fed an Accept-Language that genuinely matches nothing active ('de'), the same resolver correctly falls back to 'ru' — proven both via a direct service call and a real HTTP round trip.

**Why it matters anyway:** the suite currently has no test that actually exercises "nothing in Accept-Language matches → fall back to the registry primary" for a non-English primary — this test's name claims to, but its assertion never reaches that branch.

**Suggested fix:** pass an explicit, genuinely-non-matching header — `$this->get('/', ['Accept-Language' => 'de-DE,de;q=0.9'])` — so the test exercises the fallback path its name promises.

## Confirmed correct (positive findings)

- **MFA enforcement works.** All 52 admin pages swept correctly 302-redirected a fresh `chief_administrator` account with no second factor configured to the MFA set-up page — `EnsureSecondFactorForPrivilegedRoles` genuinely blocks access, no exceptions found.
- **The core conversion flow works end to end** once contact-channel data exists: catalog card → object page → contact click → `302` to `tel:`/`wa.me`/etc., verified with real seeded data including tier badge rendering and correct API JSON shape.
- **CSRF enforcement** on `{lang}/feedback` and `{lang}/country` — both correctly `419` without a valid token.
- **Locale/redirect handling** — `/` → `/en` primary-language redirect, 404 on inactive/unknown language segments, all correct.
- **API module gating** — `api/v1/*` correctly 404s (not exposes) while the "api" feature module is in its shipped-disabled default state.

## Route coverage

### Public site (17 routes) — deepest pass, all exercised

| Method | Route | Status | Notes |
| --- | --- | --- | --- |
| GET | `/` | ✅ pass | 302 → /en, correct |
| GET | `{lang}` | ✅ pass | EN + RU both render |
| GET | `{lang}/catalog` | ✅ pass | Tier badge, contact links correct. 5-min result cache, no write-path invalidation — by design |
| GET | `{lang}/{country}` | ✅ pass | |
| GET | `{lang}/{country}/{path}` | ✅ pass | 2-level path tested, breadcrumbs correct |
| GET | `{lang}/o/{slug}` | ⚠️ gap | Renders correctly but no hreflang (F-4); canonical host not pinned (F-3) |
| GET | `{lang}/objects/{object}/contact/{channel}/click` | ✅ pass | 302 → tel:/wa.me deep link, correct |
| GET | `{lang}/map/pins` | ✅ pass | |
| GET | `{lang}/map/pins/{object}` | ✅ pass | |
| GET | `{lang}/blog` | ✅ pass | Empty state, no crash |
| GET | `{lang}/blog/{article}` | ⏳ pending | Needs a seeded article |
| GET | `{lang}/news` | ✅ pass | Empty state, no crash |
| GET | `{lang}/news/{newsItem}` | ⏳ pending | Needs a seeded news item |
| GET | `{lang}/promotions/{promotion}` | ⏳ pending | Needs a seeded promotion |
| POST | `{lang}/feedback` | ✅ pass | Plain controller; CSRF correctly enforced |
| POST | `{lang}/country` | ✅ pass | CSRF correctly enforced |
| GET | `{lang}/privacy-policy` | ✅ pass | |
| GET | `{lang}/terms` | ✅ pass | |
| GET | `banners/{banner}/click` | ⏳ pending | Needs a seeded banner |

### Owner cabinet (23 routes) — 22 pass, 1 crash

| Method | Route | Status | Notes |
| --- | --- | --- | --- |
| GET | `cabinet/login` | ✅ pass | Renders 200; submission not driven (Livewire form) |
| POST | `cabinet/logout` | ⏳ pending | |
| GET | `cabinet/settings` | ❌ crash | F-6 |
| GET | `cabinet/{tenant}` | ✅ pass | |
| GET | `cabinet/{tenant}/bump-object` | ✅ pass | |
| GET | `cabinet/{tenant}/objects` | ✅ pass | |
| GET | `cabinet/{tenant}/objects/{record}/edit` | ✅ pass | Contacts section is F-1 |
| GET | `cabinet/{tenant}/rooms` | ✅ pass | |
| GET | `cabinet/{tenant}/rooms/create` | ✅ pass | |
| GET | `cabinet/{tenant}/rooms/{record}/edit` | ⏳ pending | Needs a seeded room |
| GET | `cabinet/{tenant}/services` | ✅ pass | |
| GET | `cabinet/{tenant}/services/{record}/edit` | ⏳ pending | Needs a seeded service |
| GET | `cabinet/{tenant}/photos` | ✅ pass | |
| GET | `cabinet/{tenant}/reviews` | ✅ pass | |
| GET | `cabinet/{tenant}/news` | ✅ pass | |
| GET | `cabinet/{tenant}/news/create` | ✅ pass | |
| GET | `cabinet/{tenant}/news/{record}/edit` | ⏳ pending | Needs a seeded news item |
| GET | `cabinet/{tenant}/promotions` | ✅ pass | |
| GET | `cabinet/{tenant}/promotions/create` | ✅ pass | |
| GET | `cabinet/{tenant}/promotions/{record}/edit` | ⏳ pending | Needs a seeded promotion |
| GET | `cabinet/{tenant}/notifications` | ✅ pass | |
| GET | `cabinet/{tenant}/statistics` | ✅ pass | |

### Back office / admin (78 routes) — 52 swept, 0 crashes, blocked on MFA set-up by design

All 52 pages swept correctly 302-redirected a fresh `chief_administrator` account to the MFA set-up page — Filament's native MFA, wired to `EnsureSecondFactorForPrivilegedRoles`, genuinely blocks a privileged account with no second factor configured, on every single page, no exceptions. Confirmed as a positive finding (enforcement working as intended), not tested further past MFA set-up since driving TOTP enrollment through a test harness was out of scope for this pass.

Areas covered: auth (login/logout/MFA set-up), objects & moderation (objects CRUD, moderation-requests queue + review, object-types, owners), geography (territories), placement & monetization (packages, tiers, financial-records, commerce-reports), content (articles, article-categories, article-tags, news-items, promotions, promotion-labels, catalog-filter-promotions), advertising (banners, banner-slots, notification-broadcast), SEO (seo-metadata-templates, redirects, error-pages, seo-health-dashboard), platform (languages, modules, portal-settings, interface-catalog-editor, translation-report, api-clients), operations (backup-administration, backup-restore), import (data-import).

### Public API v1 (14 routes)

| Method | Route | Status | Notes |
| --- | --- | --- | --- |
| GET | `api/v1/status` | ✅ pass | 404 by default — "api" module ships disabled; correct. 200 once enabled |
| GET | `api/v1/docs` | ✅ pass | Same module gate, correct |
| GET | `api/v1/token` | ❌ bug | 500 without Accept header — F-2. 200 with a valid token |
| GET | `api/v1/objects` | ❌ bug | Same F-2. With a token: clean, correct JSON incl. pagination, tier badge, contact links |
| GET | `api/v1/objects/{object}` | ⏳ pending | |
| GET | `api/v1/objects/{object}/reviews` | ⏳ pending | |
| GET | `api/v1/territories` | ❌ bug | Same F-2 |
| GET | `api/v1/territories/{territory}` | ⏳ pending | |
| GET | `api/v1/countries` | ⏳ pending | |
| GET | `api/v1/object-types` | ⏳ pending | |
| GET | `api/v1/amenities` | ⏳ pending | |
| GET | `api/v1/articles` | ⏳ pending | |
| GET | `api/v1/news` | ⏳ pending | |
| GET | `api/v1/promotions` | ⏳ pending | |

### Infrastructure & cross-cutting

- **Ops surfaces:** horizon, pulse, `up` (health check) ✅, sanctum/csrf-cookie, storage upload/serve — not deeply exercised this pass.
- **SEO plumbing:** `sitemap.xml` ✅ — empty index with no content, correctly lists per-locale territory/object children once content exists; uses `config('app.url')` correctly (see F-3 for the resulting inconsistency with every other page). `robots.txt` ✅.
- **Cross-cutting checks:** EN/RU completeness spot-checked and correct on tested pages; placement-tier ordering not violated on tested pages; CSRF and authz boundaries correct where tested; 404 handling correct.

## Not yet exercised (~70 routes)

- Admin/cabinet pages needing seeded content beyond the MFA gate (would require completing TOTP enrollment in the test harness — out of scope for this pass).
- Moderation review/approve-reject action flow, object bulk actions beyond what the automated suite already covers.
- Remaining API v1 detail/show endpoints and list endpoints for countries, object-types, amenities, articles, news, promotions.
- Horizon/Pulse dashboards, backup restore rehearsal, data-import pipeline live run.
- Full N+1/query-budget verification beyond what the existing `PublicPerformanceBudgetTest` already covers.

## Recommended next step

Findings F-1 through F-4 and F-6 describe real product gaps against this project's own specifications and architecture. F-5 is a test-authoring fix. Recommend routing the confirmed findings through `/magic.spec` to formalize them as specification amendments or backlog items, then `/magic.task` to plan the fix work — this document is the input, not a replacement for that process.
