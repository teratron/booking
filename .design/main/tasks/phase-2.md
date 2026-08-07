---
phase: 2
name: "Back Office Core"
status: Todo
subsystem: "app/Filament/Admin, app/Services, app/Policies"
requires: ["phase-1"]
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 2 Tasks — Back Office Core

**Phase:** 2
**Status:** Todo
**Strategic Goal:** A staff panel a portal can actually be operated from — objects,
owners, geography, taxonomy, translations, moderation, and the action journal — built
on Phase 1's scoped authorization rather than around it.

## Track Ordering

Phase 2 is the first phase with genuine parallelism, but not four-way:

```plaintext
A (panel foundation)  →  B (objects, owners, availability)
                      →  C (geography, taxonomy, translations)   ∥
                      →  D (moderation, journal, archive)
                      →  T (validation)
```

Track A is a hard prerequisite for all three: every resource in B, C, and D plugs into
the shared resource contract from `T-2A02`, and a resource written before that contract
exists has to be rewritten to adopt it. Once A lands, B, C, and D touch disjoint
directories under `app/Filament/Admin/Resources/` and run concurrently. Effective
parallel degree is three.

One cross-track edge is real and is scheduled rather than discovered: `T-2D01` (mode
resolution and the change-request pipeline) must land before `T-2B02`'s
return-for-revision action, which enqueues into it. Sequence `T-2D01` first within
Track D and the edge never becomes a stall.

## Atomic Checklist

### Track A — Panel Foundation

- [x] [T-2A01] Admin panel shell — protected path, sign-in hardening, permission-filtered navigation
- [x] [T-2A02] Shared resource contract — policy binding, persisted filters, unsaved-change guard, counted bulk confirmation
- [x] [T-2A03] Portal settings registry and the settings screen
- [x] [T-2A04] Module management screen with per-scope toggles and blast-radius confirmation
- [x] [T-2A05] Dashboard — state counters, operational widgets, permission-gated finance block

### Track B — Objects, Owners & Availability

- [x] [T-2B01] Object list — columns, filters, and search across every stated dimension
- [x] [T-2B02] Object form — tabbed editor and the full lifecycle action set
- [ ] [T-2B03] Object bulk operations behind counted confirmations
- [ ] [T-2B04] Owner management — accounts, object attachment, access control
- [ ] [T-2B05] Support-mode impersonation, journalled without exception
- [ ] [T-2B06] Availability administration — override, history, revert
- [ ] [T-2B07] Availability staleness — cadence, quick filters, bulk reset, optional auto-reset

### Track C — Geography, Taxonomy & Translations

- [x] [T-2C01] Territory administration with guarded reparenting
- [x] [T-2C02] Object type registry administration
- [ ] [T-2C03] Language registry and the interface catalog editor
- [ ] [T-2C04] Translation management and the untranslated-material report

### Track D — Moderation & Governance

- [x] [T-2D01] Moderation mode resolution and the change-request pipeline
- [ ] [T-2D02] Moderation queue — listing, filtering, assignment
- [ ] [T-2D03] Side-by-side review and the decision set
- [ ] [T-2D04] Action journal — search, filter, before/after, export
- [ ] [T-2D05] Archive — restore, transfer, permanent deletion

### Track T — Validation

- [ ] [T-2T01] Panel authorization matrix — every resource denies out of scope
- [ ] [T-2T02] Moderation invariants — a rejected edit cannot touch the published record
- [ ] [T-2T03] Journal completeness and append-only enforcement
- [ ] [T-2T04] Panel query budget under seeded volume

## Detailed Tracking

### [T-2A01] Admin panel shell — protected path, sign-in hardening, permission-filtered navigation

- **Spec:** l1-back-office.md §2, §5.1, §6.5; l1-moderation-governance.md §3.2
- **Status:** Done `[Bootstrap]`
- **Assignment:** Agent
- **Verify:** `docker compose exec app php artisan route:list --path=admin` lists the configured protected path, not `/admin`, and no route resolves at the old path. `docker compose exec app ./vendor/bin/pest --filter=AdminPanelShell` proves: an account with no permissions sees zero navigation groups; an account holding only `object.view` sees the Objects group and no other; six consecutive failed sign-ins lock the account and write a failure record; a successful sign-in, a sign-out, and a lockout each produce a journal row carrying IP and user agent; the chief administrator's second factor is required and other roles' is optional.
- **Changes:** Panel moved to a configurable path (`portal-admin` by default) via a new `config/booking.php`; panel admission became the `admin_panel_access` / `cabinet_access` permission pair rather than a role-name check; five navigation groups declared. Sign-in journalling added as three auto-discovered listeners over `Login`/`Logout`/`Failed` plus a subclassed login page for the lockout the framework announces with no event, all writing through a new `AuditJournal` service into the same `audits` table Eloquent auditing uses. Second factor delivered by the toolkit's native multi-factor support over the existing `two_factor_secrets` table, made mandatory for configured roles only by a middleware that subclasses the toolkit's panel-wide one.
- **Evidence:** `pest tests/Feature/Admin/AdminPanelShellTest.php` · exit 0 · 10 passed (30 assertions) — protected path resolves and `/admin` 404s; admission, four journal event classes, encrypted 2FA round-trip, and role-conditional enforcement all asserted · no errors. `composer lint && analyse && test && test:coverage` · exit 0 · Pint 188 files clean; PHPStan level 8 zero errors; Pest 126 passed; coverage 90.4%. Falsification: emptying `two_factor.required_for_roles` made the chief-administrator assertion fail at its exact line, then was restored.
- **Handoff:** T-2A02 — the resource contract registers into this panel's navigation.
- **Notes:** Navigation groups follow the §5.1 section list in `[TZ]` §134 priority order; sections whose backing resource arrives in Phase 3 or later are simply absent, not stubbed. Sign-in records are **not** model mutations, so `owen-it/laravel-auditing` will not capture them by observing Eloquent — write them explicitly against the same `audits` table so §5.4's journal stays single rather than split in two. Second factor uses the `two_factor_secrets` table and the panel toolkit's own multi-factor implementation. Brute-force protection is a rate limiter on the login route plus an account lockout; the lockout is the part that must be journalled.
  - *Amendment (2026-08-07):* `pragmarx/google2fa-laravel` — present when this note was written — was removed. The toolkit's `AppAuthentication` provider depends on `pragmarx/google2fa` directly and never called the Laravel wrapper; confirmed unused before removal and the full suite passed unchanged after. See `CLAUDE.md`'s Required Packages table.
