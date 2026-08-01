---
phase: 6
name: "Room Reservation & Payment"
status: In Progress
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
  modified:
    - src/app/(marketing)/hotel/[id]/page.tsx
    - src/components/auth-nav.tsx
    - src/components/auth-nav.test.tsx
    - src/components/header.tsx
    - src/components/header.test.tsx
    - src/lib/test-helpers/auth.ts
    - messages/ru.json
patterns_established:
  - "Dialog labels resolved server-side into one plain-object prop (no client-side i18n in this codebase) — see room-detail-dialog.tsx"
  - "Local-getter date-key formatting (not toISOString) for any client-picked calendar date reaching a 'YYYY-MM-DD' server boundary"
duration_minutes: ~
---

# Stage 6 Tasks — Room Reservation & Payment

**Phase:** 6
**Status:** In Progress
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

- [ ] [T-6A01] Room detail, availability, and guest-reservations query module — code complete, DB verification deferred (see Changes)
- [ ] [T-6B01] Room detail popup — code complete, DB verification deferred (see Changes)
- [ ] [T-6D01] Guest reservation status page (`/account/reservations`) — code complete, DB verification deferred (see Changes)
- [ ] [T-6B02] Reservation-creation Server Action, auth-gated — code complete, DB verification deferred (see Changes)
- [ ] [T-6C01] Payment step behind a swappable provider interface (simulated implementation)
- [ ] [T-6T01] Validate the full reservation flow — availability, auth gate, payment state transitions, confirmation visibility

## Detailed Tracking

### [T-6A01] Room detail, availability, and guest-reservations query module

