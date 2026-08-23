# Deep QA Simulation Plan — full-surface click path

Every reachable touchpoint of the portal, ordered as a ladder of actors from a
cold anonymous visitor up to a chief administrator with every permission. Each
step names what is clicked, what the system must do, and what would count as a
defect. This is the checklist the simulation pass ticks off; findings land in
`qa-deep-findings.md`, TZ conformance in `qa-tz-conformance.md`.

**Baseline surface:** 177 registered routes — 24 public, 23 owner cabinet,
78 back office, 14 public API v1, 38 framework/ops.

## 0. Environment and method

| Item | Value |
| --- | --- |
| Runtime | PHP 8.5.9 (Herd), Laravel 13.26.1, Filament 5 |
| Data stores | Postgres 18 + PostGIS (Docker, host port 5433), Redis 8, MinIO, Mailpit |
| App server | `artisan serve` on `127.0.0.1:8090` |
| Interaction driver | Laravel HTTP kernel + Livewire/Filament test harness against a live Postgres schema |
| Browser driver | Playwright, for the JS-dependent surfaces only (map, Alpine overlays, Livewire filters) |

Three complementary drivers, because no single one reaches everything:

1. **Live HTTP** — real status codes, headers, HTML, redirects for every GET.
2. **Livewire/Filament harness** — the only way to press a `wire:click`, submit a
   Filament form, run a table action, or drive a bulk action with a confirmation.
3. **Browser** — the only way to catch a JS console error, a broken Alpine
   binding, or a map that renders zero pins.

Every finding must be reproduced against a live instance before it is written
down, and traced to a root cause in a named file and line.

## 1. Actor ladder

| # | Persona | Auth | What defines it |
| --- | --- | --- | --- |
| A1 | Cold anonymous visitor | none | no cookies, no `Accept-Language` preference |
| A2 | Anonymous with locale preference | none | `Accept-Language: ru`, then an explicit language switch |
| A3 | Returning visitor | none | country-preference cookie set, cookie consent accepted |
| A4 | Crawler | none | `robots.txt`, `sitemap.xml`, canonical/hreflang/JSON-LD surface |
| A5 | Hostile anonymous | none | CSRF omission, IDOR attempts, disabled-module probing, host-header spoof |
| B1 | API consumer, unscoped token | bearer | every `api/v1/*` resource ability |
| B2 | API consumer, scoped token | bearer | country/category-narrowed token, revoked token, rate limit |
| C1 | Object owner, single object | session | the cabinet's whole menu |
| C2 | Object owner, several objects | session | tenant switching |
| C3 | Object owner, unapproved object | session | moderation-pending state |
| C4 | Object staff member | session | reduced cabinet rights |
| C5 | Suspended owner | session | blocked account |
| D1 | moderator | session + MFA | moderation queue only |
| D2 | content_manager | session + MFA | news, articles, promotions |
| D3 | seo_specialist | session + MFA | SEO section |
| D4 | advertising_manager | session + MFA | banners, slots, labels |
| D5 | finance_manager | session + MFA | financial records, commerce reports |
| D6 | technical_support | session + MFA | impersonation, support surfaces |
| D7 | region_administrator | session + MFA | territory-subtree-scoped |
| D8 | country_administrator | session + MFA | country-scoped |
| E1 | chief_administrator | session + MFA | everything, including backup restore |

## 2. Persona A — anonymous visitor

### A1. Entry and shell

1. `GET /` with no headers → 302 to the primary language, never 301.
2. `GET /` with `Accept-Language: ru` → 302 to `/ru`.
3. `GET /` with `Accept-Language: ka` (registry-inactive) → falls back to primary.
4. `GET /en` → 200, full shell.
5. Header: logo link → home. Every nav item (`catalog`, `news`, `blog`, plus any
   contact/about entry) → 200, no dead link.
6. Language switcher: open, pick `ru` → lands on the *same* page in `ru`, not home.
7. Country switcher: open, pick each of the 3 countries → `POST {lang}/country`
   → redirect back, preference persisted, catalog now country-narrowed.
8. Footer: every link resolves; legal pages 200 in both locales.
9. Cookie-consent overlay: appears once, accept → does not reappear; decline path.
10. Feedback overlay: open, submit empty → validation; submit valid → stored in
    `feedback_submissions`, success message; submit without CSRF → 419.
11. `GET /xx` (unknown two-letter language) → 404, styled error page.
12. `GET /en/does-not-exist` → 404.