- **Execution findings:** Three decisions were forced by the ground rather than the plan. (1) The toolkit models "second factor required" as a panel-wide boolean baked into route middleware at registration time, where no user is in scope; requiring it of one role meant subclassing that middleware so the decision moves into the request. (2) A failed sign-in against an address matching no account is deliberately left out of the journal: entries are keyed to a target record, and admitting one would let an unauthenticated visitor append rows to the one table no role is permitted to prune. Those go to the application log. (3) The navigation-filtering half of this task's verification cannot be asserted yet — no resource exists to be filtered. It moves to `T-2T01`, which generates the matrix from the resource registry; asserting it here against zero resources would have been an assertion that cannot fail.
- **Pre-existing gate failure surfaced:** `composer unused` was already failing at the previous commit — `laravel/sanctum` and `pragmarx/google2fa-laravel` are both carried and neither is called, so the phase before this one was closed against a gate that did not actually pass end to end. Both are named in the project's required package set, so neither was removed unilaterally; a `composer-unused.php` now records each one's reason and the point at which the reason expires. The second is a genuine removal candidate: the toolkit's own multi-factor implementation supersedes it.

### [T-2A02] Shared resource contract — policy binding, persisted filters, unsaved-change guard, counted bulk confirmation

- **Spec:** l1-back-office.md §3.1, §3.2, §6.1, §6.2; l1-moderation-governance.md §3.4, §5.6
- **Status:** Done `[Bootstrap]`
- **Assignment:** Agent
- **Changes:** `ScopedResource` base class narrows every list query through a new `ResourceQueryScoper` service before it runs, supplies the table persistence defaults, and lifts the moderation global scope for the panel; `CountedBulkAction` makes the counted confirmation a type rather than a convention; `ScopeAuthorizer` gained `constraintFor()`, which resolves an actor's grants of one permission into a set of reachable country, territory-subtree, and category ids in a single recursive CTE. Both panels now run with strict authorization and unsaved-change alerts.
- **Evidence:** `pest tests/Feature/Admin/ResourceContractTest.php` · exit 0 · 11 passed (18 assertions) — each scope kind narrows correctly against a two-country, three-level, two-category fixture; a resource carrying no scope axis reaches nothing under a bounded grant; a policy-less resource throws instead of allowing · no errors. `composer lint && analyse && test && test:coverage && unused` · exit 0 · PHPStan level 8 zero errors; Pest 138 passed; coverage 93.5%.
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=ResourceContract` proves, against a throwaway resource built on the contract: the list query is narrowed by the actor's scope without the resource declaring anything scope-aware itself; a bulk action refuses to execute without a confirmation whose text contains the exact affected record count; a table filter set on one request is still applied after signing out and back in; navigating away from a dirty form raises the guard. `docker compose exec app php artisan about` plus a grep proves no resource class references the `DB` facade — the architecture test from Phase 1 already fails the suite if one does.
- **Handoff:** Every resource in Tracks B, C, and D. Nothing in those tracks starts before this lands.
- **Notes:** This is the task that decides whether the panel is twenty-four sections or twenty-four rewrites. Scope narrowing delegates to Phase 1's `ScopeAuthorizer`/`ScopedPolicy`; the resource base class supplies the query narrowing and the policy binding so an author cannot forget either. `Model::shouldBeStrict()` is on from Phase 1, which means every table column reaching through a relation must be eager-loaded explicitly — Filament plus translatable plus media is precisely the shape that produces N+1, and here it throws rather than degrading quietly. Permissions register as Filament resource policies, never as inline `visible()` closures: a hidden control is a usability affordance and never an access control.
- **Execution findings:** Three defaults in the toolkit run the wrong way for this project and were inverted here rather than per resource. (1) A resource with no policy — or a policy missing the method being checked — is **permitted** by default; strict authorization turns that into an exception at the first request, which is the only way the omission surfaces before an administrator finds a section they should not have. (2) The moderation global scope, correct for public pages, hides pending and rejected rows from the only people who can act on them; the panel lifts it, and a test asserts the same rejected object is invisible to a plain model query and visible to the panel. (3) Narrowing has to happen in the query, not in the policy: a policy refuses a record but leaves it counted, so an unnarrowed list discloses how many records another country holds even when every row refuses to open. Both halves are kept — the query narrows the list, the policy guards the record — because a record reachable by URL but absent from the list is exactly the gap that appears with only one.
- **Deferred within the phase:** the counted confirmation's copy is asserted here only as "the confirmation cannot be omitted"; the count itself needs a mounted action over a real selection and is asserted in `T-2B03`. Filter persistence across a sign-out is asserted through the real object list in `T-2B01`; what is asserted here is that the contract sets it without the resource asking.

### [T-2A03] Portal settings registry and the settings screen

- **Spec:** l1-back-office.md §3.3, §5.6; l1-platform-foundation.md §3
- **Status:** Done `[Bootstrap]`
- **Assignment:** Agent
- **Changes:** Thirty-three settings declared across ten groups in a `SettingsRegistry`, each with a type, a default, and a critical flag; `SettingsRepository` resolves them from the flat `key`/`jsonb` table with type coercion, caches the whole set until a write drops it, journals each change against its previous value, and refuses a critical write from anyone but the chief administrator by raising `CriticalSettingException`. A settings page renders whatever the registry declares, grouped into tabs, with critical fields disabled for accounts that may not write them.
- **Evidence:** `pest tests/Feature/Admin/PortalSettingsTest.php` · exit 0 · 10 passed (61 assertions) — every declared setting falls back to its own default from an empty table, an undeclared key throws rather than returning null, a string written to an integer setting reads back as an integer, and a critical write is refused at the service with the stored value unchanged · no errors. `composer analyse && test` · exit 0 · PHPStan level 8 zero errors; Pest 148 passed.
- **Design decisions:** Languages and countries are deliberately **not** settings. Both are registries with their own tables, translations, and editing screens; pointing at them from here would create a second source of truth for the same data. Critical setting values are redacted in the journal — the journal is read by more accounts than may write the setting, so a secret recorded there has moved rather than been protected. The cache is dropped on write rather than expiring on a timer: a setting that takes effect in five minutes is a setting an administrator changes twice.
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PortalSettings` proves every key named in the specification's runtime-configuration list resolves through the settings service with a typed default, that a write is journalled, and that a key flagged critical is rejected for a non-chief administrator at the service boundary — not only hidden in the form. `docker compose exec app php artisan tinker --execute="dump(app(\App\Services\Settings\SettingsRepository::class)->get('moderation.default_mode'));"` returns the seeded default on a database with an empty `settings` table.
- **Handoff:** T-2A04, T-2B07, T-2D01 — module scopes, the staleness cadence, and the default moderation mode are all settings reads.
- **Notes:** The `settings` table is a flat `key`/`jsonb` pair, which is deliberate and also a trap: reading it raw scatters string keys through the codebase and loses typing. A typed repository with declared defaults is the interface; the table is an implementation detail behind it. Values are cached in Redis and the cache is invalidated on write, because settings are read on nearly every request.

### [T-2A04] Module management screen with per-scope toggles and blast-radius confirmation