- **Spec:** l1-room-reservation.md §3 Core Invariants, §5.1 Reservation Flow
- **Status:** Code complete — DB verification deferred (environment blocker, see below)
- **Assignment:** Agent
- **Verify:** integration test — `getRoomDetail(roomId)` returns full detail (title, bed configuration, guest capacity, amenities grouped by area, gallery, feature tags) for a published room, `undefined` for a pending/rejected/missing one (moderation checkpoint restated at room scope, mirroring T-4A01/T-5A02's own hotel-scope precedent); `isRoomAvailable(roomId, checkIn, checkOut)` returns `false` only when a **`paid`** reservation overlaps the requested range, proven `true` against an overlapping `pending` reservation and an overlapping-but-different-room `paid` one; `getGuestReservations(guestId)` returns only that guest's own rows, newest first.
- **Handoff:** B01 and D01 both call this module.
- **Changes:**
  Added `src/lib/reservation/reservation-query.ts` — `getRoomDetail(roomId)` (joins to `hotel` and checks *both* `room.status`/`hotel.status` are `published`, not just the room's own — a room can't be independently reservable if its hotel got un-published after the room was, mirroring T-5A02's own belt-and-suspenders moderation check), `isRoomAvailable(roomId, checkIn, checkOut)` (a `paid`-only overlap query — standard exclusive-checkout interval semantics, `lt(reservation.checkIn, checkOut) AND gt(reservation.checkOut, checkIn)`), `getGuestReservations(guestId)` (joined to `room`/`hotel` for display context, newest-first).
  Added `reservation-query.test.ts` — 4 tests covering exactly the Verify line above, including a specific case for the exclusive-checkout boundary (a request ending exactly on a paid reservation's start date is available, not blocked).
  **Environment blocker hit immediately, not caused by this task**: this session's sandbox was resumed mid-Phase-6-planning with a different drive mapping (`D:`→`C:`, see STATE.md's Recent Decisions) and, separately, Docker/Postgres (`booking-postgres-1`, port 5433) is unreachable in the resumed environment — confirmed via user direction to continue without blocking. Verified what's possible without a database: `tsc --noEmit` clean; `pnpm exec biome check .` — found 245 pre-existing CRLF-vs-LF errors across the *entire* repo (a fresh-checkout artifact from this drive's own `git`/`core.autocrlf` interaction, confirmed via `git diff --ignore-all-space` showing zero real content change on a sampled file), fixed via `--write`, unrelated to this task's own files; `fallow audit --format json` — `verdict: "pass"`, all `gate: "new-only"` categories zero. `pnpm test` run for informational signal only (not claimed as verification): 27 files/72 tests that don't touch the database still pass; 22 files fail with `ECONNREFUSED 127.0.0.1:5433`, including this task's own new `reservation-query.test.ts` — **expected and unverified, not a regression**. The atomic checklist item above stays unchecked deliberately (not `[x]`) specifically so `archive-phases` cannot prematurely archive this phase before real DB verification actually happens — see T-6T01, which is where the deferred verification for every T-6 task gets run together once Postgres access is restored.
- **Notes:** `src/lib/reservation/reservation-query.ts`. Reuses the amenity-grouping shape `l1-room-reservation.md` §3 describes (room/bathroom/bedroom/general) directly from `amenityGroupEnum` — no new taxonomy.

### [T-6B01] Room detail popup — dates/guests selection wired into the hotel profile's room cards

- **Spec:** l1-room-reservation.md §3 Core Invariants (room detail popup), §6 Implementation Note #2 (reuse the shared widgets)
- **Status:** Code complete — DB verification deferred (same environment blocker as T-6A01, see below)
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
  **Verified:** `tsc --noEmit`, `pnpm exec biome check .`, `fallow audit --format json` (`verdict: "pass"`, all `gate: "new-only"` categories zero) all clean. Existing `hotel/[id]/page.test.tsx` (5 tests, mocked `getHotelProfile`) passes unmodified — a closed `Dialog` renders only its trigger, so no new mocking was needed. **DB-dependent verification deferred**: the live availability-check/reserve round-trip and a real dev-server click-through have not been run (Postgres unreachable this session, see T-6A01's Changes for the full blocker writeup).
- **Notes:** Trigger element replaces T-5B01's inert room summary card. The popup itself is a Client Component; the room summary data it opens with is still fetched server-side and passed down as props.

### [T-6D01] Guest reservation status page (`/account/reservations`)

- **Spec:** l1-room-reservation.md §3 Core Invariants ("the outcome of a reservation... must be surfaced to the guest, at minimum a confirmation state reachable from their account")
- **Status:** Code complete — DB verification deferred (same environment blocker as T-6A01, see below)
- **Assignment:** Agent
- **Verify:** component test — an authenticated guest with reservations in each status (`pending`, `paid`, `payment_failed`, `cancelled`) sees each rendered with a distinguishable status label; an unauthenticated request redirects to sign-in rather than rendering an empty/broken page; a guest with zero reservations sees an empty state, not an error.
- **Handoff:** feeds T-6T01's full-flow validation (this is where a completed checkout's outcome becomes visible).
- **Changes:**
  Added `src/app/account/layout.tsx` (Header/Footer wrapper, identical to `add-hotel/layout.tsx`) and `src/app/account/reservations/page.tsx` — mirrors `add-hotel/page.tsx`'s exact auth-gate shape (`getCurrentUser` → `redirect("/sign-in")` if absent; this project has no middleware, so every authenticated route gates itself the same way). Lists `getGuestReservations(guestId)` rows with a status badge (`pending`→secondary, `paid`→default, `payment_failed`→destructive, `cancelled`→outline, reusing the `Badge` variant vocabulary `AddHotelDashboard` already established) and an empty state.
  Added a "Мои бронирования" link to `AuthNav`, shown only when authenticated (an unauthenticated click would just bounce straight back off the page's own redirect, so the link is hidden rather than shown-then-redirected) — new `Header.myReservationsLabel` key, new `AccountReservations` namespace (8 keys).
  **Verified:** `tsc --noEmit`, `pnpm exec biome check .`, `fallow audit --format json` clean. `auth-nav.test.tsx` extended with a case for the new link (and one pre-existing assertion switched from `toHaveAttribute`, a jest-dom matcher this project's Vitest setup doesn't register, to the `getAttribute` convention used everywhere else in this codebase); `header.test.tsx` extended to assert the new translation key resolves. Both pass. **DB-dependent verification deferred**: the page's real query round-trip and its render for a guest with mixed-status reservations have not been run against Postgres.
- **Notes:** `src/app/account/reservations/page.tsx` — a new top-level `account/` route group (not under `(marketing)/`, mirroring how `add-hotel/` already sits outside it). Auth check follows the same session-resolution pattern already established for the add-hotel dashboard.

### [T-6B02] Reservation-creation Server Action, auth-gated

- **Spec:** l1-room-reservation.md §3 Core Invariants (reservation captures room/dates/guests; guest must be authenticated), §5.1 Reservation Flow
- **Status:** Code complete — DB verification deferred (same environment blocker as T-6A01, see below)
- **Assignment:** Agent
- **Verify:** integration test — an authenticated guest submitting valid room/dates/guests creates a `pending` reservation row and returns a reference for the checkout step; an unauthenticated submission is rejected (redirect-to-sign-in path, matching the flow diagram's `E -->|no| L[Login/register]`); a submission for a now-unavailable range (a `paid` reservation was created concurrently) is rejected with a clear reason rather than creating a conflicting row.
- **Handoff:** C01 initiates payment against the `pending` reservation this creates.
- **Changes:**
  Added `src/lib/reservation/create-reservation.ts` (`createReservationCore(requestHeaders, input)`, following `submitHotelListingCore`'s exact core-function/discriminated-result shape) and `src/lib/reservation/actions.ts` (`"use server"`; three actions — `createReservationAction`, `checkRoomAvailabilityAction`, `getRoomDetailAction` — confirmed against `property-onboarding/actions.ts` that multiple Server Actions may share one file, so the dialog's three RPCs didn't need three separate files). Flow: auth → schema validation → room-published/capacity check → an `isRoomAvailable` re-check at creation time (closes the window between opening the popup and clicking Reserve) → insert as `status: "pending"`.
  This surfaced a real consequence of T-6A01's own `[DR]` (availability keyed off `paid` reservations only): two guests can both hold a `pending` reservation for overlapping dates by design, so this task's re-check is a UX courtesy, not the authoritative concurrency guard. **T-6C01 must re-check availability atomically before flipping a reservation to `paid`** — documented in `create-reservation.ts`'s own doc comment so it isn't lost before that task starts.
  Extended `src/lib/test-helpers/auth.ts`'s shared `deleteTestUsers` to also delete `reservation` rows by `guestId` before deleting the user row — `reservation.guest_id` has no `onDelete: cascade` (the same non-cascading pattern `hotel.owner_id` already has), so without this fix, *any* test creating a reservation for a `signUpAndGetCookieHeaders` user would fail its own cleanup with a foreign-key violation. Fixed at the shared helper rather than worked around locally, since every future reservation-touching test hits the same gap.
  Added `create-reservation.test.ts` — 6 tests (unauthenticated, invalid dates, unpublished room, over-capacity, blocked by a paid overlap, and the success path including a DB round-trip check of the inserted row).
  **Verified:** `tsc --noEmit`, `pnpm exec biome check .`, `fallow audit --format json` (`verdict: "pass"`) clean. Full non-DB suite re-run after every change in this task: 27 files / 72 tests passing throughout, unchanged from the T-6A01 baseline — zero regressions. **DB-dependent verification deferred**: `create-reservation.test.ts`'s 6 cases have not been run against a live Postgres instance.
- **Notes:** `src/lib/reservation/create-reservation.ts` (persistence) + `actions.ts` (`"use server"`), following this project's established Server Action file-split pattern (schema/persistence/actions — see STATE.md's Blocking Constraint, `src/lib/property-onboarding/{schema,submit-listing,actions}.ts` is the reference shape).

### [T-6C01] Payment step behind a swappable provider interface (simulated implementation)

- **Spec:** l2-third-party-integrations.md §5.2 (Fondy), l1-room-reservation.md §5.1 (checkout → success/failure branch)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** integration test — a simulated-success payment transitions the reservation from `pending` to `paid` and records a payment reference; a simulated-failure transitions it to `payment_failed` and leaves the room available again (per T-6A01's `paid`-only availability rule, this falls out for free — no explicit "release" step needed); both outcomes are reachable deterministically in tests (not randomly), so the state-machine transition itself — not the simulation's randomness — is what's under test.
- **Handoff:** feeds T-6D01's status display and T-6T01's full-flow validation.
- **Notes:** `src/lib/reservation/payment-provider.ts` defines the interface (`createPayment(reservation): Promise<PaymentAttempt>`, a callback/webhook-shaped `resolvePayment(reference, outcome)`); `src/lib/reservation/simulated-payment-provider.ts` is the only implementation for now. Loudly comment the file as a stand-in, not a real integration — the next real step (once credentials exist) is a `fondy-payment-provider.ts` implementing the same interface, not a rewrite of the checkout flow around it.

### [T-6T01] Validate the full reservation flow

- **Goal:** Prove the complete guest journey — room detail → available dates/guests → auth gate → pending reservation → payment outcome → visible confirmation — holds end-to-end, and that the availability invariant genuinely prevents a double-booking; mirrors T-2T01/T-3T01/T-4T01/T-5T01's role as this phase's exit gate.
- **Method:** One integration test driving: an authenticated guest reserves a room for an available range → a `pending` row exists → simulated payment succeeds → the row is `paid` and appears in `/account/reservations` → a second guest attempting to reserve the *same* now-`paid` range for the same room is rejected by `isRoomAvailable`. A second scenario: simulated payment fails → the row is `payment_failed` and the range remains available to a different guest. Then the full quality gate: `pnpm test`, `pnpm exec biome check .`, `pnpm exec tsc --noEmit`, `pnpm exec fallow audit --format json` (expect the `gate: "new-only"` attribution clean, and — per Phase 5's own T-5T01 finding — the raw top-level `verdict` field itself should read `"pass"` if no dead exports are left stranded), plus a live dev-server check of the hotel profile page (room popup opens, dates/guests selectable) and `/account/reservations` (this project's standing constraint: stop the dev server before `pnpm test`).
- **Status:** Todo
