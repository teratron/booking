# TZ conformance matrix

The deep QA plan (`qa-deep-plan.md`) mapped against the client's requirements
document `.drafts/booking.md`, section by section. Every row was checked against
a running instance or the code, not inferred from the specifications layer.

## Legend

| Mark | Meaning |
| --- | --- |
| ✅ | Implemented and exercised |
| ⚠️ | Implemented but defective — see the finding id in `qa-deep-findings.md` |
| ◐ | Partial: the mechanism exists, the launch configuration or one surface does not |
| ❌ | Not implemented |
| ➕ | Implemented beyond what the TZ asks — deliberate improvement, keep |
| ↔ | Deliberate divergence from the TZ — flagged for the client's decision |

## 1. Portal structure and public pages

| TZ | Requirement | Status | Note |
| --- | --- | --- | --- |
| §2, §4 | Catalog sections per object type | ✅ | Type registry is data; eight types seeded, more addable from the panel |
| §2, §4 | «Контакты» page | ❌ | F-18 — the footer entry renders as inert text |
| §2, §4 | «О проекте» page | ❌ | F-18 — same |
| §4 | Privacy policy, terms of use | ✅ | Both locales |
| §5 | Home: header, logo, language, country, menu, search | ✅ | |
| §5 | Home: popular destinations, categories, best objects, newest, promotions, news, articles, popular cities, map, partners, footer | ✅ | Sixteen blocks, each omitted when empty rather than rendered hollow |
| §5, §101 | Administrator-curated home blocks | ❌ | `home_block_selections` table exists with no consumer (F-24); blocks are derived by query only |
| §6 | Catalog: filters, sorting, pagination, map, card view | ✅ | Livewire component, bookmarkable query string |
| §6 | Catalog: list/grid toggle | ❌ | Single card layout only |
| §5 | Card: photo, name, settlement, short description, key services, rating, views, "details", contact buttons | ✅ | All present |
| §24.1 | Territory landing at every level with description, catalogs, news, promotions, map, SEO text | ✅ | Depth-independent; typed catalog within a territory also implemented |

## 2. Object page (§6, §7, §71)

| TZ | Requirement | Status | Note |
| --- | --- | --- | --- |
| §7 | Cover, gallery | ✅ | Grid gallery; no lightbox / prev-next |
| §7 | Video | ❌ | No media collection, no field |
| §75 | Panoramic images | ❌ | |
| §7 | Description, information | ✅ | |
| §7 | Contacts, messengers | ✅ | Eight channel types, click-tracked deep links, all verified |
| §7 | Map | ❌ | **F-17** — the one composition block missing |
| §7 | Rooms, prices, services, infrastructure | ✅ | |
| §7 | House rules, meals | ◐ | No dedicated fields; achievable as type-declared attributes (§109 mechanism, see below), not configured |
| §71 | Location description, additional information | ◐ | Same — `object_translations` carries name, short and full description, SEO only |
| §7 | Nearby objects, similar objects | ✅ | Both blocks render |
| §6 | Nearby attractions | ◐ | "Nearby" is same-territory objects of any type; attractions are an object type, so the block covers them incidentally rather than as a dedicated section |
| §7 | Object promotions, object news | ✅ | |
| §7 | Reviews | ⚠️ | Renders, but **F-11**: nothing can create one |
| §71 | Per-language name, descriptions, SEO, URL | ✅ | Separate translation tables, per-locale slug |
| §71 | Keywords | ↔ | No `keywords` field anywhere. Meta keywords have been ignored by search engines since 2009; omitting them is the right call, but it is a divergence and should be confirmed with the client |

## 3. Search and map (§10, §11, §14, §15)

| TZ | Requirement | Status | Note |
| --- | --- | --- | --- |
| §10, §14 | Search by region, settlement, name, category, services, price, rating | ✅ | |
| §10, §14 | Distance to sea / to centre, and "many other parameters" | ◐ | Supported through the type-declared attribute schema (`attribute_schema` + `AttributeFilterResolver`), which is editable in the panel — but no such attribute is seeded, so no distance filter exists at launch |
| §10 | Parking, pool, SPA, pets, playground, Wi-Fi, meals, holiday type | ✅ | Amenity registry, filterable flag per amenity |
| §11, §15 | Map with objects, filtering, clusters | ✅ | MapLibre, client-side clustering, pin card |
| §11, §15 | Route building | ❌ | No directions control |
| §15 | Map works out of the box | ⚠️ | **F-16** — tile key is settings-only; `.env` variables are read by nothing, so every map 403s on a fresh install |

## 4. Owner cabinet (§8, §29–§43)