- **Spec:** l1-feature-modules.md §5.3, §5.5, §5.6, §5.7; l1-back-office.md §5.6
- **Status:** Done `[Bootstrap]`
- **Assignment:** Agent
- **Changes:** `Module`, `ModuleTranslation`, and `ModuleSetting` models; a `ModuleAdministrator` service that reads effective state through the existing resolver rather than re-deriving the ladder, counts a toggle's blast radius against the object table, refuses an enable that would leave a dependency off or a conflict on, and journals every change with the affected count; a list-only Filament resource with portal-wide and per-country toggles, each behind a confirmation naming that count.
- **Evidence:** `pest tests/Feature/Admin/ModuleAdministrationTest.php` · exit 0 · 6 passed (15 assertions) — blast radius counted per scope, an enable with an unmet dependency refused with nothing written, country scope resolving more specifically than portal, a reservation row byte-identical across a disable/re-enable cycle, and the registry invisible to a country-bounded grant · no errors. `composer analyse && test` · exit 0 · PHPStan level 8 zero errors; Pest 154 passed.
- **Execution findings:** Two guards fired during this task and both were correct. Strict mode threw on the translations relation the moment the list rendered — the N+1 shape the phase notes predicted — which moved eager loading into the shared contract as a declared `$eagerLoad` list rather than a per-resource override. The architecture suite then rejected a `DB` facade call used to populate a country select; swapped to the Eloquent model, which is what the rule intends. There is no create or edit screen: a module exists because code implements it, so a row an administrator could add would register a capability nothing delivers.
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=ModuleAdministration` proves: the screen lists every `modules` row with its effective state resolved at each scope of the ladder; toggling at portal or country scope is refused unless the confirmation is acknowledged, and the confirmation text contains the count of affected objects; a toggle writes a journal entry; enabling a module whose dependency is disabled is refused with the unmet dependency named; disabling and re-enabling the booking module leaves its `reservations` rows byte-identical.
- **Handoff:** T-2T01 — the module gate is part of the authorization matrix.
- **Notes:** Resolution reuses Phase 1's `ModuleResolver` ladder (object → owner → category → country → portal → default); this screen must not re-implement it. The blast-radius count is a real query against the affected scope, not an estimate — "enabling booking for Ukraine affects 412 objects" is the required form and a wrong number is worse than none. The re-enablement guarantee is what makes the dormant booking module defensible, so it is verified here rather than assumed.

### [T-2A05] Dashboard — state counters, operational widgets, permission-gated finance block

- **Spec:** l1-back-office.md §5.3
- **Status:** Done `[Bootstrap]`
- **Assignment:** Agent
- **Changes:** `DashboardMetrics` service resolving object counts by state, work awaiting review, objects reporting vacancies, and registry sizes — every object figure narrowed through the same scoper the lists use, cached for sixty seconds. Two widgets: a portal overview gated on `object.view`, and a finance block gated on `financial_access` whose queries never run for an account without it. Both render inline rather than deferred.
- **Evidence:** `pest tests/Feature/Admin/AdminDashboardTest.php` · exit 0 · 4 passed (13 assertions) — a Moldova-scoped actor counts 3 of 4 objects, the pending-review figure is 1 while the plain model query sees 0, the finance labels are absent from the response body without the grant, and the whole page resolves in ≤30 queries · no errors. `composer analyse && test && test:coverage && unused` · exit 0 · PHPStan level 8 zero errors; Pest 158 passed; coverage 91.1%; no unused packages.
- **Design decisions:** Widgets are non-lazy. Deferred loading buys nothing for cached aggregates, and a widget that arrives after the page cannot be reasoned about from the response — including by the assertion that the finance block is absent rather than styled away. Campaign spend and paid bumps are **not** shown as zeroes pending the commerce phase: a zero meaning "not built" is indistinguishable from a zero meaning "none", and only the second is information.
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=AdminDashboard` proves each counter named in the specification renders, that counts respect the actor's scope (a Georgia-scoped administrator sees Georgian objects only), that the finance widgets are absent from the response body — not merely hidden by CSS — for an actor without the finance permission, and that the whole dashboard resolves within the phase query budget. Quick actions are asserted by route, not by label.
- **Handoff:** Phase 3 extends this dashboard with commerce widgets; the finance block's permission gate is the seam it plugs into.
- **Notes:** Counters over the seeded volume are aggregate queries and must be cached with a short TTL rather than computed per page load; the dashboard is the panel's most frequently hit screen. Widgets whose data source arrives in a later phase (active campaigns, paid bumps) are not stubbed with zeroes — a zero that means "not built yet" is indistinguishable from a zero that means "none", and the second is information.

### [T-2B01] Object list — columns, filters, and search across every stated dimension