### A2. Search and catalog

 1. Hero search: empty submit → catalog with no filters.
 2. Hero search with a term → catalog, term applied, result count sane.
 3. `GET {lang}/catalog` → 200.
 4. Each filter control in `CatalogSearch`, one at a time: type, territory,
    amenities, price range, rating, availability, tier — each narrows results and
    each is reflected in the URL query string (bookmarkable).
 5. Two filters combined → intersection, not union.
 6. Filter producing zero results → empty state, no crash, no stale count.
 7. Reset/clear filters → back to the unfiltered set.
 8. Sorting control (if present) → order changes but **tier precedence holds**:
    no tier-4 object ever appears above a tier-1 object.
 9. Pagination: page 2, last page, page beyond last → no 500.
10. Card touchpoints: title link, "read more", each contact button, availability
    badge, tier badge, photo.
11. Catalog under a country preference vs without → different result sets.
12. Deep-link a fully-populated filter URL in a fresh session → same results.

### A3. Territory and country pages

 1. `GET {lang}/{country}` for each of 3 countries → 200, breadcrumbs, objects.
 2. `GET {lang}/{country}/{path}` at every hierarchy depth (region → district →
    city → resort) → 200, correct subtree of objects.
 3. Typed catalog inside a territory (`{settlement}/{type}`) → 200, filtered.
 4. Territory with no objects → empty state, no crash.
 5. Territory banner slots render; each banner click → `banners/{banner}/click`
    → 302 to the advertiser URL, impression and click both recorded.
 6. Breadcrumb links each resolve upward to the correct ancestor.
 7. Territory news/promotions blocks link out correctly.
 8. Inactive territory → 404, not a blank page.

### A4. Object page

 1. `GET {lang}/o/{slug}` → 200. Gallery, description, rooms, prices, amenities,
    location, map, nearby objects, object news, object promotions, contacts.
 2. Every contact channel button → `objects/{object}/contact/{channel}/click`
    → 302 to a correctly-formed deep link (`tel:`, `https://wa.me/…`,
    `viber://`, `https://t.me/…`, `mailto:`, website).
 3. Contact click of a channel belonging to a *different* object → must not
    redirect (IDOR probe).
 4. Availability badge shows only when the owner set it.
 5. Gallery: open, next/prev, close.
 6. Map on the object page: pin present at the object's coordinates.
 7. Reviews block: reads, average rating, owner replies visible.
 8. Object in draft / rejected / soft-deleted state → 404 for the public.
 9. Old slug after a rename → 301 via the redirect table.

### A5. Content sections

 1. `GET {lang}/news` → list, pagination, each item link.
 2. `GET {lang}/news/{newsItem}` → 200, related object link, image, SEO tags.
 3. `GET {lang}/blog` → list, category filter, tag filter, pagination.
 4. `GET {lang}/blog/{article}` → 200, author, related articles, related objects.
 5. `GET {lang}/promotions/{promotion}` → 200; expired promotion → 404/archived.
 6. Unpublished / future-dated content → 404 for the public.

### A6. Map

 1. `GET {lang}/map/pins` → JSON pin collection; bbox and filter params honoured.
 2. `GET {lang}/map/pins/{object}` → single pin card payload.
 3. Map renders in a browser: tiles load, pins cluster, pin click opens the card,
    card link opens the object page, route-building control (if any).

### A7. Crawler surface

 1. `robots.txt` → 200, references the sitemap, disallows both panel paths by
    their configured values (not a hardcoded `/admin`).
 2. `sitemap.xml` → index; each child sitemap 200 and well-formed.
 3. Every public page: exactly one `<link rel="canonical">`, absolute, on the
    configured host.
 4. `hreflang` alternates present for every active locale plus `x-default`.
 5. Open Graph and Twitter tags on home, object, territory, news, article.
 6. JSON-LD: `Hotel`/`Restaurant` on object, `BreadcrumbList`, `Organization`.
 7. `noindex` where the spec demands it (filtered catalog permutations).

### A8. Hostile probes

 1. `POST {lang}/feedback` without CSRF → 419.
 2. `POST {lang}/country` with an unknown country id → validation failure, no 500.
 3. `GET api/v1/objects` while the api module is off → 404 (not 403, not 500).
 4. `GET api/v1/objects` with no token and no `Accept` header → 401 JSON.
 5. `GET /portal-admin` anonymous → login, no information leak.
 6. `GET /cabinet/1` anonymous → login.
 7. Host-header spoof → canonical still names the configured host.
 8. `GET storage/{path}` traversal attempt → denied.
 9. Rate limiting on the two panel logins.