| TZ | Requirement | Status | Note |
| --- | --- | --- | --- |
| §30 | Menu: home, objects, edit, photos, rooms, prices, services, promotions, news, reviews, statistics, bump, settings, logout | ✅ | All present; prices live inside rooms/object rather than as a separate menu item |
| §29 | Owner sees only their own objects | ✅ | Nine cross-owner direct-URL probes all refused |
| §30 | Switching between several objects | ✅ | Tenant switcher |
| §31 | Dashboard: package, expiry, tier, position, views by period, messenger clicks, site clicks, availability | ✅ | |
| §31 | Quick buttons | ✅ | |
| §32 | Edit basic info, geography, contacts | ⚠️ | **F-04** — the page 500s for any object that has a contact |
| §32, §27 | Availability toggle, one click | ⚠️ | Toggle and history correct; **F-08** — the public page does not refresh |
| §33 | Photos: upload, delete, reorder, main photo, captions | ✅ | |
| §34 | Rooms with amenities and photos | ✅ | |
| §35 | Prices: per night, per room, per person, seasonal | ✅ | Polymorphic price table with currency and validity window |
| §36 | Services | ✅ | |
| §37 | News with moderation path | ✅ | |
| §38 | Promotions with auto-archival | ⚠️ | **F-13** — archival is job-driven only; an elapsed promotion stays public up to 24 h |
| §39 | Reviews: reply, report, no deletion | ⚠️ | Correct — but **F-11** means the list is always empty |
| §40 | Statistics: page views, photo views, per-channel clicks, site clicks | ⚠️ | **F-19** photo views are page views × photo count; favourites counter always 0 (**F-24**) |
| §41 | Bump with cooldown, position, next free date | ✅ | |
| §42 | Change password, cabinet language, email, notification preferences | ✅ | |
| §43 | Notifications with history | ✅ | |
| §29.1 | Owner registration | ↔ | No self-registration: accounts are created by staff. Documented as deliberate in `CabinetPanelProvider`. Worth confirming with the client, since §29.1 says «после регистрации» |
| §100 | Password recovery by email | ◐ | Staff can send a reset link from the Owners screen and the broker is wired, but no panel exposes a "forgot password" link and no reset route is registered — the emailed link has nowhere to land |

## 5. Moderation (§44–§49)

| TZ | Requirement | Status | Note |
| --- | --- | --- | --- |
| §44 | Two modes, configurable per portal / country / category / owner / object | ✅ | `moderation_settings` carries all four scope levels |
| §45 | What is moderated | ✅ | Section-scoped moderation requests |
| §46 | Queue with date, owner, object, section, status | ✅ | |
| §47 | Old vs new, highlighted diff, approve / reject / request revision / comment | ✅ | Review screen renders and all four decisions exist |
| §47 | Partial acceptance (optional in the TZ) | ❌ | Not implemented — the TZ marks it optional |
| §119 | Reassign a request to another moderator | ✅ | `assigned_moderator_id` |
| §48, §53, §91 | Change history with old/new values, IP, user | ✅ | `owen-it/laravel-auditing` + a dedicated action journal |
| §49 | Owner notified of the decision, with a reason on rejection | ✅ | |

## 6. Monetization and advertising (§25–§26, §54–§63, §111–§115)

| TZ | Requirement | Status | Note |
| --- | --- | --- | --- |
| §25 | Four placement tiers, admin-renameable, colour and badge configurable | ✅ | |
| §25.2 | Tier precedence on every listing surface, per territory and category | ✅ | Verified: no lower tier outranks a higher one |
| §25.3, §26 | Bump within the tier only, with history | ✅ | |
| §25.4 | Assign tier, expiry, pin a position, unpin, restore automatic order | ✅ | |
| §55, §60, §111 | Package management: price, currency, validity, bump policy, activation | ⚠️ | **F-05** — create and edit both 500 |
| §113 | Promotion labels: text, colours, icon, position, schedule | ⚠️ | Implemented; **F-15** — one out-of-range position value takes down the list page |
| §113 | Card preview before saving | ❌ | No preview |
| §57, §83, §115 | Banners: targeting by country/region/city/resort/category/locale, slot, schedule, order | ✅ | |
| §24.2, §83, §115 | Separate mobile creative | ⚠️ | **F-21** — uploadable, never served |
| §115 | Impressions, clicks, CTR visible to the administrator | ⚠️ | **F-07** — permanently 0 |
| §115 | Banner preview | ❌ | |
| §61, §122 | Financial records: object, service, package, amount, currency, dates, method, document, status | ✅ | Six statuses as the TZ lists them |
| §122 | Reports by country, period, package, staff member | ✅ | Commerce reports page |
| §62 | Expiry notifications at 30/14/7/3/0 days and after | ✅ | Scheduled sweep + notification templates |
| §123 | Post-expiry action configurable (demote / hide / review) | ✅ | `expiry_action_override` per placement |
| §64 | Online payment | ↔ | A `payment` feature module is seeded with no implementation behind it. The TZ lists online payment as future scope (§64), so this is scaffolding, not a gap — but the empty module should be documented or removed (F-24) |