- **Spec:** l1-back-office.md §5.4 (`[TZ]` §103); l1-object-catalog.md §3.1
- **Status:** Done `[Bootstrap]` — list only; the form and lifecycle actions are `T-2B02`
- **Assignment:** Agent
- **Changes:** `Object_Policy` (the first real policy, bound by attribute rather than left to name-guessing) with permanent deletion restricted to the chief administrator regardless of any delete grant; a `ContactChannel` model; an object resource declaring all three scope axes and its eager-load set, with columns for name, type, country, territory, owner, publication state, moderation state, and availability, filters on each of those, and search across translated name, owner, identifier, and contact value.
- **Evidence:** `pest tests/Feature/Admin/ObjectResourceListTest.php` · exit 0 · 6 passed (15 assertions) — a Moldova-scoped administrator's rendered page contains the Moldovan object and not the Georgian one, the list shows objects the public query returns zero of, an out-of-scope record is refused at the policy when reached by identifier, permanent deletion is refused to a delete-granted non-chief, and a filter set on one component instance still applies to the next · no errors. `composer analyse && test && test:coverage && unused` · exit 0 · PHPStan level 8 zero errors; Pest 164 passed; coverage 91.3%.
- **Closes a deferral:** filter persistence across requests, deferred from `T-2A02`, is asserted here against two separate component instances — the second sees the first's filter because the contract persists it in the session, not because the instance survived.
- **Execution findings:** Adding a real policy broke the contract test that asserted a policy-less resource throws — the probe used the object model, so the assertion started passing for the wrong reason the moment the policy existed. Repointed at a model that genuinely has none. Separately, the coverage gate began exhausting PHP's 128 MB default as the suite grew; the script now raises the limit rather than the suite shrinking. Columns whose data arrives with the commerce phase — card caption, border colour, pinned position, last bump — are absent rather than rendered empty: an empty column asserts "this object has none", which is a different claim from "the portal does not track this yet".
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=ObjectResourceList` proves every column and every filter dimension named in §5.4 is present and functional, that search matches on name, phone, email, and object identifier, that the list renders only the fields the object's type declares, and that a country-scoped administrator's list excludes other countries' objects at the query level. `docker compose exec app ./vendor/bin/pest --group=slow --filter=ObjectResourceList` runs the same list against the seeded 52,800-object volume and asserts the phase query budget.
- **Handoff:** T-2B02, T-2B03, T-2B06, T-2B07 — the form, the bulk actions, and both availability surfaces attach to this list.
- **Notes:** The model is `Object_`, not `Object` — `Object` is a reserved PHP class name. Cover photo, package, position, and moderation status each reach through a relation, so the list query needs explicit eager loading or strict mode throws. Filters persist through the contract from `T-2A02`; this task configures them, it does not build persistence.

### [T-2B02] Object form — tabbed editor and the full lifecycle action set

- **Spec:** l1-back-office.md §5.4 (`[TZ]` §104); l1-moderation-governance.md §3.1, §3.3
- **Status:** Done `[Bootstrap]`
- **Assignment:** Agent
- **Requires:** T-2D01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=ObjectResourceForm` proves every tab named in §5.4 exists and saves, that each lifecycle action (save as draft, publish, hide, return for revision, archive, restore, duplicate, transfer ownership) performs its stated state change and writes a journal entry, that return-for-revision enqueues a moderation request carrying the reason, that transfer of ownership reassigns every dependent record rather than only the foreign key, and that an administrator scoped to one category cannot save an object of another — asserted at the policy, with the request rejected rather than the field hidden.
- **Changes:** `Object_Policy` bound; `ObjectLifecycleService` (save as draft, publish, hide, return-for-revision, archive, restore, duplicate, transfer ownership), each journalled; a six-tab form (core info, geography, SEO, contacts, services, owner & staff) with translated fields reconciled against `object_translations` explicitly in the Create/Edit pages' hooks; server-side scope validation on create (`ValidationException`, not a restricted option list); new `Amenity`/`AmenityGroup`/`Language` models filling gaps the schema already had but no model yet covered.
- **Evidence:** `pest tests/Feature/Admin/ObjectResourceFormTest.php` · exit 0 · 10 passed (45 assertions) — a translated name persists to `object_translations`; publish/hide apply directly with zero moderation requests created; return-for-revision creates a decided request carrying the reason; archive/restore round-trip through soft delete; duplicate copies the descriptive record while leaving a seeded placement behind; ownership transfer moves `owner_id` and removes the outgoing owner's own `object_user` row while leaving another staff member's grant untouched; a category-scoped administrator is 403'd at the edit URL for another category and admitted to their own; a country-scoped administrator's create attempt is rejected with a `country_id` form error and no row written · no errors. Falsification: the `object_user` cleanup query was pointed at a nonexistent column; the test failed with a SQL error rather than passing silently, then the fix was restored. `composer analyse && test && test:coverage && audit && unused` · exit 0 · PHPStan level 8 zero errors; Pest 181 passed; coverage 92.1%; no advisories; no unused packages.
- **Handoff:** T-2B03 (bulk equivalents of these actions), T-2T02 (moderation invariants).
- **Notes:** An administrator's own edits publish directly; moderation governs owner-submitted changes, and conflating the two would put staff work in a queue staff themselves clear. Translated fields render per active language against the existing `object_translations` table — `astrotomic/laravel-translatable` keys on a `locale` string matching `languages.code`, not on a language foreign key. Duplicating an object must not duplicate its placement or its statistics; copy the descriptive record and leave the commercial one to be assigned.
- **Scope boundary, stated rather than absorbed silently:** rooms and prices are §104's own named tabs and are absent here. `l1-back-office.md` §5.1 lists "Rooms & prices" as its own back-office section, distinct from "Objects" — and no task in this phase's decomposition claims it. Building it inside this task would have been scope creep into a section this plan assigns nowhere; flagged for the next planning pass rather than built unilaterally, alongside the `moderation_settings` write-screen gap `T-2D01` already surfaced.
- **Execution findings:** Three implementation-level lessons, each fixed rather than worked around. (1) Two relations on `Object_` (`contactChannels`, and the new `amenities`/`staff`) needed explicit foreign/pivot keys — Laravel's own naming convention guesses `object__id` from the `Object_` class name, the exact documented pitfall this model already carries a note about for its translation relation; the note now covers three relations, not one. (2) Nesting a `Tabs` component inside another `Tabs` component's `Tab` breaks Filament 5's Livewire state wiring (`getLivewireProperty does not exist`); per-language grouping now uses collapsible `Section`s instead, which need no tab-switch state. (3) An action's own data field must not use `->relationship()` — that binding resolves against the current record's relation, not the action's own submitted value, and silently no-ops when used on an action `Select`; the ownership-transfer action's "new owner" field now uses a plain `->options()` closure.

### [T-2B03] Object bulk operations behind counted confirmations

- **Spec:** l1-back-office.md §5.4 (`[TZ]` §105); l1-moderation-governance.md §3.4, §5.6
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=ObjectBulkActions` proves each of the eleven operations in §5.4 executes over a selection, that every one presents a confirmation naming the affected count before executing, that a selection spanning outside the actor's scope is rejected in full rather than partially applied, that each operation writes one journal entry per affected record, and that a bulk run over a thousand-record selection dispatches to the queue rather than executing in the request.
- **Handoff:** T-2T01, T-2T03.
- **Notes:** Partial application is the failure mode to design against: an administrator who selects 200 objects, 40 of which are outside their scope, must get a refusal naming the problem, not 160 silent successes. "Notify owners" and "export the selection" are long-running by nature and belong on the queue with a progress report; the others are fast enough to run inline below the queue threshold.

### [T-2B04] Owner management — accounts, object attachment, access control

- **Spec:** l1-back-office.md §5.5 (`[TZ]` §106)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=OwnerResource` proves the list carries every column named in §5.5 including object count and overdue placements, that an administrator can create an account, edit contacts, attach and detach objects, block and restore access, and send a password-reset link, that a blocked owner's session is terminated and subsequent sign-in refused, and that each of those actions is journalled. Overdue placements are asserted against seeded data with a known expiry, not against a hand-set flag.
- **Handoff:** T-2B05 — impersonation acts on the account this resource manages.
- **Notes:** Owners are `users` rows distinguished by role, not a separate table; the resource scopes its query to the owner role rather than assuming it. Detaching an object leaves it ownerless rather than deleting it — an ownerless object is a real state the portal has to render, and the alternative loses data on a routine administrative action.

### [T-2B05] Support-mode impersonation, journalled without exception

