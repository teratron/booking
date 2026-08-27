---
phase: 11
name: "Revenue & Administration Surfaces"
status: Done
subsystem: "app/Filament/Admin, app/Services/Placement, app/Services/Authorization, app/Policies, app/Services/Advertising, app/Http/Controllers/Public, app/Livewire/Public, database/seeders, resources/views, tests/Feature"
requires: ["phase-2", "phase-3", "phase-5", "phase-10"]
provides:
  - "Staff account and role administration screen (create, deactivate, grant/revoke roles with the last-chief-administrator guard)"
  - "Placement-grant admin surface on the object edit screen and objects table (grant, pin, unpin, bulk grant, history)"
  - "Geographic banner rendering on the catalog and object pages, and territory context on the home page"
  - "Server-side map-pin clustering and a response cap with a truncation signal"
  - "Realistic-volume demo fixtures for contact channels, banners, editorial content, reviews, and the audit trail"
key_files:
  created:
    - "app/Filament/Admin/Resources/Staff/**"
    - "app/Services/Staff/StaffAccountService.php"
    - "app/Models/RoleScope.php"
    - "app/Policies/StaffPolicy.php"
    - "app/Policies/RoleScopePolicy.php"
    - "app/Policies/PlacementHistoryPolicy.php"
    - "app/Services/Authorization/RoleGrantPresenter.php"
    - "app/Filament/Admin/Resources/Objects/RelationManagers/PlacementHistoryRelationManager.php"
    - "app/Support/Catalog/MapCluster.php"
    - "app/Support/Catalog/MapPinsResult.php"
    - "database/migrations/2026_08_27_090000_add_revocation_columns_to_role_scopes_table.php"
  modified:
    - "app/Services/Placement/PlacementLifecycleService.php"
    - "app/Services/Authorization/RoleGrantService.php"
    - "app/Services/Objects/ObjectBulkActionService.php"
    - "app/Services/Catalog/CatalogQueryService.php"
    - "app/Filament/Admin/Resources/Objects/Pages/EditObject.php"
    - "app/Filament/Admin/Resources/Objects/Tables/ObjectsTable.php"
    - "app/Http/Controllers/Public/HomePageController.php"
    - "app/Http/Controllers/Public/ObjectPageController.php"
    - "app/Livewire/Public/CatalogSearch.php"
    - "app/Http/Controllers/Public/MapPinsController.php"
    - "database/seeders/DemoVolumeSeeder.php"
    - "database/seeders/BannerSlotSeeder.php"
patterns_established:
  - "A Filament resource sharing an Eloquent model with another resource (User: Staff vs Owner) overrides getAuthorizationResponse() to dispatch to its own dedicated policy, since Laravel resolves exactly one policy per model class."
  - "owen-it/laravel-auditing's automatic model-event observer gates itself on App::runningInConsole() (config('audit.console'), false by default) — a seeder or other console-only code path that needs a real audit row writes it through AuditJournal directly rather than relying on a plain model save()."
  - "Every relation exposed through a Filament RelationManager needs a registered policy — Filament's strict authorization mode throws LogicException at render time otherwise, not at write time, so the gap surfaces as a page crash rather than a silent bypass."
duration_minutes: null
---

# Stage 11 Tasks — Revenue & Administration Surfaces

**Phase:** 11
**Status:** Done (25/25)
**Strategic Goal:** Build the two back-office surfaces the portal's own business model
depends on and that have never existed in any form — granting a paid placement to an
object, and administering the staff who do it — plus the three narrower gaps found in
the same sweep. The services behind both surfaces were built in earlier phases and are
correct; what is missing is any path a person can reach them by.

## What Makes This Phase Different

**Two of its five tracks close gaps that make delivered capability unreachable, not
gaps that make it wrong.** `PlacementLifecycleService::grant()`, `pin()` and `unpin()`
have no caller anywhere in `app/Filament`; `grant()`'s only production caller is the
expiry sweep, which only ever demotes. `RoleGrantService::grantRole()` has exactly one
caller in the entire application and it is the database seeder. Every unit test for
both services passes. The portal can therefore define a placement package, record that
a payment was received, and connect the two only through direct database access — and
it cannot hire a moderator after it ships at all.

