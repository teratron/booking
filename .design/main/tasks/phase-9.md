---
phase: 9
name: "Post-Launch QA Remediation"
status: In Progress
subsystem: "app/Filament/Admin/Resources/Objects, app/Filament/Cabinet, app/Providers/AppServiceProvider.php, bootstrap/app.php, app/Services/Seo, app/Services/Shell, resources/views/components/layouts, tests/Feature"
requires: ["phase-2", "phase-4", "phase-6"]
provides: []
key_files:
  created: []
  modified:
    - app/Providers/Filament/CabinetPanelProvider.php
    - app/Providers/AppServiceProvider.php
    - tests/Feature/Cabinet/CabinetFoundationTest.php
    - tests/Feature/Public/MetadataResolutionTest.php
    - tests/Feature/Public/PublicRootEntryTest.php
patterns_established:
  - "A tenant-independent Filament page inside a tenancy-enabled panel must use ->profile(..., isSimple: true) — the full layout's shared chrome (sidebar, topbar, tenant menu) builds tenant-scoped URLs unconditionally with no null-tenant guard anywhere in that vendor Blade tree."
  - "URL::forceRootUrl() alone does not pin the scheme when TLS terminates upstream of the app — pair it with URL::forceScheme(parse_url($appUrl, PHP_URL_SCHEME)), both derived from the same config('app.url') value."
duration_minutes: ~
---

# Stage 9 Tasks — Post-Launch QA Remediation