- **Spec:** l1-back-office.md §5.5; l1-moderation-governance.md §3.2
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=Impersonation` proves: entering support mode authenticates the administrator as the target owner in the cabinet panel; a journal entry is written naming both the actor and the target before the session switches; the session carries a visible banner and a return path that restores the original identity; every mutation made while impersonating is journalled against the administrator, not against the owner; impersonation requires its own permission and is refused for an owner-scoped account; and the journal entry is written even when the subsequent session switch fails.
- **Handoff:** T-2T03 — impersonation is one of the journal completeness assertions.
- **Notes:** This is the single most sensitive capability in the panel: it grants an administrator the full authority of another account, which is exactly why the record of it is unconditional. Attributing the impersonated session's mutations to the owner would make the journal actively misleading — worse than absent — so the actor is resolved from the impersonator, not from the authenticated user. The cabinet panel exists from Phase 1 as a shell; its resources arrive in Phase 4, and impersonation into an empty cabinet is still the correct thing to build now, because the journal contract is what is being established.

### [T-2B06] Availability administration — override, history, revert

- **Spec:** l1-availability-status.md §5.1, §5.5 (`[TZ]` §28, §114); l1-moderation-governance.md §3.1
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=AvailabilityAdministration` proves an administrator sees current status, change time, actor, last confirmation time, and the full toggle history for any object; that an override writes both the object columns and an `availability_histories` row with `source: administrator` in one transaction; that revert-to-previous restores the prior value and appends a further history row rather than deleting one; that the write path never enqueues a moderation request; and that a status write invalidates the catalog, territory, and object-page caches.
- **Handoff:** T-2B07, and Phase 4's owner-facing toggle, which shares this service.
- **Notes:** The write path is narrow by design. Routing it through the general object-edit path drags in moderation, validation, and full-object cache invalidation, all three of which this operation must avoid — the availability toggle is the one owner action that bypasses moderation unconditionally, and the administrator's override has to honour the same shape. `last_confirmed_at` is distinct from `changed_at` on purpose: re-affirming an unchanged value must reset the staleness clock, and a single timestamp cannot express that.

### [T-2B07] Availability staleness — cadence, quick filters, bulk reset, optional auto-reset

- **Spec:** l1-availability-status.md §5.4, §5.5 (`[TZ]` §27.3, §114)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=AvailabilityStaleness` proves the confirmation cadence is a settings-backed value and not a constant, that the object list offers each quick filter named in §5.5, that bulk reset presents a counted confirmation and writes one history row per affected object, that auto-reset is off in a freshly seeded database, and that when enabled its rows carry `source: automatic`. `docker compose exec app php artisan schedule:list` shows the staleness sweep registered as a scheduled job and no sweep code is reachable from a web route.
- **Handoff:** Phase 3's notification track sends the owner reminder this sweep raises.
- **Notes:** Auto-reset is genuinely double-edged — resetting a stale `unavailable` back to `available` manufactures exactly the false vacancy claim the feature exists to prevent — so it ships off, is enabled per portal, and records its own provenance so a resulting badge is never mistaken for an owner's assertion. Scheduled work runs as a job dispatched by the scheduler, never during a web request.

### [T-2C01] Territory administration with guarded reparenting

- **Spec:** l1-geography.md §5.5 (`[TZ]` §107); l1-moderation-governance.md §5.6
- **Status:** Done `[Bootstrap]`
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=TerritoryAdministration` proves an administrator can create and edit a territory at any depth setting every field named in §5.5 including per-language names and slugs; that reparenting a node with attached objects or descendants is refused until a confirmation naming both counts is acknowledged; that the confirmation counts are computed over the whole subtree, not the immediate children; that reparenting rewrites the denormalized country and the cached slug path for every descendant in one transaction; that a cycle-forming reparent is rejected; and that deactivating a territory removes it from navigation while leaving its objects attached and reachable by their own URLs.
- **Changes:** New `moderation_settings`-sibling design decision reversed here too — a `full_slug_path` column added to `territory_translations` (nullable, cached per language) rather than a computed-on-read value, per the spec's own caching note. `TerritoryAdministrator` service: `blastRadius()` (whole-subtree counts), `reparent()` (cycle guard, denormalized-country cascade, slug-path recompute, one transaction), `recomputeSlugPaths()`. `TerritoryPolicy` bound; a new `geography` permission resource added to `PermissionSeeder` (the seeder's own docblock authorizes exactly this: growing the set when a new gate is written). Territory resource scoped on country **and** on the territory's own id — a region-scoped grant reaches every descendant through the same subtree-expansion `ScopeAuthorizer` already provides.
- **Evidence:** `pest tests/Feature/Admin/TerritoryAdministrationTest.php` · exit 0 · 9 passed (28 assertions) — blast radius counts 2 descendants and 2 objects across a three-level subtree (not just immediate children); a reparent onto a grandchild or onto itself is refused; a cross-country reparent rewrites `country_id` on every descendant; slug paths recompute and contain every ancestor segment; the reparent journal entry carries both affected counts; deactivating a territory leaves its objects attached; a region-scoped grant reaches a resort two levels down and not a sibling region; the list renders a country-root territory (no parent) without crashing. Falsification: the cycle guard's descendant check was replaced with a hardcoded `false`; rather than merely failing an assertion, it produced a genuine infinite-recursion hang in Postgres (a real cycle in the adjacency-list CTE), which is stronger evidence of necessity than a clean test failure — the hung backend was terminated and the fix restored. `composer analyse && test && test:coverage && audit && unused` · exit 0 · PHPStan level 8 zero errors; Pest 190 passed; coverage 91.5%; no advisories; no unused packages. `migrate:fresh --seed` applies cleanly.
- **Handoff:** Phase 5's territory landing pages consume the slug paths this task maintains.
- **Notes:** Subtree counts and descendant rewrites go through `staudenmeir/laravel-adjacency-list` recursive CTEs; a per-node walk at this depth is the wrong cost and the seeded 6,270-territory tree will show it. `country` is denormalized onto every node deliberately, which means reparenting across a country boundary has to repair it — the field is immutable in practice, not immutable in fact.
- **Execution findings:** Two platform quirks worth keeping in mind for later resource tasks. (1) Larastan types every `BelongsTo` relation as non-nullable regardless of the foreign key's own nullability — it flags a legitimate nullsafe operator (`$territory->parent?->name`, where `parent_id` genuinely can be null for a country root) as "unnecessary." Silencing it by removing the nullsafe would have been a real regression; the fix checks the foreign key column directly instead, which Larastan does understand as nullable. (2) `composer test` itself — not only `composer test:coverage` — began exhausting PHP's 128 MB default as the suite passed 190 tests; the `test`, `test:arch`, and `test:slow` scripts now all raise the limit the same way `test:coverage` already did.

### [T-2C02] Object type registry administration