**Track B is scheduled after a specification amendment, not before it.** Staff
administration was a genuine specification gap: `l1-back-office.md` §5.2 defined how a
permission is stored and enforced and never said a person could create one, so a system
whose grants are written only at installation satisfied every word of it. `/magic.spec
main` closed that on 2026-08-26 (v0.2.0 — five new §3.1 invariants and the §5.2
administration surface). Track A is the opposite case and worth stating plainly: the
back-office *screens* were already specified — `l1-back-office.md` §5.3's "assign
package" quick action, §5.4's "package and position" tab, change history, and bulk
"change package", and §5.8's placement entries in the mandatory first release — while
the *act* those screens perform was not, in the spec that owns the domain. The same
amendment pass added it as `l1-placement-monetization.md` §3.6 and §5.6 (v0.2.0).

**One QA fix-specification item is deliberately not scheduled, and this is the record
of why.** The 2026-08-26 sweep proposed enforcing per-package entitlements — promotions
allowed, news allowed, photo caps — at the point an owner uses them. That is not a
missing implementation but an explicitly rejected design: `[TZ]` §25 states outright
that photo, contact, description, service and news counts must **not** depend on the
chosen package, §79 and §111 agree, and `l1-placement-monetization.md` §3.2 and §7
already codified the rejection by name. The schema matches — `PlacementPackage` carries
no such columns and its migration says the absence is deliberate. Building it would
have broken the package-parity invariant this portal's whole commercial model rests on.
Bump eligibility remains the single package-varying capability, and it is already
enforced end to end.

**The whole of Tracks A and B sits inside the declared sensitive zone** — authorization
and policies for B, placement and commerce for A. Neither track's changes qualify as
"ordinary" under the standing autonomous-operation grant, so each needs a person's
review before it travels, regardless of how mechanical an individual task looks. This
is a property of the subject matter, not of the size of any one diff.

## Cross-Track Edges

Two are real and scheduled rather than left to be discovered:

- **`T-11B03` before `T-11A05`.** Track A's grant permission is scopable per
  `l1-placement-monetization.md` §3.6, and it resolves through the same grant records
  Track B is changing. Wiring A's policy against the grant path before B has finished
  giving that path a revocation and a re-bounding means writing the check twice.
- **`T-11B06` before `T-11B02`.** The last-remaining-chief-administrator guard belongs
  in the service, and an edit screen that can deactivate an account must not ship for
  even one commit ahead of the guard that stops it deactivating the last one.

Tracks C, D and E are independent of A/B and of each other.

## Task Checklist

### Track A — Placement Grant Surface

- [x] [T-11A01] Grant a placement package to an object from the object edit screen
- [x] [T-11A02] Manual position: pin, unpin — restore automatic ordering (partial: internal-priority as a distinct action deferred, see Task Detail)
- [x] [T-11A03] Bulk "assign placement package" action on the objects table
- [x] [T-11A04] Placement column and read-only placement history on the objects table
- [x] [T-11A05] Scope the grant permission (partial: cross-tier chief-administrator override deferred, see Task Detail)
- [x] [T-11A06] Validation — a granted package reaches the catalog ordering; an out-of-scope grant is refused

### Track B — Staff Administration

- [x] [T-11B01] Staff resource listing accounts by exclusion of the object-side roles
- [x] [T-11B02] Create, edit, and deactivate a staff account
- [x] [T-11B03] Grant and revoke roles through `RoleGrantService`, recording the revocation
- [x] [T-11B04] Scope picker over the live registries (partial: suspended-grant state renders as a label only, see Task Detail)
- [x] [T-11B05] Read-back of a role's effective permissions and an account's total reach
- [x] [T-11B06] Last-remaining-chief-administrator guard, enforced in the grant service
- [x] [T-11B07] Second-factor reset on a staff account (partial: enrolment/requirement administration deferred, see Task Detail)
- [x] [T-11B08] Validation — a created moderator reaches exactly the screens their role grants

### Track C — Banner Render Completion

- [x] [T-11C01] Render the catalog page's banner slot
- [x] [T-11C02] Render the object page's banner slot
- [x] [T-11C03] Pass territory and category context to the home page's existing slots
- [x] [T-11C04] Validation — a territory-targeted banner reaches its own pages and no others