## 7. Back office (§99–§134)

| TZ | Requirement | Status | Note |
| --- | --- | --- | --- |
| §100 | Separate protected address, 2FA, login/IP/device logging, brute-force protection | ✅ | Panel path is configuration; Filament native MFA enforced for privileged roles |
| §100 | Password recovery | ◐ | See §5 above — no reset route |
| §101 | Dashboard counters and financial summary | ✅ | |
| §101 | Quick action buttons | ◐ | The dashboard shows widgets; the nine quick-action buttons the TZ enumerates are not rendered as such |
| §102 | Menu: Objects | ✅ | |
| §102 | Owners | ✅ | |
| §102 | Geography | ✅ | |
| §102 | Object categories | ✅ | Including the per-category field schema (§109) |
| §102, §110, §134 | **Services and characteristics** | ❌ | No amenity or amenity-group resource anywhere in the panel. Amenities can only be *selected*, never created, renamed, translated, re-iconed, regrouped, or marked filterable. §134 lists this among the first-stage must-haves |
| §102 | **Rooms and prices** (staff-side) | ❌ | Rooms and prices are cabinet-only; staff cannot edit them for an owner |
| §102 | Placement packages | ⚠️ | F-05 |
| §102 | Positions and bumps | ✅ | |
| §102 | Banners | ✅ | |
| §102 | News, promotions, articles | ✅ | Plus categories and tags |
| §102, §120 | **Reviews** | ❌ | No resource; combined with F-11 the module cannot function |
| §102 | Moderation | ✅ | |
| §102, §121 | **Users and roles** | ❌ | Only owners are manageable. No staff-account screen, no role screen, no permission screen, no scope-grant screen. This is what makes F-01 unrecoverable without SQL |
| §102 | Finance | ✅ | |
| §102, §125 | Statistics | ✅ | Analytics report with export |
| §102, §126 | SEO | ✅ | Templates, redirects, error pages, health dashboard with warnings |
| §102, §124 | Notifications | ✅ | Personal and broadcast |
| §102, §127–§128 | Import and export | ✅ | XLSX/CSV/JSON, filter-aware export, duplicate detection with per-signal scoring (name, contact, address), validation before commit |
| §102, §129 | Action journal | ✅ | Filters, old/new values, export, protected from ordinary administrators |
| §102, §130 | System settings | ✅ | Registry-driven, critical settings gated |
| §102, §131 | Backups | ⚠️ | **F-14** — the status page 500s when the destination is unreachable |
| §103 | Object list columns and filters | ✅ | Including the four quick filters §28 names |
| §104 | Object form tabs; draft / publish / hide / return for revision / archive / restore / duplicate / transfer owner | ✅ | All present, plus merge — see ➕ below |
| §105 | Bulk actions with a confirmation naming the count | ✅ | Ten bulk actions |
| §106 | Owner management incl. impersonation, logged | ✅ | Impersonation and exit both journalled |
| §107 | Geography management, warn when objects are attached | ✅ | |
| §108 | Language management, interface translations, untranslated report | ✅ | |
| §112 | Position management | ✅ | |
| §114 | Availability status administration incl. mass reset and staleness period | ✅ | |
| §121 | Nine staff roles with per-country / per-region / per-category rights | ⚠️ | The roles exist and scoping works; **F-09** — the permission map does not match the role names or duties |
| §132 | Filter persistence, breadcrumbs, unsaved-changes warning, bulk actions, tablet support | ✅ | Panel-wide, not per resource |
| §133 | Confirmation on destructive actions | ✅ | |

## 8. Cross-cutting (§13, §16–§21, §94–§97)