## 3. Persona B — public API consumer

 1. Enable the `api` module in the back office; `api/v1/status` → 200.
 2. `api/v1/docs` → 200, lists every endpoint and ability.
 3. Create an API client and token in the back office; copy the plaintext once.
 4. Each of the 12 data endpoints with a full-ability token → 200 + correct shape.
 5. Each endpoint with a token lacking that ability → 403.
 6. Country-scoped token → results narrowed; a foreign record by id → 404.
 7. Revoked token → 401.
 8. Rate limit exceeded → 429 with `Retry-After`.
 9. `?locale=` / `Accept-Language` on list endpoints → translated payload.
10. Pagination params, `per_page` beyond the cap, negative page → no 500.
11. Consumption recorded in analytics only for requests that cleared every gate.

## 4. Persona C — object owner cabinet

### C1. Access

 1. `GET /cabinet` anonymous → login page.
 2. Login with wrong password → error, throttle after N attempts.
 3. Login as a suspended owner → refused.
 4. Login as owner → lands on the tenant dashboard.
 5. `GET /cabinet/settings` → 200 (profile, password change, locale).
 6. Change password → old password required, new password enforced, re-login works.
 7. Change cabinet language → whole panel switches.
 8. Logout → session gone, back button does not restore.

### C2. Tenant scope (the highest-risk area)

 1. Owner A opens the cabinet of another owner's object → refused, not silently switched.
 2. Every child resource id belonging to another owner (room, price, service,
    news, promotion, review, photo) opened directly by URL → refused.
 3. Tenant switcher lists exactly the owner's own objects.
 4. An owner whose only object is pending moderation can still reach the cabinet.

### C3. Dashboard

 1. Shows: object name, active package, placement end date, tier, catalog
    position, views today/week/month/all-time, messenger clicks, site clicks,
    availability status.
 2. Quick buttons: edit object, bump object, add photos, add news, add promotion —
    each lands on the right screen for the right tenant.

### C4. Object editing

 1. Open the edit form; every tab renders.
 2. Basic info: name, short and full description, type, country/region/district/
    city/resort, address, coordinates. Save → moderation request created when the
    portal is in review mode; published directly in auto mode.
 3. Contacts: add a row of each of the 8 channel types, set the type selector,
    save, verify the generated deep link.
 4. Remove a contact row; reorder rows.
 5. Availability toggle — one click, no full form, badge appears/disappears on
    the public page within the cache window; a history row is written.
 6. Validation: blank required field, bad coordinates, malformed URL, overlong text.
 7. Unsaved-changes guard when navigating away.
 8. Save an edit while a previous revision is still pending moderation.

### C5. Photos, rooms, prices, services

  1. Photos: upload, delete, reorder, set the main photo, caption in each locale.
  2. Upload an oversized file and a non-image → rejected with a clear message.
  3. Rooms: create with name, description, capacity, area, amenities, price;
     edit; delete; reorder; attach room photos.
  4. Prices: per-night, per-room, per-person, seasonal, currency, validity window.
  5. Services: toggle amenities on and off; verify they drive the catalog filter.

### C6. Content

  1. News: create → title, excerpt, body, image, publish date; save; verify the
     moderation path; verify it appears on the object page and in the portal feed.
  2. News: edit, unpublish, delete.
  3. Promotions: create with start/end dates and image; verify auto-archival past
     the end date; verify it appears on the object page and the promotions page.
  4. Reviews: list; reply to a review; report a review; confirm delete is absent.

### C7. Statistics and bump

  1. Statistics page: views, photo views, per-channel click counts, site clicks.
  2. Bump page: current position, tier, last bump date, next free bump date,
     remaining bumps, the bump button.
  3. Press bump → object moves to first *within its own tier* only; a
     `bump_events` row is written; the cooldown now blocks a second press.
  4. Press bump during cooldown → refused with the reason.
  5. Bump with no bump allowance on the package → button absent *and* the action
     refused server-side.

### C8. Notifications

  1. Notification list; unread badge; mark as read.
  2. Moderation-result notification arrives after staff approves or rejects.
  3. Placement-expiry notifications at 30/14/7/3/0 days.