### Track D — Map Pins Bounding

- [x] [T-11D01] Cluster pins server-side by zoom level
- [x] [T-11D02] Cap individual pins per response and signal truncation to the client
- [x] [T-11D03] Validation — a country-wide viewport stays under a fixed response ceiling at seeded volume

### Track E — Fixture Volume

- [x] [T-11E01] Seed contact channels for a realistic share of objects
- [x] [T-11E02] Seed banners, news, promotions, articles, reviews, and an audit trail at modest volume
- [x] [T-11E03] Validation — the volume-sensitive suites run against the seeded set

### Track F — Closing Gate

- [x] [T-11F01] Full `composer quality` and the non-slow Pest suite, clean, after Tracks A–E close

## Task Detail

> Commands below run inside the application container unless stated otherwise:
> `docker compose exec -T app …`. Host-side `vendor/bin/pest` depends on `.env`'s
> `DB_PORT` matching whatever host port the Postgres container currently publishes,
> which has drifted before; the container path uses the internal network and does not.
> Pest needs `php -d memory_limit=1G` for a full-suite run.

### Track A — Placement Grant Surface

**[T-11A01] Grant a placement package to an object from the object edit screen**

- **Spec:** [l1-placement-monetization.md](../specifications/l1-placement-monetization.md) §3.6, §5.6; [l1-back-office.md](../specifications/l1-back-office.md) §5.4 ("package and position" tab)
- **Status:** Todo
- **Assignment:** Agent — sensitive zone (commerce), needs a person's review before it travels
- **Verify:** Grant a package to an object through the screen, then assert `placement_histories` holds exactly one open row carrying the acting staff member and the comment, the prior row is closed rather than rewritten, and the object's tier changes in `PlacementOrderingService`'s result for its scope.
- **Notes:** The service already does the work — call `grant()` rather than writing the tables. §3.6 requires the act to record actor and comment, and to be available with no payment recorded at all (`granted free of charge` is a real state the dashboard counts, per `[TZ]` §101). Do not build proration or refund arithmetic: §3.6 states the portal records money and does not compute it.

**[T-11A02] Manual position: pin, unpin, adjust internal priority, restore automatic ordering**

- **Spec:** [l1-placement-monetization.md](../specifications/l1-placement-monetization.md) §3.1 (v0.2.0 — "Pinning has an inverse"), §5.6
- **Status:** Todo
- **Assignment:** Agent — sensitive zone (commerce)
- **Verify:** Pin an object within its tier and assert the catalog reflects it; unpin and assert automatic ordering returns; assert each of the four operations writes a journal entry carrying object, scope, actor, previous position and new position.
- **Notes:** `[TZ]` §112 enumerates six operations and the spec now requires all six. `unpin()` currently has no caller anywhere. A pin clears exactly when the object leaves the tier it was pinned within and at no other time (§5.6) — so a package change to a different tier clears it, one extending the same tier does not.

**[T-11A03] Bulk "assign placement package" action on the objects table**

- **Spec:** [l1-back-office.md](../specifications/l1-back-office.md) §5.4 (bulk "change package")
- **Status:** Todo
- **Assignment:** Agent — sensitive zone (commerce)
- **Verify:** Select three objects, assign a package, and assert three separate open history rows exist with the same package and actor; assert the confirmation names the affected record count before the action runs.
- **Notes:** `assign_promotion_label` and `assign_manager` in `ObjectsTable` are the established pattern to follow. Route it through the same service call `T-11A01` uses, not a second write path — §6 note 5 exists because a bulk action reaching the tables directly is exactly how append-only history acquires a hole. `[TZ]` §133 requires the confirmation.

**[T-11A04] Placement column and read-only placement history on the objects table**

- **Spec:** [l1-back-office.md](../specifications/l1-back-office.md) §5.4 (list columns: active package, current position, placement expiry; "change history" tab); [l1-placement-monetization.md](../specifications/l1-placement-monetization.md) §3.6 ("A placement's history is readable from the panel")
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** The objects list shows current tier, position and expiry for an object holding a placement and degrades to an empty cell for one holding none, adding no query per row (assert the list stays inside the 30-query budget at seeded volume).
- **Notes:** The history already exists and is append-only; nothing reads it back. Watch for the N+1 — this is a column over a relation on the portal's largest table.