- **Spec:** l1-object-catalog.md §3.1 (`[TZ]` §69, §109)
- **Status:** Done `[Bootstrap]`
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=ObjectTypeRegistry` proves an administrator can create a type, nest it under a parent, assign its icon, choose its applicable field set and amenity groups, and set its SEO defaults, all without a code change; that a type's declared field set actually governs which fields the object form in `T-2B02` renders; that deactivating a type hides it from the public catalog without detaching its objects; and that no code path branches on a hard-coded type key.
- **Changes:** `ObjectType` gains an `amenityGroups()` many-to-many relation and is policy-bound (`ObjectTypePolicy`: view/viewAny gated by `settings.view`, create/update/delete by `settings.edit` — the same portal-wide configuration gate the module registry already uses, since a type registry is configuration rather than a per-object action). New `ObjectTypeResource` (list/form/pages) with a `parent_id` self-reference, an `amenityGroups` multi-select, and an `attribute_schema` repeater (key, type, per-language label) that becomes the JSONB column the object form reads. Retroactively wires `T-2B02`'s object form with a dynamic `typeAttributesTab()`: a `Get $get`-driven schema closure resolves the selected type's `attribute_schema` and renders a `Toggle` or `TextInput` per declared field straight onto the object's own `attributes` JSONB column — no reconciliation step, since it is a cast column and not a relation.
- **Evidence:** `pest tests/Feature/Admin/ObjectTypeRegistryTest.php` · exit 0 · 5 passed (30 assertions) — a nested type with icon, amenity groups, and attribute schema is created in one save; a type's declared fields — and only those fields — render on the object form's dynamic tab; a type invented after this code was written (never special-cased) renders correctly from its own schema alone; deactivating a type removes it from the public catalog while its objects stay attached; an unrestricted grant reaches every type and a scoped one is refused. Falsification: `ObjectForm`'s attribute-schema read was replaced with a hardcoded empty array; the two tests asserting rendered fields failed at their exact assertions (caught independently by a concurrent coverage run before the isolated re-run confirmed it), then the fix was restored and the full 5 tests passed again. `composer quality` (fix, analyse, test, test:coverage, audit, unused) · exit 0 · Pint 260 files clean; PHPStan level 8 zero errors; Pest 195 passed (587 assertions) twice (plain run and coverage run); coverage 92.2%; no security advisories; no unused packages beyond the documented `laravel/sanctum` filter. `migrate:fresh --seed` applies cleanly.
- **Handoff:** T-2B02's object form (already wired) and any later catalog rendering that needs to branch on type-declared fields rather than a hard-coded list.
- **Notes:** The registry being data is not decoration: an accommodation type exposes rooms, prices, and availability while a dining type exposes cuisine, average cheque, and opening hours, and the difference has to survive an administrator adding a type nobody anticipated. The architecture test that forbids hard-coded language and country counts extends naturally here; add the type-key case to it rather than trusting review.
- **Execution findings:** Cross-track integration, not a plan gap: `T-2B02` shipped before this task and left its type-dependent tab unbuilt on purpose, since the schema it reads did not exist yet; completing `T-2C02` closes that loop rather than opening a new one, and the object form needed no structural change — only its already-`Get`-driven tab closure gained a real data source in place of an empty list. One containment-test violation caught and fixed here: an early docblock for `typeAttributesTab()` cited a specification filename directly; rewritten in plain language, suite re-verified green.

### [T-2C03] Language registry and the interface catalog editor

- **Spec:** l1-localization.md §5.1, §5.4, §5.6 (`[TZ]` §67, §108)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=LanguageAdministration` proves an administrator can toggle a language, set exactly one primary, and reorder the switcher; that deactivating a language hides it from the switcher and from alternate links while leaving its translation rows intact so reactivation is lossless; that an interface catalog entry edited in the panel changes the rendered string on the next request with no deployment and no cache clear command; that a key absent from a catalog falls back to the primary language rather than rendering a raw key; and that activating a third language produces a usable site immediately, resolving entirely through fallback.
- **Handoff:** T-2C04 — the untranslated report reads the same catalogs.
- **Notes:** File-based catalogs under `resources/lang` cannot satisfy "editable without a deployment", so a database-backed loader overlays the files: files supply the shipped defaults, database rows supply the administrator's overrides, and the loader merges with the database winning. Launch activates English and Russian only; the remaining three are a data operation, and this task is what makes that claim true. UI strings always fall back and never hide — a missing button is worse than a button in the wrong language.

### [T-2C04] Translation management and the untranslated-material report

- **Spec:** l1-localization.md §5.5 (`[TZ]` §108, §126)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=TranslationManagement` proves the report lists untranslated material across every translatable entity class rather than a hard-coded subset; that the object list can be filtered to objects lacking a translation in a chosen language; that copy-from-primary populates a target language as an editable starting point and marks the result untranslated rather than translated; that a single language version of an entity can be published independently of the others; and that completeness per entity and per language is reported as a number the SEO warning in Phase 6 can consume.
- **Handoff:** Phase 6's SEO track raises the "translation missing" warning from this metric.
- **Notes:** "Across all entity classes" is the requirement that decides the implementation: enumerating translatable models by convention (the trait they carry) keeps the report correct when Phase 3 adds articles, news, and promotions; enumerating them by hand guarantees it is wrong by the end of Phase 3. Copy-from-primary marking its output untranslated is what keeps the completeness metric honest — otherwise the first bulk copy reports one hundred percent coverage of text nobody has translated.

### [T-2D01] Moderation mode resolution and the change-request pipeline

- **Spec:** l1-moderation-governance.md §3.1, §5.1, §5.2
- **Status:** Done `[Bootstrap]`
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=ModerationResolution` proves the mode resolves down the full ladder (object → owner → category → country → portal default) with most-specific winning, that each rung is exercised by its own case; that a change to a type not on the moderated list publishes immediately regardless of mode; that an enqueued request stores the published state and the proposed state as independent snapshots on the request itself; that the availability toggle never enqueues; and that the moderated-change-type list is configuration read at runtime rather than a constant.
- **Changes:** New `moderation_settings` table (country/category/owner/object rows only — no portal row, since the portal default already exists as `moderation.default_mode`); `ModerationRequest` model over the existing `moderation_requests` table; `ModerationScopeContext` value object; `ModerationModeResolver` walking the four-rung ladder; `ModerationPipeline::submit()` returning an exclusive `ModerationOutcome` (publish now, or an enqueued request) so a caller cannot accidentally do both.
- **Evidence:** `pest tests/Feature/Admin/ModerationResolutionTest.php` · exit 0 · 7 passed (24 assertions) — each of the four rungs exercised independently with most-specific winning, an unmoderated change type publishes immediately regardless of a `review` override, an enqueued request's snapshot stays unchanged after the live record is edited afterward, and the moderated-types check reads the settings repository at call time (asserted by changing the setting mid-test and observing the outcome flip) · no errors. Falsification: the snapshot write was deliberately pointed at `$proposedData` for both columns; both snapshot-integrity tests failed at their exact assertions, then the fix was restored. `composer analyse && test && test:coverage && unused` · exit 0 · PHPStan level 8 zero errors; Pest 171 passed; coverage 91.6%.
- **Handoff:** T-2D02, T-2D03, and T-2B02's return-for-revision action. This is Track D's first task.
- **Notes:** The previous-state snapshot lives on the request, not as a reference to the live record. If the live record changes between submission and review, a reference-based diff shows the moderator a comparison that never existed — and the moderator would have no way to tell.
- **Execution findings:** Two departures from what this task's own notes originally predicted, and one plan gap surfaced rather than papered over.
  1. *Resolver sharing, revised.* The original note said resolution logic would be "one service used by both" moderation and feature modules. Building both concretely showed they diverge enough that literal sharing would force an abstraction to fit: `module_settings` carries a `module_id` foreign key moderation has no equivalent of, `ModuleResolver` additionally walks a dependency graph after resolving its own state, and `module_settings` carries a nullable-reference portal row that `moderation_settings` deliberately does not (the portal default already exists once, as the `moderation.default_mode` setting — see finding 2). Kept as two small resolvers rather than one generalized one; the shared *shape* is documented in each class's docblock so the parallel is visible without being load-bearing code.
  2. *No portal row in `moderation_settings`.* Modelling the ladder's fifth rung as a `moderation_settings` row with a null reference (mirroring `module_settings`) would have given the portal default two independent sources of truth — that row and the `moderation.default_mode` setting from `T-2A03`. The table holds only the four scopes that override the setting.
  3. *No admin write screen exists for this ladder in the current phase plan.* T-2A04 paired `ModuleAdministrator` with a screen in the same task; no equivalent task is decomposed for `moderation_settings`. This task's own Verify line only demands the resolver and pipeline work correctly, so the ladder is tested via direct fixture rows — legitimate and already the pattern `ModuleAdministrationTest`/`AuthorizationMatrixTest` use for their own scope tables. The write points most likely belong distributed across the resources that own each scope — the object form (`T-2B02`) for the object rung, owner management (`T-2B04`) for the owner rung, the object type registry (`T-2C02`) for the category rung — rather than one more standalone settings screen; none of those tasks currently name it either, so it is flagged here rather than invented unilaterally in a task that did not ask for it. A country-level write point has no owning resource in this phase's plan at all.
  4. *Caught by the architecture suite, not by review.* An early draft of two docblocks cited a specification filename directly, which the containment test exists to catch — it failed with the exact two offending files named, both were rewritten to state the same rationale in plain language, and the suite went green. Recorded because the guard did its job as designed, not as an incident.