## 5. Persona D — scoped staff roles

For **each** of the nine staff roles:

  1. Log in, complete MFA enrolment, land on the dashboard.
  2. Navigation shows only that role's sections — and every hidden section is
     also refused when its URL is typed directly (the policy, not the menu).
  3. Each visible resource: list, filter, sort, search, open a record, save an
     edit, run every row action, run every bulk action.
  4. Each forbidden resource: index, create, edit, and delete by direct URL → 403.
  5. Export actions: allowed only for roles holding the export permission;
     financial and personal-data exports gated separately.
  6. `region_administrator`: sees only objects/owners inside its territory
     subtree; a record outside → 403 on show and on edit.
  7. `country_administrator`: same, on the country axis.
  8. `moderator`: opens the queue, sees old vs new values with the diff
     highlighted, approves, rejects with a reason, requests changes, comments,
     reassigns; the published record is untouched until approval.
  9. `technical_support`: impersonate an owner → banner shows, `support-mode/exit`
     returns to the staff session, both events land in the action journal.

## 6. Persona E — chief administrator

  1. Dashboard widgets: every counter in the TZ list, financial summary, quick
     actions.
  2. Objects: create from scratch through every tab; publish; hide; archive;
     restore; hard delete; duplicate; transfer to another owner; move to another
     territory; change tier; pin a position; unpin.
  3. Bulk actions on objects: publish, hide, archive, change package, assign a
     label, move territory, notify owners, export — each with a confirmation
     naming the record count.
  4. Owners: create, edit, attach/detach objects, suspend, restore, send a
     password-reset link, impersonate.
  5. Geography: create a territory at each level, reparent it, warn when objects
     are attached, edit translations and SEO, deactivate.
  6. Object types: create, edit, set the amenity groups, icons, SEO.
  7. Placement tiers and packages: rename, recolour, change the badge, price,
     duration, bump policy, activate/deactivate.
  8. Placement expiry: run the sweep job, confirm the configured post-expiry
     action fires and the object drops to standard tier.
  9. Banners: create with desktop *and* mobile creatives, targeting by country/
     region/city/resort/category/locale, slot, schedule, order, preview,
     activate; verify impressions and clicks accumulate.
 10. News, promotions, articles, categories, tags: full CRUD, scheduling,
     translations, SEO, archive.
 11. Moderation queue: filters by country, object, owner, change type, date.
 12. Financial records: create, edit, statuses, reports by country/period/package.
 13. Notifications: personal and mass broadcast, by country/resort/package.
 14. SEO: metadata templates, redirects, error pages, health dashboard warnings.
 15. Import: upload XLSX/CSV, map columns, dry-run validation, error report,
     duplicate detection, confirm, report.
 16. Export: every exportable resource in CSV/XLSX/JSON, respecting filters.
 17. Action journal: filters, old/new value view, export; confirm a staff account
     cannot delete its own entries.
 18. Modules: toggle each feature module; verify the public surface follows.
 19. Portal settings, interface catalog editor, translation report.
 20. Backup administration: run a manual backup, read the log, check integrity.
 21. Backup restore: the double-confirmation gate; refuse without the extra grant.
 22. Analytics report and commerce reports: period selection, export.

## 7. Cross-cutting probes

| Probe | What it must show |
| --- | --- |
| Tier precedence | On every listing surface, no lower tier outranks a higher one |
| Soft delete | Deleted objects/news/promotions/banners/articles vanish publicly, remain for chief admin, restore works |
| Moderation scope | Public queries hide pending records; owner and staff queries do not |
| Locale completeness | Every rendered string translated in `en` and `ru`; no raw translation key |
| Query budget | 30 queries or fewer per request; no N+1 on catalog, territory, object |
| Cache invalidation | An availability toggle or an approved edit reaches the public page |
| Panel path config | No hardcoded `/admin` anywhere in redirects, robots, CSP, sitemap |
| Authorization | Every hidden UI affordance is also refused server-side |
| Audit | Every mutating staff action lands in the action journal |

## 8. Execution order

1. Seed a realistic dataset (all registries + volume content across 3 countries).
2. Run the automated suite as a regression baseline.
3. Persona A (HTTP + browser).
4. Persona B (API).
5. Persona C (cabinet, harness-driven).
6. Personas D and E (back office, harness-driven, MFA enrolled programmatically).
7. Cross-cutting probes.
8. Write findings and improvement specs.