**[T-11A05] Scope the grant permission; reserve the cross-tier override to the chief administrator**

- **Spec:** [l1-placement-monetization.md](../specifications/l1-placement-monetization.md) §3.6 ("Granting is scopable"), §3.1 (v0.2.0 — the two authorities separated)
- **Status:** Todo
- **Assignment:** Agent — sensitive zone (authorization + commerce)
- **Requires:** `T-11B03`
- **Verify:** A country-scoped administrator grants a package to an object in their own country (allowed) and is refused on another country's object by the policy, not by the action being hidden; a non-chief administrator is refused the cross-tier override specifically while retaining ordinary within-tier pinning.
- **Notes:** §3.1 v0.2.0 corrected an over-narrowing: `[TZ]` §112 gives all six position operations to any administrator, and `[TZ]` §25.2 reserves only the override that lets a lower tier outrank a higher one to the chief administrator. Enforce in the policy — §6 note 6 states that a scoped grant enforced by which button renders is not enforced.

**[T-11A06] Validation — a granted package reaches the catalog ordering; an out-of-scope grant is refused**

- **Goal:** Prove `T-11A01`–`T-11A05` against §3.6 and the tier-ordering contract in §5.2.
- **Method:** Feature test granting a package and asserting catalog order changes for that scope; feature test asserting an out-of-scope grant is refused server-side with the action reachable; feature test asserting a pin survives a same-tier package change and clears on a cross-tier one.
- **Status:** Todo

### Track B — Staff Administration

**[T-11B01] Staff resource listing accounts by exclusion of the object-side roles**

- **Spec:** [l1-back-office.md](../specifications/l1-back-office.md) §5.2 (v0.2.0 — the administration surface), §5.1 ("Users & roles")
- **Status:** Todo
- **Assignment:** Agent — sensitive zone (authorization)
- **Verify:** The staff list shows an account holding any panel role and never shows an `object_owner`; seed a new panel role at runtime and assert it appears in the list without a code change.
- **Notes:** Scope by excluding `object_owner` and `object_staff_member`, not by enumerating the nine panel roles — §6 note 7. §3.1 makes roles data, so a list naming its members cannot show a role an administrator adds later. `OwnerResource` scopes the opposite way and is the shape to mirror, not to extend.

**[T-11B02] Create, edit, and deactivate a staff account**

- **Spec:** [l1-back-office.md](../specifications/l1-back-office.md) §3.1 (v0.2.0 — "Staff accounts are created from the panel"), §5.2
- **Status:** Todo
- **Assignment:** Agent — sensitive zone (authorization)
- **Requires:** `T-11B06`
- **Verify:** Create a staff account through the screen and sign in as it; deactivate it and assert sign-in is refused while its journal entries remain readable.
- **Notes:** Deactivation, never deletion — `[TZ]` §129 requires the journal to outlive the account. The fate of a deactivated moderator's in-flight queue claims is an open question recorded in the spec's §2 and deferred to `l1-moderation-governance`; do not invent an answer here, and do not let the absence block the account state itself.

**[T-11B03] Grant and revoke roles through `RoleGrantService`, recording the revocation**

- **Spec:** [l1-back-office.md](../specifications/l1-back-office.md) §3.1 (v0.2.0 — "A grant is revocable and re-boundable"), §5.2
- **Status:** Todo
- **Assignment:** Agent — sensitive zone (authorization)
- **Verify:** Grant a role and assert both the role assignment and the matching scope row are written in one transaction; revoke it and assert the revocation records actor and time rather than deleting the row; assert `role_scopes` has a working update path.
- **Notes:** `grantRole()` exists and writes both halves — it simply has no caller outside the seeder. `revokeRole()` has no caller at all outside tests, and `role_scopes` has no update or delete path anywhere in the application. Separately, `OwnerAccountService::createAccount()` assigns its role directly instead of going through the service, which already breaks the single-path rule §6 note 6 states — bring it onto the service in this task.

**[T-11B04] Scope picker over the live registries, with suspended-grant behaviour**