### [T-2D02] Moderation queue — listing, filtering, assignment

- **Spec:** l1-moderation-governance.md §5.2 (`[TZ]` §46, §119)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=ModerationQueue` proves the queue shows change date, owner, object, section, change summary, and status per entry; that it filters by country, object, owner, change type, and date; that a moderator may reassign an entry to a colleague and the reassignment is journalled; that a country-scoped moderator sees only their country's entries; and that the queue's default ordering surfaces the oldest pending request first.
- **Handoff:** T-2D03 — opening an entry from this queue is the review screen's entry point.
- **Notes:** Oldest-first is the ordering that prevents a queue from developing a permanently unreviewed tail; any recency-first default quietly starves the entries that have waited longest. Scope filtering happens in the query, not in the view, for the same reason it does everywhere else in this panel.

### [T-2D03] Side-by-side review and the decision set

- **Spec:** l1-moderation-governance.md §5.3 (`[TZ]` §47, §49); l1-moderation-governance.md §2
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=ModerationReview` proves the screen renders published and proposed values side by side with changed values marked and before/after images where media changed; that approve applies the proposed data, publishes without a second manual step, and journals; that reject leaves the published record byte-identical and requires a reason that reaches the owner; that request-revision returns the change to the owner in an editable state with the comment attached; that partial acceptance applies only the selected fields and returns the remainder; and that partial acceptance is refused when its portal setting is off.
- **Handoff:** T-2T02 — the rejection invariant is the phase's most important assertion.
- **Notes:** Partial acceptance is marked optional in the client specification and modelled as field-level selection in the spec. Resolved here as: implemented, behind a portal setting, defaulting off. The snapshot model already supports it, so gating it costs a setting read rather than a redesign, and shipping it on by default would make every moderator decision a field-by-field one. A rejected edit being unable to damage a live page is the practical reason moderation acts over changes rather than over records — it is verified, not assumed.

### [T-2D04] Action journal — search, filter, before/after, export

- **Spec:** l1-moderation-governance.md §3.2, §5.4 (`[TZ]` §53, §91, §94, §129)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=ActionJournal` proves the journal renders actor, action, target, previous value, new value, timestamp, IP, device, and outcome; that it searches and filters across each of those; that reading it requires its own permission distinct from every other panel permission; that export respects the active filter set and requires the export permission; and that retention and archival of old entries is an administrator-configured setting with a scheduled job that honours it. `docker compose exec app php artisan schedule:list` shows the archival job registered.
- **Handoff:** T-2T03 — completeness and append-only enforcement are asserted there.
- **Notes:** One journal, not two. A moderation decision is one kind of journalled mutation, and splitting the moderation log from the action log leaves two partial histories where the requirement asks for one. Journal entries are written in the same transaction as the mutation they describe — a journal written afterwards has gaps exactly where failures occurred, which are the cases it most needs to record. Append-only is already enforced by the Phase 1 database trigger; this task must not add a UI-level guard and call it enforcement. The journal is the highest-volume table after statistics, so archival is scoped in now rather than after the first slow query.

### [T-2D05] Archive — restore, transfer, permanent deletion

- **Spec:** l1-moderation-governance.md §3.3, §5.5 (`[TZ]` §51, §75, §95, §131)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=ArchiveGovernance` proves a soft-deleted object, user, news item, promotion, or banner disappears from public queries, appears in the archive, and is restorable with its media intact; that an archived object can be transferred to another owner; that permanent deletion is refused for every role but the chief administrator; that it requires re-authentication rather than a confirmation click; that it is journalled; and that soft-delete filtering is applied by a global scope so no individual query can forget it. One case asserts the negative directly: a raw query without the scope returns the archived row, proving the scope is doing the work.
- **Handoff:** T-2T01, T-2T03.
- **Notes:** Soft-delete filtering belongs in the shared query layer because a single forgotten filter republishes archived content and that failure is silent — nothing errors, the page simply shows what it should not. Media survives its parent until final deletion, so restoring an archived object must restore a complete record rather than a text skeleton. Re-authentication is a stronger gate than confirmation and the specification asks for it by name on the highest-impact targets.

### [T-2T01] Panel authorization matrix — every resource denies out of scope

- **Goal:** Prove that Phase 1's scoped authorization actually governs Phase 2's panel, on every resource, through the request rather than through the interface.
- **Spec:** l1-back-office.md §3.1; l1-feature-modules.md §5.5; l2-tech-stack.md §5.6
- **Status:** Todo
- **Assignment:** Agent
- **Method:** `docker compose exec app ./vendor/bin/pest --filter=PanelAuthorizationMatrix` iterates every registered admin resource against every scope kind (`none`, `country`, `territory`, `category`) and every permission verb, asserting for each combination that an out-of-scope request is refused at the policy. Refusal is asserted by issuing the request directly at the resource action URL with navigation bypassed entirely — a test that only checks a hidden menu item proves nothing. One case covers each: a Georgia-scoped administrator against a Moldovan object, a region-scoped administrator against a city outside their subtree, a category-scoped administrator against another category, and an actor whose module is disabled against that module's resource.
- **Verify:** The matrix is generated from the resource registry rather than enumerated by hand, so a resource added in a later phase is covered without editing the test; a deliberately unpoliced scratch resource makes the suite fail, then is removed.