| TZ | Requirement | Status | Note |
| --- | --- | --- | --- |
| §13, §92 | URL, title, description, canonical, robots, OG, sitemap, breadcrumbs, structured data | ⚠️ | Implemented; **F-02** hreflang points at 404s, **F-03** news/blog/promotion URLs are numeric ids not slugs, **F-20** OG gaps |
| §13 | Keywords | ↔ | Deliberately absent — see §2 above |
| §16 | Translations for countries, cities, categories, articles, news, objects, menu, SEO, system messages | ✅ | Separate tables per entity; interface catalogs per locale |
| §16 | Per-language page address for every entity | ◐ | Objects, territories, object types ✅; article categories carry a single global slug, not one per locale |
| §1.4 | Five languages | ↔ | `en` and `ru` active, `ro`/`uk`/`ka` seeded inactive — the agreed launch scope, addable from the panel |
| §17 | SSL, 2FA, logs, injection and XSS protection, backups | ✅ | |
| §130 | CAPTCHA | ◐ | Three settings keys exist; nothing reads them and no form validates a CAPTCHA (F-16) |
| §18 | Caching, CDN, image optimisation, lazy load, Redis, queues | ⚠️ | All present; **F-08** — the invalidation half does not work |
| §19 | REST API with documentation, auth, tokens | ⚠️ | Twelve endpoints, scoped tokens, revocation and expiry all correct; **F-06** two endpoints 500, **F-12** the module gate leaks |
| §20 | Fully responsive | ✅ | No horizontal overflow at 390 px |
| §21, §65–§93 | Schema: tables, relations, indexes, constraints | ✅ | With **F-15** (one missing check constraint) and **F-24** (five unused tables) |
| §95 | Soft delete with archive and restore | ✅ | |
| §96, §127–§128 | Import/export XLSX, CSV, JSON with pre-validation | ✅ | |
| §97, §131 | Daily database backup, separate media backup, retention, manual run, log, integrity check, restore procedure | ✅ | Restore is administrator-gated and rehearsed in `docs/` |
| §12, §10 | Comments on news | ❌ | No comment model or UI |
| §11 | Blog: categories, tags, author, related articles | ✅ | Plus related objects and territories |

## 9. Implemented beyond the TZ — keep

| ➕ | What | Why it is worth keeping |
| --- | --- | --- |
| §109 | Per-category **field schema** (`attribute_schema`), editable in the panel, driving both the object form and the catalog filters | The TZ asks for "different fields for different categories"; this delivers it as administrator-editable data instead of code, which is what makes meals, house rules, cuisine, average bill, and distance-to-sea addable without a developer |
| §104 | Object **merge** | Not requested; the natural companion to the import duplicate detection the TZ does ask for (§127) |
| §127 | Duplicate detection with **per-signal scoring** and named signals | The TZ asks for detection by name/phone/site/address; the implementation explains *why* each candidate matched rather than raising one opaque flag |
| §63 | Feature **modules** with per-scope enable/disable and dependency/conflict declarations | The TZ asks for services to be switchable (§63); this generalises it |
| §100 | Non-guessable, configurable panel paths | Not requested; removes the credential-stuffing surface a literal `/admin` invites |
| §121 | Query-level **scope narrowing** in addition to per-record policies | The TZ asks for country/region-limited rights; narrowing the list query as well means a limited administrator never learns another country's record counts |
| §94 | N+1 detection failing the test run, and measured performance budgets | The TZ asks for speed; this makes it enforceable rather than aspirational |

## 10. Divergences to confirm with the client

Not defects — decisions that differ from a literal reading of the TZ and should
be signed off rather than silently carried:

1. **No owner self-registration** (§29.1). Accounts are created by staff. This
   is a deliberate anti-spam and data-quality choice, and it matches the
   revenue model (paid placement sold by the portal), but the TZ's wording
   implies self-service.
2. **No meta keywords** (§13, §71). Ignored by every major search engine.
3. **Launch languages are `en` + `ru`** (§1.4 lists five). Agreed scope; the
   remaining three are seeded inactive and addable from the panel.
4. **Booking-shaped schema exists** (`reservations`, `room_availabilities`,
   `booking_settings`) behind a `booking` feature module, while §1 and §76 say
   the portal is explicitly not a booking system and keeps no occupancy
   calendar. Either declare it as reserved future scope in the documentation or
   drop it (F-24).
5. **Prices are not a separate cabinet menu item** (§30) — they are edited on
   the object and on each room, which is where the owner is already working.

## 11. What §134 calls first-stage, and where it stands

The TZ's own launch checklist, scored:

| §134 item | Status |
| --- | --- |
| Login and rights distribution | ⚠️ F-01, F-09 |
| Object management | ⚠️ F-04 |
| Owner management | ✅ |
| Geography | ✅ |
| Categories **and services** | ❌ services registry has no back-office screen |
| Placement packages | ⚠️ F-05 |
| Positions, borders, badges | ⚠️ F-15 |
| Availability status | ⚠️ F-08 |
| Moderation | ✅ |
| Banners | ⚠️ F-07, F-21 |
| News and promotions | ✅ |
| Deadline control | ✅ |
| Basic statistics | ⚠️ F-19 |
| SEO | ⚠️ F-02, F-03 |
| Import and export | ✅ |
| Action journal | ✅ |