- **Spec:** [l1-back-office.md](../specifications/l1-back-office.md) §5.2 (v0.2.0 — the scope picker and grant-target behaviour), §3.1 ("Permissions are scopable")
- **Status:** Todo
- **Assignment:** Agent — sensitive zone (authorization)
- **Verify:** Bound a grant to a territory chosen from the registry (not typed); re-parent that territory and assert the grant still points at the same node; delete the target and assert the grant is marked suspended, grants nothing, and remains visible on the account rather than disappearing or widening to unrestricted.
- **Notes:** The widening failure — delete a region, promote its administrator to the whole portal — is the one the spec names explicitly. The disappearing failure is its mirror and is also refused. Whether a territory scope reaches the subtree transitively is an open question in the spec's §2; it decides whether this picker selects one node or a subtree, so read that section before choosing.

**[T-11B05] Read-back of a role's effective permissions and an account's total reach**

- **Spec:** [l1-back-office.md](../specifications/l1-back-office.md) §3.1 (v0.2.0 — "What a role can do is readable before it is granted")
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** Open the view for a seeded role and assert every permission the seeder grants it is listed; open it for an account holding two differently-scoped grants and assert both scopes are shown distinctly.
- **Notes:** Read-only is acceptable for a first cut and is what the spec requires. This is also the surface that would have shown a human the `seo_specialist` permission over-grant that only a machine sweep caught.

**[T-11B06] Last-remaining-chief-administrator guard, enforced in the grant service**

- **Spec:** [l1-back-office.md](../specifications/l1-back-office.md) §3.1 (v0.2.0), §6 note 4
- **Status:** Todo
- **Assignment:** Agent — sensitive zone (authorization)
- **Verify:** Revoking the chief-administrator role from the last remaining holder is refused; revoking it from one of two holders succeeds. Assert both through the service directly, not through the screen.
- **Notes:** An existing test already covers the last-holder rule for role revocation; confirm it and extend it to cover deactivation of the last holder's account, which is the same lockout by a different route. The guard holds against the last remaining holder specifically, not against the role in general.

**[T-11B07] Second-factor enrolment and reset on a staff account**

- **Spec:** [l1-back-office.md](../specifications/l1-back-office.md) §2 (`[TZ]` §100), §5.2 (v0.2.0)
- **Status:** Todo
- **Assignment:** Agent — sensitive zone (authentication)
- **Verify:** Enrol a second factor on an account and assert sign-in requires it; reset it as a chief administrator and assert the account can enrol again.
- **Notes:** The panel's native multi-factor support is already wired; what is absent is any way to administer it per account. The spec records this as a consequence of `[TZ]` §100 rather than a sentence the client wrote — build the minimum that makes the stated capability reachable, not a policy engine.

**[T-11B08] Validation — a created moderator reaches exactly the screens their role grants**

- **Goal:** Prove `T-11B01`–`T-11B07` against §3.1 and §5.2.
- **Method:** Feature test creating a staff account through the resource, granting a scoped role, signing in as it, and asserting the reachable-screen set matches the role's grants exactly — 200 where granted, 403 where not, on both the navigation and the direct URL. The existing authorization-matrix probe is the template.
- **Status:** Todo

### Track C — Banner Render Completion

**[T-11C01] Render the catalog page's banner slot**

- **Spec:** [l1-advertising.md](../specifications/l1-advertising.md) §5.6; [l1-back-office.md](../specifications/l1-back-office.md) §5.1
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** A banner targeted at the catalog's resolved territory and category renders on `/catalog`; one targeted elsewhere does not; the page stays inside the 30-query budget.
- **Notes:** The selection service, targeting, impression counting and click tracking are all correct and already proven on the home and territory pages. Pass the resolved territory and category as context — a slot requested with language alone cannot match a geographic campaign. Slot rows are cached whole, so several slots on one render cost one query, not one each.

**[T-11C02] Render the object page's banner slot**

- **Spec:** [l1-advertising.md](../specifications/l1-advertising.md) §5.6
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** A banner targeted at the object's own territory renders on that object's page and not on an object in a different territory; the page stays inside its 300 ms cache-miss budget and the 30-query budget.
- **Notes:** Seed the slot alongside the others rather than adding a second seeding path.

**[T-11C03] Pass territory and category context to the home page's existing slots**

