---
phase: 6
name: "Room Reservation & Payment"
status: Done
subsystem: "src/lib/reservation/, src/app/(marketing)/hotel/[id]/, src/app/account/reservations/"
requires:
  - "Phase 1 — Platform Foundation (reservation table with pending/paid/payment_failed/cancelled status)"
  - "Phase 2 — Identity & Back Office (Better Auth session — a reservation requires an authenticated guest)"
  - "Phase 4 — Discovery & Catalog (the shared DateRangePicker/GuestCountPicker widgets this phase reuses)"
  - "Phase 5 — Hotel Profile & Content Publishing (the room summary cards this phase wires a real interaction into)"
provides: []
key_files:
  created:
    - src/lib/reservation/reservation-query.ts
    - src/lib/reservation/reservation-query.test.ts
    - src/lib/reservation/schema.ts
    - src/lib/reservation/create-reservation.ts
    - src/lib/reservation/create-reservation.test.ts
    - src/lib/reservation/actions.ts
    - src/app/account/layout.tsx
    - src/app/account/reservations/page.tsx
    - src/app/(marketing)/hotel/[id]/room-detail-dialog.tsx
    - src/lib/reservation/payment-provider.ts
    - src/lib/reservation/simulated-payment-provider.ts
    - src/lib/reservation/pricing.ts
    - src/lib/reservation/checkout.ts
    - src/lib/reservation/checkout.test.ts
    - src/lib/reservation/checkout-query.ts
    - src/lib/reservation/checkout-actions.ts
    - src/app/account/reservations/[id]/checkout/page.tsx
    - src/app/account/reservations/[id]/checkout/simulate-payment-buttons.tsx
    - src/lib/reservation/reservation-flow-contract.test.ts
  modified:
    - src/app/(marketing)/hotel/[id]/page.tsx
    - src/components/auth-nav.tsx
    - src/components/auth-nav.test.tsx
    - src/components/header.tsx
    - src/components/header.test.tsx
    - src/lib/test-helpers/auth.ts
    - src/app/account/reservations/page.tsx
    - messages/ru.json
    - src/lib/reservation/reservation-query.test.ts
patterns_established:
  - "Dialog labels resolved server-side into one plain-object prop (no client-side i18n in this codebase) — see room-detail-dialog.tsx"
  - "Local-getter date-key formatting (not toISOString) for any client-picked calendar date reaching a 'YYYY-MM-DD' server boundary"
  - "A mutation must never run as a side effect of a GET-triggered Server Component render — even a same-app internal 'checkout URL' redirect target needs its own explicit Server Action, not an eager call during page load"
  - "An UPDATE's own precondition (e.g. status = 'pending') must be folded into that UPDATE's WHERE clause and checked via returning().length, not verified by a separate SELECT before a transaction — the SELECT is not atomic with the write"
  - "A shadcn Button rendered as a Link (render={<Link/>}) needs nativeButton={false} — Base UI's Button otherwise assumes its root DOM node is a native <button>, which an anchor isn't"
  - "A test file whose tests share one fixed fixture user/guestId must clean up per-test (afterEach), not only in one file-level afterAll — an earlier test's own rows for that same id are otherwise still present when a later test asserts an exact row count/order"
duration_minutes: ~
---

# Stage 6 Tasks — Room Reservation & Payment

**Phase:** 6
**Status:** Done
**Strategic Goal:** An authenticated guest can open a room from its hotel
profile summary card, select dates and a guest count that respect the room's
actual availability, and complete a reservation that reaches a genuine
paid/failed outcome — with that outcome visible from their own account —
closing the last gap `l1-room-reservation.md` describes and the last phase
this project's own Phase Dependency Graph schedules.

## Track Structure

Honest execution shape: `A01 → (B01 ‖ D01) → B02 → C01 → T`.

- **A01** (room detail + availability + guest-reservations query module) reads
  only tables Phase 1 already created (`room`, `roomMedia`, `amenity`,
  `reservation`) and has no dependency on anything else in this phase — it
  starts immediately.
- **B01** (room detail popup) and **D01** (guest reservation status page)
  both depend only on `A01`'s query module and touch entirely different
  parts of the app (a dialog inside the existing hotel profile route vs. a
  brand-new `/account/reservations` route) — genuinely file-disjoint,
  dispatchable in parallel. `D01` doesn't need `B01`'s popup to exist first;
  it can be built and verified against directly-seeded `reservation` rows,
  the same testing pattern every prior phase has used.