### [T-2T02] Moderation invariants — a rejected edit cannot touch the published record

- **Goal:** Verify the three moderation invariants that the design rests on and that a plausible implementation can violate without any visible symptom.
- **Spec:** l1-moderation-governance.md §3.1, §5.3
- **Status:** Todo
- **Assignment:** Agent
- **Method:** `docker compose exec app ./vendor/bin/pest --filter=ModerationInvariants` asserts: a pending request never mutates the published record, checked by hashing the published row before submission and after rejection; approval publishes with no second manual step; the availability toggle bypasses moderation in every mode including the strictest; and a request whose target is edited by an administrator between submission and review still shows the moderator the snapshot that was submitted against.
- **Verify:** Each invariant is proven capable of failing before it is proven to hold — the implementation is temporarily broken in the way the invariant forbids, the assertion fails citing the case, and the break is reverted.

### [T-2T03] Journal completeness and append-only enforcement

- **Goal:** Verify that every event class the specification enumerates actually reaches the journal, and that no ordinary administrator can alter it.
- **Spec:** l1-moderation-governance.md §3.2, §5.4 (`[TZ]` §53, §129)
- **Status:** Todo
- **Assignment:** Agent
- **Method:** `docker compose exec app ./vendor/bin/pest --filter=JournalCompleteness` performs one action per enumerated event class — sign-in, object creation, object edit, owner change, availability toggle, content publication, moderation decision, data export, settings change, module toggle, deletion, restoration, impersonation — and asserts each produced exactly one journal row with a populated previous and new value. A second case attempts `UPDATE` and `DELETE` against `audits` as the application's own database role and asserts both are refused by the Phase 1 trigger, not by application code.
- **Verify:** The event class list in the test is asserted against the specification's enumeration so an unimplemented class fails loudly rather than being silently omitted; package changes, position changes, and bumps belong to Phase 3 and are marked skipped with that reason rather than dropped from the list.

### [T-2T04] Panel query budget under seeded volume

- **Goal:** Prove the panel meets its query budget against realistic volume rather than against fixtures, before Phase 3 adds more surface to it.
- **Spec:** l2-tech-stack.md §5.9
- **Status:** Todo
- **Assignment:** Agent
- **Method:** `docker compose exec app ./vendor/bin/pest --group=slow --filter=PanelQueryBudget` loads each resource list, the dashboard, the moderation queue, and the action journal against the seeded 52,800 objects and 6,270 territories, asserting each stays within thirty queries and that strict mode raises no lazy-loading violation.
- **Verify:** Measured inside the container against a non-bind-mounted copy — the Windows bind mount measures filesystem cost rather than application cost and is not a valid benchmark host. Findings are recorded as numbers in the task's evidence line, not as a pass/fail claim.

## Standing Constraints

- Every action is permission-checked server-side against Phase 1's scoped resolution.
  Hiding a Filament action is a usability affordance and never an access control.
- Permissions are registered as Filament resource policies, never as inline `visible()`
  closures.
- Moderation acts on **changes**, not on records: a pending revision never overwrites
  the published version, so a rejected edit cannot damage a live page.
- Bulk actions require a confirmation naming the affected record count.
- Impersonation is journalled without exception — it grants an administrator the full
  authority of another account.
- Every user-facing string goes through a translation key. No literal copy in a Filament
  label, table column, or form field.
- Business logic lives in `app/Services/`. A Filament resource that reaches for the `DB`
  facade fails the architecture suite.
- `composer quality` runs after every meaningful change, not at task boundaries.

## Planning Audit

Findings from the adversarial review of this decomposition, recorded rather than
resolved silently.

**Optimism bias.** Twenty-five tasks is the largest phase in the plan, and the estimate
most likely to be wrong is `T-2A02`. It reads like plumbing and is in fact the task that
decides whether the remaining twenty-two resources are configuration or construction.
Its scope was deliberately widened to include the unsaved-change guard and the counted
bulk confirmation — both look like per-resource concerns and both become twenty-four
copies of a subtle bug if they are. The second underestimation risk is `T-2C03`: a
database-backed translation loader overlaying file catalogs is a small amount of code in
an unusually load-bearing position, and it is the only task in the phase that changes how
every string in the application resolves.

**Hidden dependencies.** The tracks are genuinely independent only after `T-2A02`, which
is why the ordering is stated as a hard gate rather than a suggestion. One further edge
crosses tracks — `T-2D01` before `T-2B02` — and it is scheduled first within Track D so
it never blocks. A third dependency is on Phase 1 rather than within Phase 2: every
availability, module, and scope task consumes a Phase 1 service, and none of them
re-implements it. The seam most likely to be violated is the module ladder, because
re-deriving it inside the administration screen looks locally simpler than reading it.

**Cascade risk.** `T-2A02` is the single highest-cascade task in the phase: it is
upstream of every resource in three tracks, and a contract that has to change after ten
resources adopt it is a ten-file rewrite. `T-2D01` is second — the queue, the review
screen, and one object action all consume its output, and its snapshot semantics cannot
be retrofitted once requests exist in the table. `T-2A01` has the least code and a
disproportionate blast radius in the other direction: the sign-in journal cannot be
backfilled, so a panel shipped without it loses the records permanently.

**Plan stability.** Every specification behind this phase is still `RFC`, and two of the
set's open questions land here rather than in Phase 1. Region-scoped permission
transitivity governs `T-2T01`'s territory case directly — the matrix is written against
the transitive reading, and the explicit-per-node alternative would change both the test
and `role_scopes`. Partial acceptance of a moderated change set is resolved inside
`T-2D03` as implemented-but-off; if the client settles it as whole-request-only, the
setting is removed and nothing else moves.

## Instruction Quality Review

The task units above were reviewed as executor-facing instructions. Verdict:
**PASS-WITH-REWRITES**, applied inline. Three classes of rewrite were made.

Verify lines that named a test without naming what it must prove were expanded into
their assertion sets — `T-2A01`, `T-2B03`, `T-2C01`, and `T-2D05` each carried a
verifiable-sounding command whose failure condition was undefined. Four tasks asserted
absence through the interface and were rewritten to assert it through the request, since
"the button is hidden" and "the action is refused" are different claims and only the
second is the requirement. And the three validation tasks were given falsifiability
clauses — an assertion never observed failing is an assertion of unknown value, which
Phase 1 established as the standard when it deliberately broke each architecture rule
before trusting it.