**Phase:** 9
**Status:** In Progress (7/15 — Tracks C, E, and F closed 2026-08-22. 8 tasks across Tracks A, B, D remain `Blocked [!] (Spec RFC)`: `l1-object-profile.md`, `l1-public-api.md`, and `l1-platform-shell.md` are `RFC` in `INDEX.md`, not `Stable`; `/magic.run main`'s Pre-flight Spec Stability Spot-Check caught this before any task began, since Phase 9 had been scheduled against them without that check being applied at plan time. The `/magic.spec main` review that preceded this plan already confirmed each spec's referenced section states the correct, settled behaviour — what blocks here is the document's overall status field, not its content. Resolution: `/magic.spec main` reviews and promotes the three specs `RFC → Stable` via `@role:spec-critic`'s review gate; `/magic.task main` then re-evaluates and unblocks. `T-9G01` (full regression gate) also waits on this — its own precondition is all of Tracks A–F closed, not merely C/E/F. See STATE.md § Blockers.)
**Strategic Goal:** Close five confirmed, live-reproduced functional defects a full-surface
QA sweep found against the running instance (`.drafts/qa-sweep-report.md`), plus one
test-suite defect the same sweep surfaced. Every defect was checked directly against its
governing specification before being scheduled here — in every case the specification
already states the correct behaviour and the implementation diverges from it, so no
specification required amendment (confirmed via `/magic.spec main` immediately before
this plan was written; see that session's conclusion). This phase is implementation-only.

## What Makes This Phase Different

Every prior phase built new capability against a specification. **This phase fixes
capability that was already built, and already specified, but does not do what its own
specification says.** No task here changes what the product is supposed to do; each one
makes the code agree with a requirement that was already settled — several of them
explicitly, by name, before this phase existed (`l1-seo.md`'s canonical-host and
hreflang-alternate requirements; `l1-object-profile.md` §5.2's contact-channel type
model; `l1-public-api.md`'s unauthenticated-request contract).

**Severity, not spec novelty, orders the tracks.** Track A is scheduled first because it
breaks the product's entire conversion mechanism — no contact channel can be saved
through either UI that manages one. Track E is scheduled second because it is a live
crash on a page every owner eventually visits. Tracks B, C, D, and F are real but
narrower in blast radius, and are genuinely independent of each other and of A/E — six
tracks touch six non-overlapping file sets, so this phase is six-wide, wider than any
phase before it, precisely because none of these fixes shares a resource with another.

**Track E carries a known-unknown.** The crash traces into Filament's own tenancy/layout
code (`Panel::getTenantBillingUrl()`), not this project's application code. The task
states what was verified (the exact call site and the null-tenant condition that
triggers it) and what was not (whether the correct fix is an app-level guard, a panel
configuration change, or an upstream Filament patch) — Track E's own first step is
narrowing that down, not applying a fix sight-unseen.

## Atomic Checklist

### Track A — Contact Channel Type Selection

- [ ] [T-9A01] Admin object form — add the contact-channel type selector `Blocked [!] (Spec RFC)`
- [ ] [T-9A02] Cabinet object form — add the contact-channel type selector `Blocked [!] (Spec RFC)`
- [ ] [T-9A03] Validation — a contact channel saves cleanly through both forms `Blocked [!] (Spec RFC)`

### Track B — API Guest-Redirect JSON Contract

- [ ] [T-9B01] Exempt `api/*` from the guest-redirect-to-login fallback `Blocked [!] (Spec RFC)`
- [ ] [T-9B02] Validation — unauthenticated API requests return 401 JSON regardless of Accept header `Blocked [!] (Spec RFC)`

### Track C — Canonical Host Consistency

- [x] [T-9C01] Pin URL generation to the configured `APP_URL`
- [x] [T-9C02] Validation — canonical/OG URLs match `APP_URL` regardless of request Host

### Track D — hreflang Alternate Links

- [ ] [T-9D01] Carry per-language alternate URLs on `ResolvedMetadata` `Blocked [!] (Spec RFC)`
- [ ] [T-9D02] Emit hreflang alternate tags in the public layout `<head>` `Blocked [!] (Spec RFC)`
- [ ] [T-9D03] Validation — hreflang tags present, correct, and reciprocal across active languages `Blocked [!] (Spec RFC)`

### Track E — Cabinet Settings Crash

- [x] [T-9E01] Root-cause the tenant-billing-URL crash on the tenant-independent Settings page
- [x] [T-9E02] Apply the fix identified by `T-9E01`
- [x] [T-9E03] Validation — `cabinet/settings` renders for an authenticated owner

### Track F — Test-Suite Correction

- [x] [T-9F01] Fix `PublicRootEntryTest`'s false assumption about the test client's default Accept-Language

### Track G — Full-Suite Regression Gate

- [ ] [T-9G01] Full `composer quality` and non-slow Pest suite, clean, after Tracks A–F close

## Task Detail

### Track A — Contact Channel Type Selection

**[T-9A01] Admin object form — add the contact-channel type selector**

- **Spec:** [l1-object-profile.md](../specifications/l1-object-profile.md) §5.2 (Contact Channel Model — `type -> ContactChannelType` is a required, non-optional field of the model); surface is [l1-back-office.md](../specifications/l1-back-office.md)
- **Status:** `Blocked [!] (Spec RFC)` — `l1-object-profile.md` is `RFC` in `INDEX.md`, not `Stable`. Run `/magic.spec main` to review/promote, then `/magic.task main` to unblock.
- **Assignment:** Agent
- **Verify:** `Livewire::test(EditObject::class, ['record' => $objectId])->fillForm(['contactChannels' => [['contact_channel_type_id' => $typeId, 'raw_value' => '+37360000000', 'label' => 'Front desk']]])->call('save')->assertHasNoFormErrors()` succeeds and inserts a `contact_channels` row with a non-null `contact_channel_type_id`, where the prior schema (no type field) threw `QueryException: SQLSTATE[23502]`.
- **Handoff:** `T-9A03` covers both A01 and A02 in one validation pass.
- **Notes:** `app/Filament/Admin/Resources/Objects/Schemas/ObjectForm.php`'s `contactsTab()` `Repeater::make('contactChannels')->relationship()` currently schemas only `raw_value` and `label`. Add `Select::make('contact_channel_type_id')->relationship('contactChannelType', 'key')->required()` (or the translated display-name equivalent, matching how `amenities` already resolves labels via `getOptionLabelFromRecordUsing`) inside that repeater's `->schema([...])`. `derived_link` computation (from the type's own `link_template`) is a separate, already-working concern — confirm it still fires correctly once a type is actually selected; do not duplicate that logic here.

**[T-9A02] Cabinet object form — add the contact-channel type selector**

- **Spec:** [l1-object-profile.md](../specifications/l1-object-profile.md) §5.2; surface is [l1-object-onboarding.md](../specifications/l1-object-onboarding.md)
- **Status:** `Blocked [!] (Spec RFC)` — same governing spec as `T-9A01`, same blocker.
- **Assignment:** Agent
- **Verify:** Same shape as `T-9A01`'s Verify line, against `app/Filament/Cabinet/Resources/Objects/Schemas/ObjectForm.php`'s `contactsSection()`.
- **Handoff:** `T-9A03`.
- **Notes:** Identical gap, identical fix, different file — `contactsSection()`'s `Repeater::make('contactChannels')->relationship()` has the same two-field schema as the admin form. This form's own docblock already calls contact channels "the mechanism this portal's whole conversion path depends on," which is exactly why this section is explicitly exempted from the moderation gate the rest of the form goes through — the fix must land inside that same unmoderated write path, not behind it.

**[T-9A03] Validation — a contact channel saves cleanly through both forms**

- **Goal:** Verify `T-9A01` and `T-9A02` against [l1-object-profile.md](../specifications/l1-object-profile.md) §5.2 and the live-reproduced failure in `.drafts/qa-sweep-report.md` F-1.
- **Method:** Feature tests in `tests/Feature/Admin/ObjectResourceFormTest.php` and `tests/Feature/Cabinet/CabinetObjectEditingTest.php` asserting a contact channel with an explicit type saves without a `QueryException`, and that the resulting row's `derived_link` matches the selected type's `link_template` applied to the raw value. Also re-run the already-passing end-to-end proof this phase's own diagnosis relied on: a saved channel's public click route (`{lang}/objects/{object}/contact/{channel}/click`) still 302s to the correct deep link.
- **Status:** `Blocked [!] (Spec RFC)` — depends on `T-9A01`/`T-9A02`, both blocked on the same spec.

### Track B — API Guest-Redirect JSON Contract

**[T-9B01] Exempt `api/*` from the guest-redirect-to-login fallback**

- **Spec:** [l1-public-api.md](../specifications/l1-public-api.md) (unauthenticated request → 401)
- **Status:** `Blocked [!] (Spec RFC)` — `l1-public-api.md` is `RFC` in `INDEX.md`, not `Stable`. Run `/magic.spec main` to review/promote, then `/magic.task main` to unblock.
- **Assignment:** Agent
- **Verify:** `curl -H "Accept: */*" http://<host>/api/v1/objects` (no token, no explicit JSON Accept header) returns `401` with `{"message":"Unauthenticated."}`, not `500`.
- **Handoff:** `T-9B02`.
- **Notes:** Root cause confirmed live: `Illuminate\Auth\Middleware\Authenticate::redirectTo()` decides whether to attempt a redirect based on `$request->expectsJson()` (Accept-header-driven), which is a different check from this app's own `bootstrap/app.php` `shouldRenderJsonWhen(fn ($request) => $request->is('api/*'))` — so a request whose Accept header doesn't ask for JSON still triggers a `route('login')` call, and this app registers no route literally named `login` (Filament panels register `filament.admin.auth.login` / `filament.cabinet.auth.login` instead), producing an unhandled `RouteNotFoundException`. Fix in the same `withMiddleware()` closure in `bootstrap/app.php`, using the identical `$request->is('api/*')` predicate already sitting one block below it: `$middleware->redirectGuestsTo(fn ($request) => $request->is('api/*') ? null : route('filament.admin.auth.login'));` — returning `null` tells Laravel's guest handler never to attempt a redirect for that request, letting the already-correct `shouldRenderJsonWhen` render it as JSON. Confirm this doesn't change guest-redirect behaviour for the admin/cabinet panels, which have their own Filament-registered login redirects independent of this global fallback.

**[T-9B02] Validation — unauthenticated API requests return 401 JSON regardless of Accept header**

- **Goal:** Verify `T-9B01` against [l1-public-api.md](../specifications/l1-public-api.md) and F-2 in `.drafts/qa-sweep-report.md`.
- **Method:** Feature test (`tests/Feature/Api/` — extend `ApiModuleGateTest.php` or add a sibling) asserting `$this->get('/api/v1/objects')` (no `Accept` header override, no token) returns `401`, and that the same assertion holds for `/api/v1/territories` and `/api/v1/token`. Confirm `composer test` still passes `tests/Feature/Api/ApiRateLimitTest.php` and `ApiModuleGateTest.php` unchanged (no regression to the module-gate 404 path, which must still fire ahead of this check when the `api` module is disabled).
- **Status:** `Blocked [!] (Spec RFC)` — depends on `T-9B01`, blocked on the same spec.

### Track C — Canonical Host Consistency

**[T-9C01] Pin URL generation to the configured `APP_URL`**

- **Spec:** [l1-seo.md](../specifications/l1-seo.md) ("canonicals always name that host" — the 2026-08-15 URL-model decision, §2/§5.1)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** With `APP_URL=http://booking.test` in `.env`, a request to the object page via a *different* Host header (`http://localhost:8300/en/o/hotel-nistru`) renders `<link rel="canonical" href="http://booking.test/en/o/hotel-nistru">` — matching, not diverging from, `sitemap.xml`'s own already-correct output.
- **Handoff:** `T-9C02`.
- **Notes:** Confirmed `l2-tech-stack.md`'s deployment table: production "TLS terminates at the CDN edge" and `bootstrap/app.php` configures no `trustProxies()` at all, so `$request->getScheme()` reports the *raw* connection scheme reaching the app — plain HTTP even in production, unless corrected. This matters because `URL::forceRootUrl()` alone is **not** sufficient: `Illuminate\Routing\UrlGenerator::formatRoot()` rewrites the forced root's own scheme prefix to whatever `formatScheme()` resolves to, which without an explicit `forceScheme()` call falls straight back to the live request's own detected scheme — so a `forceRootUrl`-only fix would still silently downgrade every canonical/OG/API URL to `http://` in production. Implemented in `App\Providers\AppServiceProvider::pinGeneratedUrlsToConfiguredAppUrl()`, called from `boot()`: `URL::forceRootUrl($appUrl)` **and** `URL::forceScheme(parse_url($appUrl, PHP_URL_SCHEME))`, both derived from the same `config('app.url')` value rather than a hard-coded `'https'` literal — stays correct for local dev's own `http://` URL with no environment branch, and can never drift out of sync with `APP_URL` since there is only one source of truth. `sitemap.xml` (`spatie/laravel-sitemap`) already read `config('app.url')` directly and needed no change.

**[T-9C02] Validation — canonical/OG URLs match `APP_URL` regardless of request Host**

- **Goal:** Verify `T-9C01` against [l1-seo.md](../specifications/l1-seo.md) and F-3 in `.drafts/qa-sweep-report.md`.
- **Method:** Feature test asserting the object page, catalog page, and territory page's rendered `<link rel="canonical">` and `og:*` tags equal `config('app.url')` + the expected path, when the test request is made with a Host header that deliberately does not match `APP_URL`. Confirm `PublicPerformanceBudgetTest` still passes (a global `URL::forceRootUrl()` call must not add a query or measurably regress TTFB).
- **Status:** Done — added `it('names the configured APP_URL host in the canonical link regardless of the request Host header', ...)` to `tests/Feature/Public/MetadataResolutionTest.php`: requests `http://a-completely-different-host.invalid/en/md/host-mismatch` directly (an absolute URL to the test client sets that Host), asserts the canonical link names `config('app.url')`'s host and the mismatched host never appears in the response at all. Ran red against the code with `pinGeneratedUrlsToConfiguredAppUrl()` temporarily removed, green with it restored. Full non-slow `tests/Feature/Public` and `tests/Feature/Api` suites re-run alongside it as a regression check — result recorded at `T-9G01` (the phase's own cross-cutting gate), not duplicated here.

### Track D — hreflang Alternate Links

**[T-9D01] Carry per-language alternate URLs on `ResolvedMetadata`**

- **Spec:** [l1-seo.md](../specifications/l1-seo.md) ("every page declares its alternates in all active languages, plus a default"); [l1-platform-shell.md](../specifications/l1-platform-shell.md) §4 ("emit the language alternates once, in the shell, and reuse them for both the switcher and l1-seo.md's alternate links")
- **Status:** `Blocked [!] (Spec RFC)` — `l1-platform-shell.md` is `RFC` in `INDEX.md`, not `Stable` (`l1-seo.md` alone is `Stable`, but §4's shared-emission requirement spans both). Run `/magic.spec main` to review/promote, then `/magic.task main` to unblock.
- **Assignment:** Agent
- **Verify:** `app(\App\Services\Seo\MetadataResolver::class)->resolve($object, 'en')->alternates` returns an array keyed by every active language code (`en`, `ru`) to that page's URL in that language, computed via the same `App\Services\Shell\LocaleSwitchResolver::targetUrl()` call the language switcher already uses — not a second, independent URL-construction path.
- **Handoff:** `T-9D02`.
- **Notes:** `App\Support\Seo\ResolvedMetadata` currently has no field for this at all (`title`, `description`, `canonicalUrl`, `indexable`, `ogTitle`, `ogDescription`, `ogImageUrl` only) — confirms this was never wired, not merely disabled. `resources/views/components/public/language-switcher.blade.php` already calls `App\Services\Shell\LocaleSwitchResolver::targetUrl(request(), $language->code)` per active language for its own dropdown; `MetadataResolver::resolve()`/`resolveCatalog()`/`resolveTypedCatalog()` should call the identical resolver for the identical set of active languages and attach the result as a new `alternates` readonly property, so the switcher and the hreflang tags are provably the same URLs, never two independently-computed sets that can drift apart.

**[T-9D02] Emit hreflang alternate tags in the public layout `<head>`**

- **Spec:** [l1-seo.md](../specifications/l1-seo.md); [l1-platform-shell.md](../specifications/l1-platform-shell.md) §4
- **Status:** `Blocked [!] (Spec RFC)` — depends on `T-9D01`, blocked on the same spec.
- **Assignment:** Agent
- **Verify:** Rendered HTML of the object page, catalog page, and home page each include `<link rel="alternate" hreflang="en" href="…">` and `hreflang="ru"`, plus one `hreflang="x-default"` pointing at the primary-language URL — grep the response body for `rel="alternate"` in a feature test rather than asserting manually.
- **Handoff:** `T-9D03`.
- **Notes:** `resources/views/components/layouts/public.blade.php` already renders `canonical`/`og:*` from `$metadata` in its `<head>` (lines ~26-40); add a `@foreach ($metadata->alternates as $locale => $url)` loop emitting one `<link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}">` per entry, plus the `x-default` line, guarded the same way the existing canonical block is guarded (`@if ($metadata)`). No new Livewire/JS surface — this is server-rendered, same request, same response.

**[T-9D03] Validation — hreflang tags present, correct, and reciprocal across active languages**

- **Goal:** Verify `T-9D01` and `T-9D02` against [l1-seo.md](../specifications/l1-seo.md) and F-4 in `.drafts/qa-sweep-report.md`.
- **Method:** Feature test fetching the EN and RU variants of the same object/catalog/territory page and asserting: (a) each declares hreflang alternates for every other active language, (b) the alternate URLs are reciprocal (EN page's `hreflang="ru"` URL, fetched, declares an `hreflang="en"` pointing back), (c) exactly one `x-default` per page. Extend `PublicShellTest.php` or add a sibling `PublicHreflangTest.php`.
- **Status:** `Blocked [!] (Spec RFC)` — depends on `T-9D01`/`T-9D02`, both blocked on the same spec.

### Track E — Cabinet Settings Crash

**[T-9E01] Root-cause the tenant-billing-URL crash on the tenant-independent Settings page**

- **Spec:** None — this is a Filament/framework integration defect, not a specification gap; the crash lives in `vendor/filament/filament`, not application code, and no L1/L2 specification governs Filament's own layout internals.
- **Status:** Done
- **Assignment:** Agent
- **Verify:** A written finding (as a task Note, not a spec) naming: the exact Filament version in `composer.lock`, whether this is a known upstream issue (changelog/issue-tracker check), and which of the three candidate fixes applies — (i) an app-level guard before the layout renders, (ii) a panel-provider configuration change, (iii) an upstream version bump. `T-9E02` implements whichever this task identifies.
- **Handoff:** `T-9E02`.
- **Notes:** Confirmed live: `GET /cabinet/settings` → `500`, `Filament\Panel::getTenantBillingUrl(): Argument #1 ($tenant) must be of type Illuminate\Database\Eloquent\Model, null given`, thrown from Filament's own `layout/index.blade.php`. `cabinet/settings` (registered via `->profile(Settings::class, isSimple: false)` in `CabinetPanelProvider`) is the one cabinet route with no `{tenant}` segment — every other route is `cabinet/{tenant}/…` — so `Filament::getTenant()` is legitimately `null` here, and the shared layout's billing-URL resolution doesn't null-guard before calling into it. Do not fix this by forcing a tenant onto the Settings page — the page is deliberately tenant-independent (an account page has nothing to do with any one managed object) — the fix targets the layout/panel-config side, not the route's own tenancy.
  **Finding (2026-08-22):** Installed version is `filament/filament v5.7.6` (`composer.lock`), confirmed the newest available release (`composer show filament/filament --all`, no `v5.7.7`+ exists) — option (iii), an upstream bump, is unavailable. Not a known/tracked upstream issue check-worthy beyond the source read below (no local issue tracker access; the defect is visible directly in vendor source, which is authoritative).

  Investigation went through two rounds, the first incomplete — recorded here because the second round's own test (`T-9E03`) is what disproved the first. **Round 1 (superseded):** traced the reported crash to `HasTenancy::getTenantMenuItemGroups()` unconditionally building a `'billing'` menu item via `Filament::getTenantBillingUrl($tenant ?? Filament::getTenant())`, `Model $tenant` non-nullable, `TypeError` on this page's `null` tenant. Patched via `->tenantMenuItems(['billing' => Action::make('billing')->visible(false)])`, which resolved *that specific* crash — but re-running `T-9E03`'s real HTTP-level test (not `Livewire::test()`, which never renders the panel's own layout chrome at all) immediately surfaced a **second** unguarded call one line away, `filament()->getTenantName($currentTenant)` in `tenant-menu.blade.php` itself (same non-nullable-`Model` shape), and — after guarding that too via `->tenantMenu(fn (): bool => Filament::getTenant() !== null)` — a **third**: the sidebar's own home/logo link (`filament()->getHomeUrl()` → `Panel::getUrl()`) fails to generate a URL for `filament.cabinet.pages.dashboard`, a genuinely tenant-scoped route (`cabinet/{tenant}`), with no tenant to bind.

  **Round 2 (actual root cause):** these are not three independent bugs to patch one at a time — they are three symptoms of one design fact: Filament's full `index` panel layout (sidebar + topbar chrome) assumes, throughout, that a tenancy-enabled panel always has a *current* tenant on every page it renders, and builds tenant-scoped URLs unconditionally on that assumption with no null-guard anywhere in that shared Blade tree. `cabinet/settings` is this panel's one deliberately tenant-independent route, so every one of those unconditional builds fails on it, and patching each call site individually chases an open-ended list — the tenant switcher's own tenant list, sub-navigation, anything else in that shared layout doing the same thing, discoverable only by hitting each in turn. **Fix: `->profile(Settings::class, isSimple: true)`** — reverting the codebase's original, deliberate `isSimple: false` override. `isSimple: true` is Filament's *own default* (`HasAuth.php`'s `$isProfilePageSimple = true`) specifically for pages like this: `EditProfile::getLayout()` switches to `filament-panels::components.layout.simple`, which renders no sidebar, no tenant switcher, no tenant-scoped link of any kind — just a centered card and a plain, non-tenant-aware `SimpleUserMenu`. Zero remaining edge cases (an owner with no objects at all would still crash under any per-call guard, since there would be no tenant to fall back to; `isSimple: true` has no such gap because it never attempts a tenant-scoped URL). Trade-off — Settings loses the cabinet's sidebar/tenant-switcher chrome, replaced by the bare centered layout — surfaced to the project owner directly rather than assumed; owner chose the complete fix over patching individual call sites ("no crutches, everything must be correct, even if it takes time").

**[T-9E02] Apply the fix identified by `T-9E01`**

- **Spec:** None (see `T-9E01`)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `GET /cabinet/settings` returns `200` for an authenticated `object_owner`, and every other cabinet route's rendering is unchanged (no regression to tenant-scoped pages' own billing-URL behaviour, if any exists elsewhere in the panel chrome).
- **Handoff:** `T-9E03`.
- **Notes:** Implemented in `app/Providers/Filament/CabinetPanelProvider.php` — `->profile(Settings::class, isSimple: false)` → `isSimple: true`, per `T-9E01`'s Round 2 finding. No other cabinet route touches `isSimple` or the layout selection, so every tenant-scoped page's own rendering (sidebar, tenant switcher, navigation) is untouched — only the one page that was already crashing changes shape. `tests/Feature/Cabinet/CabinetSettingsTest.php`'s existing coverage (form save, locale, notification preferences) exercises `Settings` via `Livewire::test()`, which mounts the component directly and never renders the page layout either way, so none of it needed updating for this change.

**[T-9E03] Validation — `cabinet/settings` renders for an authenticated owner**

- **Goal:** Verify `T-9E01`/`T-9E02` against the live-reproduced crash in `.drafts/qa-sweep-report.md` F-6.
- **Method:** Feature test: `$this->actingAs($owner)->get('/cabinet/settings')->assertSuccessful()`. Add to `tests/Feature/Cabinet/CabinetFoundationTest.php` or a sibling. Confirm the page's actual content renders (name/email/password fields, locale selector, notification preferences) rather than merely asserting the status code.
- **Status:** Done — added `it('renders the account Settings page for an authenticated owner, the one cabinet route with no tenant segment', ...)` to `tests/Feature/Cabinet/CabinetFoundationTest.php`, using `route('filament.cabinet.auth.profile')` (never the literal path, per this project's panel-path rule) and asserting the owner's own name/email render in the response body, not just the status code. Ran red against the pre-fix code (twice — first against the Round-1 patch, which still crashed one call site later; then confirmed green only after Round 2's `isSimple: true`).

### Track F — Test-Suite Correction

**[T-9F01] Fix `PublicRootEntryTest`'s false assumption about the test client's default Accept-Language**

- **Spec:** None — test-authoring defect, not a product bug. `PublicEntryLocaleResolver::resolve()` and `LanguageRegistry::primaryLocale()` were independently verified correct during the QA sweep (F-5 in `.drafts/qa-sweep-report.md`): fed an `Accept-Language` that genuinely matches nothing active, the resolver correctly falls back to the registry's primary language, proven both via a direct service call and a real HTTP round trip.
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `tests/Feature/Public/PublicRootEntryTest.php`'s `it('follows the registry when a different language is made primary')` passes both alone (`--filter`) and inside the full suite.
- **Handoff:** None — this task is self-contained.
- **Notes:** Laravel/Symfony's HTTP test-request factory silently attaches `Accept-Language: en-us,en;q=0.5` to any request that doesn't set one explicitly, so the test's bare `$this->get('/')` was never actually exercising the "nothing matches → fall back to primary" branch its own name claims to test — 'en' genuinely is in the request's (implicit) accepted-languages set, and the resolver correctly prefers an explicit visitor preference over the registry primary. Confirmed red beforehand: `assertRedirect` failed expecting `/ru`, got `/en` — exactly the wrong-branch symptom the diagnosis predicted. Fixed by passing `$this->get('/', ['Accept-Language' => 'de-DE,de;q=0.9'])` — a header that genuinely matches no active language — so the assertion reaches the branch the test name promises. Confirmed green after.

### Track G — Full-Suite Regression Gate

**[T-9G01] Full `composer quality` and non-slow Pest suite, clean, after Tracks A–F close**

- **Goal:** Confirm none of the six fixes regressed anything the existing 171-file suite already covers, and that the phase's own new validation tasks (`T-9A03`, `T-9B02`, `T-9C02`, `T-9D03`, `T-9E03`, `T-9F01`) are all green together, not just individually.
- **Method:** `docker compose exec app sh -c "php artisan config:clear --ansi && php -d memory_limit=1G vendor/bin/pest --exclude-group=slow"` — full pass, zero failures. Also `composer analyse` (PHPStan level 8) and `composer lint` (Pint), since Track D adds a new `ResolvedMetadata` property and Track C/B touch service-provider/bootstrap code that PHPStan resolves eagerly. Run test suites sequentially, never concurrently against `booking_testing`, per this project's own established constraint.
- **Status:** Todo — dependency-blocked, not itself `Blocked [!] (Spec RFC)`: Tracks A and B carry that marker directly (see Overview), so this gate cannot start until they clear regardless of Track C/E/F's own progress.