- **B02** (reservation-creation Server Action, auth-gated) needs `B01`'s
  popup to actually collect room/dates/guests from, so it is sequenced after
  it — this is where the linear "checkout funnel" shape of this phase (unlike
  Phase 4/5's broader parallel surfaces) becomes unavoidable.
- **C01** (payment) needs a `pending` reservation to attach a payment attempt
  to, which only exists once `B02` can create one — per
  `l2-third-party-integrations.md` §6's own explicit sequencing note ("Fondy
  last, once the reservation data model has a concrete 'paid' state to
  transition into").

## Decisions Carried Into Decomposition

<!-- Elective forks resolved here per magic.md §7 (DA-8/DA-9) — narrated, not asked. -->

- [DR] The payment step (`T-6C01`) is built behind a swappable
  `PaymentProvider` interface with a **simulated** implementation (deterministic
  success/failure, no live API call), not a real Fondy integration — per
  explicit user direction: no real Fondy merchant/sandbox credentials exist in
  this environment, and fabricating a plausible-looking-but-wrong API contract
  against a real payment processor is the wrong failure mode for a financial
  integration. The interface's shape mirrors what a real Fondy checkout-session
  creation + webhook callback genuinely needs (create a payment attempt for an
  amount/currency/reservation reference; receive a success/failure outcome that
  transitions the reservation) specifically so wiring in the real Fondy SDK
  later is a drop-in implementation swap, not a redesign. The simulated
  provider and its "not production-ready for real payments" status are
  documented prominently in code, not silently passed off as a finished
  integration. (Override: provide real Fondy sandbox credentials and this task
  becomes a straight swap of the interface's one implementation.)
- [DR] Guest reservation status (`T-6D01`) surfaces via a dedicated
  `/account/reservations` page, not email. Criterion: no transactional-email
  provider exists anywhere in `l2-tech-stack.md` or
  `l2-third-party-integrations.md`, and `l1-room-reservation.md` §3 itself
  says the exact surface "is left to L2 design, not re-opened as a domain
  question" — a page reachable from the guest's own session is the minimal
  buildable option with zero new dependencies.
- [DR] The room detail popup (`T-6B01`) is a Base UI `Dialog` (matching the
  shadcn/ui + Base UI primitive convention every other interactive surface in
  this project already uses), triggered from the room summary cards
  `T-5B01` already built — those cards were deliberately built with no
  functional interaction yet (Phase 5's own `[DR]`, since this phase didn't
  exist at the time), so this task is what finally gives them one, not a
  parallel/competing entry point.
- [DR] Availability (`T-6A01`) is computed from **`paid`** reservations only —
  a date range with only a `pending` (unpaid) reservation against it does not
  block another guest's selection. Criterion: `l1-room-reservation.md` §3's
  own resolved invariant states "an unpaid reservation attempt does not hold
  the dates against availability beyond a short checkout window" — this phase
  has no background job scheduler anywhere in the stack to expire a `pending`
  row after a TTL, so the simplest correct reading that needs no new
  infrastructure is: `pending` rows never held availability in the first
  place, they only become relevant once (simulated, for now) payment succeeds
  and the status flips to `paid`. (Override: `/magic.spec` to add an explicit
  short-hold TTL if concurrent-checkout double-booking during the payment step
  itself becomes a real observed problem.)

## Atomic Checklist

- [x] [T-6A01] Room detail, availability, and guest-reservations query module — DB-verified
- [x] [T-6B01] Room detail popup — code + non-DB verification complete; live dev-server click-through deferred to T-6T01
- [x] [T-6D01] Guest reservation status page (`/account/reservations`) — DB-verified
- [x] [T-6B02] Reservation-creation Server Action, auth-gated — DB-verified
- [x] [T-6C01] Payment step behind a swappable provider interface (simulated implementation) — DB-verified
- [x] [T-6T01] Validate the full reservation flow — DB-verified + live dev-server checked, full suite green

## Detailed Tracking

### [T-6A01] Room detail, availability, and guest-reservations query module

- **Spec:** l1-room-reservation.md §3 Core Invariants, §5.1 Reservation Flow
- **Status:** DB-verified (Docker/Postgres access restored mid-Phase-6; see the T-6C01 entry below for how)
- **Assignment:** Agent
- **Verify:** integration test — `getRoomDetail(roomId)` returns full detail (title, bed configuration, guest capacity, amenities grouped by area, gallery, feature tags) for a published room, `undefined` for a pending/rejected/missing one (moderation checkpoint restated at room scope, mirroring T-4A01/T-5A02's own hotel-scope precedent); `isRoomAvailable(roomId, checkIn, checkOut)` returns `false` only when a **`paid`** reservation overlaps the requested range, proven `true` against an overlapping `pending` reservation and an overlapping-but-different-room `paid` one; `getGuestReservations(guestId)` returns only that guest's own rows, newest first.
- **Handoff:** B01 and D01 both call this module.
- **Changes:**
  Added `src/lib/reservation/reservation-query.ts` — `getRoomDetail(roomId)` (joins to `hotel` and checks *both* `room.status`/`hotel.status` are `published`, not just the room's own — a room can't be independently reservable if its hotel got un-published after the room was, mirroring T-5A02's own belt-and-suspenders moderation check), `isRoomAvailable(roomId, checkIn, checkOut)` (a `paid`-only overlap query — standard exclusive-checkout interval semantics, `lt(reservation.checkIn, checkOut) AND gt(reservation.checkOut, checkIn)`), `getGuestReservations(guestId)` (joined to `room`/`hotel` for display context, newest-first).
  Added `reservation-query.test.ts` — 4 tests covering exactly the Verify line above, including a specific case for the exclusive-checkout boundary (a request ending exactly on a paid reservation's start date is available, not blocked).
  **Environment blocker hit immediately, not caused by this task**: this session's sandbox was resumed mid-Phase-6-planning with a different drive mapping (`D:`→`C:`, see STATE.md's Recent Decisions) and, separately, Docker/Postgres (`booking-postgres-1`, port 5433) was unreachable for a large stretch of this session — confirmed via user direction to continue without blocking. Verified what was possible without a database in the meantime: `tsc --noEmit` clean; `pnpm exec biome check .` — found 245 pre-existing CRLF-vs-LF errors across the *entire* repo (a fresh-checkout artifact from this drive's own `git`/`core.autocrlf` interaction, confirmed via `git diff --ignore-all-space` showing zero real content change on a sampled file), fixed via `--write`, unrelated to this task's own files; `fallow audit --format json` — `verdict: "pass"`, all `gate: "new-only"` categories zero.
  **DB verification, now run**: Docker's CLI reappeared on PATH mid-session (previously not found at all) but its daemon wasn't running; started Docker Desktop, then `docker compose up -d` recreated the `booking-postgres-1` container — the named `postgres-data` volume survived the recreation intact (`\dt` confirmed all 14 tables present, no migration needed). `pnpm exec vitest run` against the live database: `reservation-query.test.ts`'s own 4 tests all pass individually across two full-suite runs. One of its tests (`getGuestReservations returns only that guest's own rows, newest first`) failed once in the first full-suite run and passed on an immediate re-run with no code change in between — a transient cross-file fixture-visibility collision (Vitest's parallel workers sharing one live, non-transactional Postgres instance), the exact class of flakiness already documented as a Blocking Constraint in STATE.md, not a defect in this task's own query logic. This class of flakiness is a pre-existing, whole-suite characteristic (also observed in unrelated Phase 4 files, `catalog-query.test.ts`/`catalog-contract.test.ts`, neither touched this phase) — flagged honestly here rather than silently re-run until green, and left as an open item for T-6T01/a future dedicated task, not fixed as part of this task.
- **Notes:** `src/lib/reservation/reservation-query.ts`. Reuses the amenity-grouping shape `l1-room-reservation.md` §3 describes (room/bathroom/bedroom/general) directly from `amenityGroupEnum` — no new taxonomy.

### [T-6B01] Room detail popup — dates/guests selection wired into the hotel profile's room cards

- **Spec:** l1-room-reservation.md §3 Core Invariants (room detail popup), §6 Implementation Note #2 (reuse the shared widgets)
- **Status:** Code + non-DB verification complete; live dev-server click-through of the popup deferred to T-6T01 (its own designated scope for that specific check)
- **Assignment:** Agent
- **Verify:** component test — opening the popup for a mocked room detail renders its gallery/bed-configuration/amenities/feature-tags; the shared `DateRangePicker`/`GuestCountPicker` (T-4A02) are rendered, not reimplemented; selecting a date range that a mocked `isRoomAvailable` reports unavailable disables/blocks the reservation submit control.
- **Handoff:** B02 consumes the popup's collected room id/date range/guest count.
- **Changes:**
  Added `src/lib/reservation/schema.ts` (client-safe zod schema — roomId/checkIn/checkOut/guestCount with a `.refine()` on checkIn < checkOut; discriminated-union `CreateReservationResult` with UNAUTHENTICATED/VALIDATION_ERROR/ROOM_NOT_FOUND/CAPACITY_EXCEEDED/UNAVAILABLE variants) and `src/app/(marketing)/hotel/[id]/room-detail-dialog.tsx` (`RoomDetailDialog`, a Base UI `Dialog` wrapping T-4A02's `DateRangePicker`/`GuestCountPicker` unmodified — both components' own doc comments already anticipated this exact reuse). Wired into T-5B01's previously-inert room cards in `hotel/[id]/page.tsx`.
  Full room detail (amenities, bed configuration) is fetched lazily via a Server Action when the dialog opens, rather than server-rendered for every room on the page up front — most room cards on a given hotel profile are never opened. Availability is live-checked via a Server Action whenever both dates are picked, guarded by a request-id ref so a slow, stale response can't overwrite a newer one.
  Dates are formatted to `"YYYY-MM-DD"` via local `Date` getters, not `toISOString()` — the latter renders in UTC and would silently shift a locally-selected date back one day for any positive UTC offset (all of Russia).
  This codebase has no `NextIntlClientProvider`/client-side `useTranslations` (confirmed by reading every existing Client Component that takes labels — `AuthNav`, `SignInForm` — all take fully-resolved plain-string props from their Server Component parent). Followed that convention: every dialog label is resolved server-side in `page.tsx` and threaded down as one `labels` object prop. The one value not known until client-side data arrives (bed configuration) uses a static translated prefix plus the raw value as a JSX sibling — matching how the existing room cards already render price (`{fromLabel} {basePrice} ₴`) — rather than introducing template interpolation this codebase doesn't otherwise use.
  Fallow's complexity gate (`cognitive` 26 against a 15 threshold) flagged the first-draft single-function `RoomDetailDialog` — fixed the same way `HotelProfilePage` was fixed twice already (T-5B01/T-5B02): extraction into small named sub-components (`RoomMediaAndPricing`, `RoomDetailInfo`, `AvailabilityStatus`, `ReservationOutcome`), never test-coverage padding. Re-audit: `complexity_introduced: 0`.
  `messages/ru.json` — new `RoomBooking` namespace (20 keys).
  **Verified:** `tsc --noEmit`, `pnpm exec biome check .`, `fallow audit --format json` (`verdict: "pass"`, all `gate: "new-only"` categories zero) all clean. Existing `hotel/[id]/page.test.tsx` (5 tests, mocked `getHotelProfile`) passes unmodified — a closed `Dialog` renders only its trigger, so no new mocking was needed. The dialog's own two Server Action dependencies (`checkRoomAvailabilityAction`, `getRoomDetailAction`) are exercised indirectly and DB-verified via `create-reservation.test.ts`/`reservation-query.test.ts` now that Postgres access is restored (see T-6A01/T-6B02's Changes) — a real dev-server click-through of the popup itself (the one thing that actually proves the UI wiring, not just the underlying queries) is still deferred to T-6T01, which owns that specific check per this file's own Method line.
- **Notes:** Trigger element replaces T-5B01's inert room summary card. The popup itself is a Client Component; the room summary data it opens with is still fetched server-side and passed down as props.

### [T-6D01] Guest reservation status page (`/account/reservations`)

- **Spec:** l1-room-reservation.md §3 Core Invariants ("the outcome of a reservation... must be surfaced to the guest, at minimum a confirmation state reachable from their account")
- **Status:** DB-verified
- **Assignment:** Agent
- **Verify:** component test — an authenticated guest with reservations in each status (`pending`, `paid`, `payment_failed`, `cancelled`) sees each rendered with a distinguishable status label; an unauthenticated request redirects to sign-in rather than rendering an empty/broken page; a guest with zero reservations sees an empty state, not an error.
- **Handoff:** feeds T-6T01's full-flow validation (this is where a completed checkout's outcome becomes visible).
- **Changes:**
  Added `src/app/account/layout.tsx` (Header/Footer wrapper, identical to `add-hotel/layout.tsx`) and `src/app/account/reservations/page.tsx` — mirrors `add-hotel/page.tsx`'s exact auth-gate shape (`getCurrentUser` → `redirect("/sign-in")` if absent; this project has no middleware, so every authenticated route gates itself the same way). Lists `getGuestReservations(guestId)` rows with a status badge (`pending`→secondary, `paid`→default, `payment_failed`→destructive, `cancelled`→outline, reusing the `Badge` variant vocabulary `AddHotelDashboard` already established) and an empty state.
  Added a "My reservations" link to `AuthNav`, shown only when authenticated (an unauthenticated click would just bounce straight back off the page's own redirect, so the link is hidden rather than shown-then-redirected) — new `Header.myReservationsLabel` key, new `AccountReservations` namespace (8 keys).
  **Verified:** `tsc --noEmit`, `pnpm exec biome check .`, `fallow audit --format json` clean. `auth-nav.test.tsx` extended with a case for the new link (and one pre-existing assertion switched from `toHaveAttribute`, a jest-dom matcher this project's Vitest setup doesn't register, to the `getAttribute` convention used everywhere else in this codebase); `header.test.tsx` extended to assert the new translation key resolves. Both pass. **DB verification, now run**: this page's own data source (`getGuestReservations`) is DB-verified via `reservation-query.test.ts` and, end-to-end, via `checkout.test.ts`'s status-transition tests reading back real rows this page's own query shape would return (same join/status fields). A dedicated live dev-server render for a guest with mixed-status reservations is still deferred to T-6T01.
- **Notes:** `src/app/account/reservations/page.tsx` — a new top-level `account/` route group (not under `(marketing)/`, mirroring how `add-hotel/` already sits outside it). Auth check follows the same session-resolution pattern already established for the add-hotel dashboard.

### [T-6B02] Reservation-creation Server Action, auth-gated

- **Spec:** l1-room-reservation.md §3 Core Invariants (reservation captures room/dates/guests; guest must be authenticated), §5.1 Reservation Flow
- **Status:** DB-verified
- **Assignment:** Agent
- **Verify:** integration test — an authenticated guest submitting valid room/dates/guests creates a `pending` reservation row and returns a reference for the checkout step; an unauthenticated submission is rejected (redirect-to-sign-in path, matching the flow diagram's `E -->|no| L[Login/register]`); a submission for a now-unavailable range (a `paid` reservation was created concurrently) is rejected with a clear reason rather than creating a conflicting row.
- **Handoff:** C01 initiates payment against the `pending` reservation this creates.
- **Changes:**
  Added `src/lib/reservation/create-reservation.ts` (`createReservationCore(requestHeaders, input)`, following `submitHotelListingCore`'s exact core-function/discriminated-result shape) and `src/lib/reservation/actions.ts` (`"use server"`; three actions — `createReservationAction`, `checkRoomAvailabilityAction`, `getRoomDetailAction` — confirmed against `property-onboarding/actions.ts` that multiple Server Actions may share one file, so the dialog's three RPCs didn't need three separate files). Flow: auth → schema validation → room-published/capacity check → an `isRoomAvailable` re-check at creation time (closes the window between opening the popup and clicking Reserve) → insert as `status: "pending"`.
  This surfaced a real consequence of T-6A01's own `[DR]` (availability keyed off `paid` reservations only): two guests can both hold a `pending` reservation for overlapping dates by design, so this task's re-check is a UX courtesy, not the authoritative concurrency guard. **T-6C01 must re-check availability atomically before flipping a reservation to `paid`** — documented in `create-reservation.ts`'s own doc comment so it isn't lost before that task starts.
  Extended `src/lib/test-helpers/auth.ts`'s shared `deleteTestUsers` to also delete `reservation` rows by `guestId` before deleting the user row — `reservation.guest_id` has no `onDelete: cascade` (the same non-cascading pattern `hotel.owner_id` already has), so without this fix, *any* test creating a reservation for a `signUpAndGetCookieHeaders` user would fail its own cleanup with a foreign-key violation. Fixed at the shared helper rather than worked around locally, since every future reservation-touching test hits the same gap.
  Added `create-reservation.test.ts` — 6 tests (unauthenticated, invalid dates, unpublished room, over-capacity, blocked by a paid overlap, and the success path including a DB round-trip check of the inserted row).
  **Verified:** `tsc --noEmit`, `pnpm exec biome check .`, `fallow audit --format json` (`verdict: "pass"`) clean. Full non-DB suite re-run after every change in this task: 27 files / 72 tests passing throughout, unchanged from the T-6A01 baseline — zero regressions. **DB verification, now run**: all 6 of `create-reservation.test.ts`'s cases pass against the live database (Docker/Postgres access restored mid-Phase-6; see T-6C01's Changes for how) — confirmed across two consecutive full-suite runs with zero failures in this file either time.
- **Notes:** `src/lib/reservation/create-reservation.ts` (persistence) + `actions.ts` (`"use server"`), following this project's established Server Action file-split pattern (schema/persistence/actions — see STATE.md's Blocking Constraint, `src/lib/property-onboarding/{schema,submit-listing,actions}.ts` is the reference shape).

### [T-6C01] Payment step behind a swappable provider interface (simulated implementation)

- **Spec:** l2-third-party-integrations.md §5.2 (Fondy), l1-room-reservation.md §5.1 (checkout → success/failure branch)
- **Status:** DB-verified
- **Assignment:** Agent (multi-agent build + adversarial verify, orchestrated)
- **Verify:** integration test — a simulated-success payment transitions the reservation from `pending` to `paid` and records a payment reference; a simulated-failure transitions it to `payment_failed` and leaves the room available again (per T-6A01's `paid`-only availability rule, this falls out for free — no explicit "release" step needed); both outcomes are reachable deterministically in tests (not randomly), so the state-machine transition itself — not the simulation's randomness — is what's under test.
- **Handoff:** feeds T-6D01's status display and T-6T01's full-flow validation.
- **Changes:**
  Added `src/lib/reservation/payment-provider.ts` (the `PaymentProvider` interface — `createPayment(input): Promise<PaymentAttempt>` — and `getPaymentProvider()` factory) and `src/lib/reservation/simulated-payment-provider.ts` (the only implementation for now; resolves immediately with a `sim_`-prefixed reference and an internal `checkoutUrl`, loudly commented as not a real integration). Deliberately does NOT put outcome-resolution on the provider interface — a real gateway reports outcomes via an asynchronous webhook it calls, not a method the caller calls, so that entry point lives on the reservation side instead (`resolvePaymentCore` below).
  Added `src/lib/reservation/checkout.ts` (`initiatePaymentCore` — auth/ownership/`NOT_PENDING` checks, computes the amount from `room.basePrice × nights` via a new shared `pricing.ts` helper, calls the provider, persists the reference; `resolvePaymentCore` — same checks, then inside one `db.transaction`: a `"failure"` outcome writes `payment_failed`, a `"success"` outcome first re-checks for a competing `paid` reservation overlapping the same room/dates and writes `payment_failed` instead of `paid` if one exists — closing the concurrency window `create-reservation.ts`'s own doc comment flagged forward to this task). The reservation's own `status = "pending"` precondition is folded into the terminal UPDATE's `WHERE` clause (checked via `returning().length`, not a pre-transaction `SELECT`), so two concurrent/duplicate resolve calls for the same reservation can never both "win" — the loser reports `NOT_PENDING` instead of silently overwriting the winner's terminal status. Documented explicitly that this transaction-scoped re-check is a best-effort mitigation under Postgres's default READ COMMITTED isolation, not an airtight guarantee — a fully airtight one needs a `daterange` + `EXCLUDE USING gist` constraint, a schema migration flagged as a real, known follow-up, not implied to already exist.
  Added `src/lib/reservation/checkout-actions.ts` (`"use server"`, mirrors `actions.ts`'s split), `src/lib/reservation/checkout-query.ts` (`getReservationForCheckout`, the checkout page's read-only display data source), `src/app/account/reservations/[id]/checkout/page.tsx` (auth-gated, `notFound()` for a missing-or-not-owned reservation — deliberately the same response for both, never revealing that an id belongs to someone else — `redirect` to the reservations list if already resolved), and `src/app/account/reservations/[id]/checkout/simulate-payment-buttons.tsx` (Client Component, two buttons calling `initiatePaymentAction` then `resolvePaymentAction` on click, `router.refresh()` after success per this codebase's established Router-Cache-staleness fix). Added a "Pay now" link to `account/reservations/page.tsx` for `pending` rows, and a new `Checkout` ru.json namespace (8 keys) plus `AccountReservations.payNowLabel`.
  **Built via an orchestrated multi-agent workflow** (Ultracode session posture): 4 agents built the pieces above in dependency order (provider → persistence core → UI → tests), then 3 agents adversarially reviewed the actual files through independent lenses (correctness/races, auth/ownership, convention-consistency), surfacing 8 real findings — unrounded floating-point currency math (`19.99 × 3 = 59.96999999999999`, not a clean 2-decimal value) in both `checkout.ts` and `checkout-query.ts`; the reservation's own pending-check not being atomic with the write; `initiatePaymentCore` originally running as a side effect of the checkout page's GET-triggered render instead of behind an explicit Server Action (a real risk once swapped for a live gateway — Link prefetching could silently create real checkout sessions); `resolvePaymentCore` not validating `outcome` strictly against the two known literals; the nights/amount math duplicated verbatim between two files; and one i18n-pattern inconsistency. A fix agent confirmed and resolved all 8 by re-reading the actual code (not blindly trusting the findings) — extracted the shared `pricing.ts` helper (rounds to whole cents), made the pending-check atomic, moved payment initiation behind `initiatePaymentAction`, added strict outcome validation with a new `INVALID_OUTCOME` error, and fixed the i18n key to match the established embedded-placeholder pattern.
  Added `checkout.test.ts` — 12 tests (both core functions' auth/ownership/not-pending rejections, the persisted-reference/checkout-url success path, both terminal-status transitions, the overlap-downgrade case, and a `Promise.all`-driven concurrent-duplicate-call test proving the atomic-UPDATE fix actually prevents a double-write).
  **DB access was restored during this task**: Docker's CLI reappeared on PATH (previously not found all session) with its daemon stopped; started Docker Desktop, then `docker compose up -d` recreated `booking-postgres-1` — the named `postgres-data` volume preserved the schema intact. `pnpm exec vitest run` against the live database, run twice: `checkout.test.ts`'s own 12 tests passed cleanly both times, as did `create-reservation.test.ts`'s 6. Two unrelated, pre-existing test files (`catalog-query.test.ts`, `catalog-contract.test.ts` — Phase 4, untouched this phase) and `reservation-query.test.ts` showed 2-4 flaky failures that changed shape between the two runs — confirmed as transient cross-file fixture-visibility collisions (Vitest's parallel workers sharing one live, non-transactional Postgres instance — an already-documented STATE.md Blocking Constraint), not a regression from this task; the underlying orphaned-looking rows were gone on direct inspection immediately after, consistent with in-flight concurrent fixtures rather than leftover pollution. `tsc --noEmit`, `pnpm exec biome check .` (18 pre-existing CRLF/LF errors from the same drive-checkout artifact as T-6A01's, fixed via `--write`, unrelated to this task's content), and `fallow audit --format json` (`verdict: "pass"`, `complexity_introduced: 0`, `dead_code_introduced: 0`) all clean. Fallow flagged two new Client Components (`SimulatePaymentButtons`, `ReservationCheckoutPage`) at a "moderate" CRAP severity purely from zero dedicated component-test coverage (their raw complexity is low, `cyclomatic` 5) — not gating (`introduced: false` on both), and left uncovered at the component level consistent with this project's established pattern of testing this kind of thin UI at the persistence-layer/integration level instead (mirrors `RoomDetailDialog`, which likewise has no dedicated component test).
- **Notes:** `src/lib/reservation/payment-provider.ts` + `simulated-payment-provider.ts`. The next real step (once Fondy credentials exist) is a `fondy-payment-provider.ts` implementing the same `PaymentProvider` interface plus a webhook route calling `resolvePaymentCore`, not a rewrite of the checkout flow around it.

### [T-6T01] Validate the full reservation flow

- **Goal:** Prove the complete guest journey — room detail → available dates/guests → auth gate → pending reservation → payment outcome → visible confirmation — holds end-to-end, and that the availability invariant genuinely prevents a double-booking; mirrors T-2T01/T-3T01/T-4T01/T-5T01's role as this phase's exit gate.
- **Method:** One integration test driving: an authenticated guest reserves a room for an available range → a `pending` row exists → simulated payment succeeds → the row is `paid` and appears in `/account/reservations` → a second guest attempting to reserve the *same* now-`paid` range for the same room is rejected by `isRoomAvailable`. A second scenario: simulated payment fails → the row is `payment_failed` and the range remains available to a different guest. Then the full quality gate: `pnpm test`, `pnpm exec biome check .`, `pnpm exec tsc --noEmit`, `pnpm exec fallow audit --format json` (expect the `gate: "new-only"` attribution clean, and — per Phase 5's own T-5T01 finding — the raw top-level `verdict` field itself should read `"pass"` if no dead exports are left stranded), plus a live dev-server check of the hotel profile page (room popup opens, dates/guests selectable) and `/account/reservations` (this project's standing constraint: stop the dev server before `pnpm test`).
- **Status:** Done — full quality gate green, live-verified in a real browser
- **Changes:**
  Added `src/lib/reservation/reservation-flow-contract.test.ts` (mirrors T-4T01/T-5T01's `*-contract.test.ts` exit-gate pattern) — two integration tests against a shared fixture hotel/room: the full journey (available → reserve → still available while `pending` → pay → `paid` → blocks availability → visible in the guest's own `getGuestReservations` list → a second guest's overlapping attempt rejected with `UNAVAILABLE`), and the failure path (reserve → pay-fails → `payment_failed` → dates remain available → a different guest can freely reserve them). Both pass against the live database.
  **Live dev-server check, run in a real browser** (chrome-devtools MCP driving headless Chromium against `pnpm dev`): seeded a real published hotel/room directly via SQL (every fixture the test suite creates cleans itself up, so nothing "real" was left in the DB to click through) — clicked through the entire flow exactly as a guest would: opened a room card's dialog on the hotel profile page → picked a date range → saw the live availability check resolve to "Dates available" → clicked Reserve while unauthenticated and confirmed the sign-in prompt renders correctly with a working link → signed up as a new guest through the real `/sign-up` form → returned to the hotel page → reserved the room → landed on the success state inside the dialog → followed its link to `/account/reservations` → saw the new row as "Awaiting payment" with a "Pay" action → opened the checkout page → confirmed the displayed amount (120 ₴ × 5 nights = "Due: 600.00 UAH") matches the DB exactly → clicked "Simulate a successful payment" → landed back on `/account/reservations` showing "Paid". Zero console errors at any step, confirmed via `list_console_messages` after every navigation.
  **Two real, non-hypothetical defects surfaced by the live check that no automated test had caught, both fixed on the spot:**
  1. Base UI logged a console error: `AccountReservationsPage`'s "Pay" `<Button render={<Link/>}>` renders as an anchor while `Button` defaults to assuming a native `<button>` root — fixed by adding `nativeButton={false}` (Base UI's own recommended fix). Confirmed pre-existing since Phase 3 (`add-hotel/page.tsx`'s "Add hotel" button has the identical unfixed pattern) — left that file untouched as a cross-phase concern, not this phase's regression to fix.
  2. What earlier tasks' Changes logged as "flaky, pre-existing cross-file Postgres pollution" for `reservation-query.test.ts`'s `getGuestReservations` test turned out, on closer investigation now that DB access allowed actually diagnosing it, to be a **real, deterministic bug in that test file itself**, not cross-file flakiness: every test in the file shares one fixed fixture `guestId`, and the file only cleans up reservations in one file-level `afterAll` — so the `isRoomAvailable` test's own reservations for that same guest (declared earlier in the file) are still present in the database when the `getGuestReservations` test later asserts an *exact* 2-row result. Fixed by scoping that test's assertion to the rows it itself created (`ownResults = results.filter(...)`) plus an explicit check that the other guest's row is absent — preserves everything the test actually claims to prove, without depending on total isolation from sibling tests in the same file. Reclassifying this from "known flakiness, left open" to "diagnosed and fixed" matters: the original entry (T-6A01's Changes) undersold it as random when it was fully reproducible once traced.
  **Final quality gate, all green:** `pnpm exec tsc --noEmit` clean. `pnpm exec biome check .` clean (264 files). `pnpm exec fallow audit --format json` — `verdict: "pass"` (the raw top-level field, not just the `gate: "new-only"` attribution — Phase 5's own T-5T01 bar), `dead_code_introduced: 0`, `complexity_introduced: 0`. `pnpm exec vitest run` — **52 files, 158 tests, 0 failures** — the entire suite, not just this phase's own files, fully green for the first time this session (the two catalog files that showed transient failures during T-6C01's own run passed cleanly here with no code change, consistent with genuine cross-worker timing flakiness on those; `reservation-query.test.ts`'s failure did NOT recur after the fix above, consistent with it having been deterministic all along).
  All DB fixtures created for the live check (the seeded hotel/room, the live-guest account, the reservation) were deleted afterward; verified `SELECT count(*)` on `hotel`/`room`/`reservation` all read `0` post-cleanup.
- **Notes:** This is the last task of Phase 6, and Phase 6 was the last phase this project's own Phase Dependency Graph scheduled — the full 6-phase plan (`PLAN.md`) is complete as of this task.