- **Spec:** [l1-advertising.md](../specifications/l1-advertising.md) §5.6
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** With a country preference stored in the session, a banner targeted at that country's territory renders on the home page; with no preference stored, an untargeted banner still renders and no targeted one leaks.
- **Notes:** The three home slots currently request by language only, so geographic targeting is unreachable there — the same defect as the missing pages, in a page that already renders banners. Decide the home page's territory from the stored country preference, the same source the country landing redirect already uses.

**[T-11C04] Validation — a territory-targeted banner reaches its own pages and no others**

- **Goal:** Prove `T-11C01`–`T-11C03` against the targeting contract.
- **Method:** Feature tests per surface asserting a resort-targeted banner appears on that resort's catalog, object and home renders and is absent from an unrelated territory's; one test asserting impressions increment exactly once per render.
- **Status:** Todo

### Track D — Map Pins Bounding

**[T-11D01] Cluster pins server-side by zoom level**

- **Spec:** [l1-object-catalog.md](../specifications/l1-object-catalog.md) (map)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** At a country-wide zoom the endpoint returns cluster centroids with counts rather than individual objects; past the configured zoom threshold it returns individual pins. Assert the switch happens at the stated zoom, not at an arbitrary count.
- **Notes:** A country-wide viewport currently serialises every object — measured at 2,151,356 bytes for 52,800 points, all clustered in the browser.

**[T-11D02] Cap individual pins per response and signal truncation to the client**

- **Spec:** [l1-object-catalog.md](../specifications/l1-object-catalog.md) (map)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** A request whose viewport holds more objects than the cap returns exactly the cap and a truncation flag; the map surfaces a "zoom in to see more" affordance rather than silently showing a partial set.
- **Notes:** Silently truncating is worse than the current oversized response — a visitor cannot tell an empty area from a clipped one.

**[T-11D03] Validation — a country-wide viewport stays under a fixed response ceiling at seeded volume**

- **Goal:** Prove `T-11D01`–`T-11D02` at realistic volume.
- **Method:** Test in the `slow` group asserting the country-wide response stays under a fixed byte ceiling with the volume seeder applied, and that the individual-pin path past the zoom threshold returns the expected shape.
- **Status:** Todo

### Track E — Fixture Volume

**[T-11E01] Seed contact channels for a realistic share of objects**

- **Spec:** [l1-object-profile.md](../specifications/l1-object-profile.md) (the direct-contact conversion contract)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** After `php artisan migrate:fresh --seed` with the volume seeder, a representative sample of objects carries several channel types with mixed activity, and the contact-click path can be exercised end to end without hand-created fixtures.
- **Notes:** The volume seeder creates 52,800 objects and zero contact channels, and the contact handoff is the portal's entire product. Keep it in the `slow` group so the ordinary loop stays fast.

**[T-11E02] Seed banners, news, promotions, articles, reviews, and an audit trail at modest volume**

- **Spec:** [l2-data-model.md](../specifications/l2-data-model.md) §2
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** Every admin screen listing one of these entities shows real rows after a seeded install, and the action journal has entries to page and filter.
- **Notes:** A single seeded audit row would have caught the action-journal crash two sweeps earlier than it was found. That is the argument for this task, and it is worth stating in the seeder itself.

**[T-11E03] Validation — the volume-sensitive suites run against the seeded set**

- **Goal:** Prove the seeded fixtures actually reach the tests that need volume.
- **Method:** Confirm `php artisan migrate:fresh --seed` applies cleanly from empty, then run `composer test:slow` and assert the contact-rendering, click-tracking and moderation-queue tests exercise seeded data rather than their own fixtures.
- **Status:** Todo

### Track F — Closing Gate

**[T-11F01] Full `composer quality` and the non-slow Pest suite, clean, after Tracks A–E close**

- **Goal:** The phase does not close on green tracks alone.
- **Method:** `composer quality` end to end plus the non-slow Pest suite in the container, and `php artisan migrate:fresh --seed` from empty. Record the counts.
- **Status:** Todo
- **Notes:** Tracks A and B both touch authorization; the architecture suite's policy-scope and sensitive-zone-ownership rules are the ones most likely to catch a mistake here, and both run inside `composer quality`.
