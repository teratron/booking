---
phase: 3
name: "Property Onboarding"
status: Done
subsystem: "src/lib/property-onboarding/, src/app/add-hotel/, src/app/api/upload/, src/lib/db/seed-amenities.ts"
requires:
  - "Phase 1 — Platform Foundation (hotel/room/amenity/media schema, moderation status column, hotel/room hierarchy FK)"
  - "Phase 2 — Identity & Back Office (auth session helpers, actor roles, admin approve/reject endpoints the exit-gate test round-trips through)"
provides:
  - "Shared amenity taxonomy seeded (27 rows, 5 groups) via an idempotent pnpm db:seed script — src/lib/db/seed-amenities.ts"
  - "src/lib/property-onboarding/{schema,submit-listing,actions,queries}.ts — the schema/persistence/Server-Action/read-query split every future Server-Action-backed Client Component in this project should follow"
  - "submitHotelListingCore / updateHotelListingCore — transactional insert and edit-resubmit, auth+ownership+status gated, guest-to-owner promotion on first submit"
  - "src/app/api/upload/route.ts — Vercel Blob client-upload token endpoint, session-gated"
  - "/add-hotel (dashboard), /add-hotel/new (intake form), /add-hotel/[id]/edit (edit-and-resubmit) — full owner-facing property onboarding flow, sharing one HotelListingForm component"
  - "Full submit -> reject -> edit -> resubmit -> ownership-gated lifecycle proven end-to-end, round-tripping through Phase 2's real admin reject endpoint"
key_files:
  created:
    - "src/lib/db/seed-amenities.ts, seed-amenities.test.ts"
    - "src/lib/property-onboarding/schema.ts, submit-listing.ts (+.test.ts), actions.ts, queries.ts (+.test.ts), update-listing.test.ts, lifecycle.test.ts"
    - "src/app/api/upload/route.ts (+.test.ts)"
    - "src/app/add-hotel/layout.tsx, page.tsx (+.test.ts), hotel-listing-form.tsx (+.test.tsx)"
    - "src/app/add-hotel/new/page.tsx (+.test.ts)"
    - "src/app/add-hotel/[id]/edit/page.tsx (+.test.ts)"
  modified:
    - "src/lib/db/client.ts (exported pool; test-only pool.max cap under process.env.VITEST)"
    - "src/lib/test-helpers/auth.ts (deleteTestUsers now cascades owned hotels first)"
    - "messages/ru.json (AddHotelForm, AddHotelDashboard namespaces)"
    - "package.json (tsx, zod, @vercel/blob, @hookform/resolvers dependencies; db:seed script)"
    - ".fallowrc.jsonc (dotenv added to ignoreDependencies)"
    - "vitest.config.ts (testTimeout: 15000)"
patterns_established:
  - "Server Action modules must be split three ways: a pure schema/types file (client-safe), a persistence file (db logic), and a dedicated actions file (file-level \"use server\") — mixing them breaks the moment a Client Component imports the file, either outright (inline \"use server\" in a mixed file) or silently (the bundler pulling db/pg/Node builtins into the client bundle). Only caught by a live dev-server request, never tsc or Vitest."
  - "A shared form component grows an optional {entityId, defaultValues} pair rather than being duplicated for create vs. edit — same validation, same submit UI, the Server Action called is the only thing that branches."
  - "Full-resubmission edits replace child rows (delete + re-insert) rather than diff — simpler and correct when the form always carries complete desired state, not a partial patch."
  - "Ownership/authorization failures on a per-resource route resolve to 404, not 403, when the caller has no legitimate reason to know the resource exists (contrast with the admin surface's role-gated 403, where the caller IS a legitimate actor probing their own permission boundary)."
  - "Vitest test-file Postgres pool sizing must account for aggregate connection pressure across all concurrently-run files, not just one file's own needs — cap pool max under process.env.VITEST rather than letting pg's default (10) multiply by the file count."
duration_minutes: ~
---

# Stage 3 Tasks — Property Onboarding

**Phase:** 3
**Status:** Done
**Strategic Goal:** An authenticated actor can submit a hotel with its room
inventory, the submission is attributed to them and enters the moderation
queue as `pending`, and a `rejected` submission can be edited and resubmitted
— the intake half of the moderation loop Phase 2 built the review half of.

## Track Structure

Honest execution shape: `(A01 ‖ A02 ‖ B01 ‖ C01) → B02 → B03 → C02 → T`.

- **Track A** (data + persistence foundation) and **B01** (upload route) are
  mutually file-disjoint and have no dependency on each other — all
  dispatchable immediately.
- **C01** (owner dashboard, read-only) queries the Phase 1 `hotel` table
  directly and uses the existing Phase 2 `getCurrentUser` helper — it does not
  import T-3A02's Server Action module at all, so it is genuinely dispatchable
  in the first parallel batch, not merely "as soon as A02 lands."
- **B02** (the intake form scaffold) needs A02's Server Action and A01's seed
  data to render real amenity checkboxes.
- **B03** (media upload widget) is split out from B02 rather than bundled
  into it: `@role:planner` review flagged the original single-task B02 as
  optimism-biased — multi-section fields, a repeatable room array, grouped
  checkboxes, upload-widget wiring, and an auth-redirect test in one task
  bundles too much independently-verifiable surface. B03 depends on both
  B02 (the form to attach into) and B01 (the route to call).
- **C02** (edit/resubmit) reuses the full B02+B03 form pre-filled, so it is
  sequenced after both, not parallel with either.

## Decisions Carried Into Decomposition

<!-- Elective forks resolved here per magic.md §7 (DA-8/DA-9) — narrated, not asked. -->

- [DR] The `/add-hotel` auth gate checks only "is authenticated" (any role),
  not `role === "owner"` — a `guest` becomes attributed as a submission's
  owner by the act of submitting, and is promoted `guest → owner` server-side
  at that point. Criterion: `l1-property-onboarding.md` §5.2's lifecycle
  diagram gates on "Owner account? → Login/register", not on a pre-existing
  role; T-2A04's "no self-service escalation" invariant concerns a
  direct role-picker in the sign-up form, not a role change that is the
  side effect of a legitimate, controlled business action. Admins are left
  as `admin` (roles are not a hierarchy, per T-2A04). (Override: `/magic.spec`
  to amend `l2-third-party-integrations.md` §5.1 if a stricter pre-provisioned
  owner gate is actually intended.)
- [DR] "Saved as a draft" (§3 Core Invariants) requires no schema change and
  no fourth moderation-status value. Criterion: `hotel`/`room` media live in
  separate child tables (`hotelMedia`/`roomMedia`) with no NOT NULL
  requirement on the parent row, so a `pending` row can already exist with
  zero media — the existing `pending | published | rejected` enum already
  expresses "saved, awaiting completeness-checked review." Completeness
  before publication is an admin-judgment gate at approve-time, not a
  separate persisted state. (Override: `/magic.spec` if a true
  save-without-submitting draft state is actually wanted.)
- [DR] Room sections are repeatable within one submission (one hotel + N
  rooms per form), not one submission per room. Criterion: this is the
  literal reading of §5.1's own diagram label ("Room section (repeatable —
  see Drawbacks)"); the Drawbacks paragraph flags it as open only because the
  static Figma frame alone couldn't prove it, not because the diagram
  disagrees with it. (Override: `/magic.spec` to record a different resolution
  if that reading is wrong.)
- [DR] Media storage is **Vercel Blob** (`@vercel/blob`), using its
  client-upload token pattern (browser uploads directly to blob storage; the
  Next.js route only issues/validates the token) rather than proxying file
  bytes through a Server Action. Criterion: `hotelMedia.url`/`roomMedia.url`
  being plain `text` columns (a Phase 1 decision) already implies "upload
  somewhere, store the resulting URL"; Vercel Blob needs no local
  infrastructure (no MinIO/S3 container) and pairs natively with the Next.js
  deployment target already implied by `l2-tech-stack.md` §5.1. Requires a
  new `BLOB_READ_WRITE_TOKEN` env var — added to `.env.example`, not
  `.env` (secret, user-provisioned). (Override: `/magic.spec` to add a
  Media Storage entry to `l2-tech-stack.md` if a different provider is
  preferred.)
- [DR] Form handling for the intake form uses **react-hook-form +
  `useFieldArray`** (repeatable rooms) with **zod** schema validation, a
  deliberate departure from the plain-`FormData`-plus-`useState` pattern
  Phase 2's 3-field sign-up/sign-in forms used. Criterion: `react-hook-form`
  is already a dependency (Phase 2, transitively via the admin surface);
  `zod` is a new addition. The sign-up form's simplicity didn't justify the
  library; a multi-section, repeatable-room, grouped-checkbox form does.

## Atomic Checklist

- [x] [T-3A01] Seed the shared amenity taxonomy
- [x] [T-3A02] `submitHotelListing` Server Action — auth gate, owner promotion, transactional insert
- [x] [T-3B01] Media upload route (Vercel Blob client-upload tokens)
- [x] [T-3B02] `/add-hotel/new` intake form
- [x] [T-3B03] Media upload widget wired into the intake form
- [x] [T-3C01] `/add-hotel` owner dashboard (my submissions)
- [x] [T-3C02] Edit and resubmit a rejected submission
- [x] [T-3T01] Validate the submission and edit/resubmit lifecycle

## Detailed Tracking

### [T-3A01] Seed the shared amenity taxonomy

- **Spec:** l1-property-onboarding.md §5.1 (hotel + room amenity groupings); l1-room-reservation.md §3 (room/bathroom/bedroom/general groups); schema.ts `amenityGroupEnum`
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `pnpm db:seed` run twice in a row; `psql` `select count(*) from amenity` identical after both runs (idempotency); a `seed-amenities.test.ts` asserting the same via the test DB.
- **Handoff:** T-3B02 renders these rows as grouped checkboxes; no other task blocks on this one.
- **Notes:** `amenity` table exists (Phase 1) but is currently empty — no rows to render without this. Group by `amenityGroupEnum` (`hotel | room | bathroom | bedroom | general`); use representative, not exhaustive, entries per group (this is seed data, not a spec-mandated fixed list).
- **Changes:** Added `src/lib/db/seed-amenities.ts` (27 entries across all 5 groups, dedup-by-name idempotency — no schema migration, per the phase's no-draft-state decision) + `seed-amenities.test.ts` (2 tests: idempotent re-run, all 5 groups present) + `pnpm db:seed` script (`tsx`, new dev dependency) + exported `pool` from `db/client.ts` so the standalone script can close its connection. Verified: `pnpm db:seed` run twice — `27 inserted, 0 already present` then `0 inserted, 27 already present`; `psql select count(*) from amenity` = 27 after both runs; full suite `pnpm test` 22 files/42 tests green; `tsc --noEmit` and `biome check` clean.

### [T-3A02] `submitHotelListing` Server Action — auth gate, owner promotion, transactional insert

- **Spec:** l1-property-onboarding.md §3 Core Invariants, §5.2 Submission Lifecycle; l1-platform-foundation.md §4 (actor roles, moderation checkpoint)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `submit-listing.test.ts` — unauthenticated caller rejected; authenticated `guest` caller ends up `role: "owner"` after submit (read back via `getCurrentUser`); resulting hotel row has `status: "pending"` and `ownerId` equal to the caller; rooms/amenity-links/media rows are all attributed to the same hotel in one transaction (partial insert never observed on a forced failure case).
- **Handoff:** B02 (form) and C01 (dashboard) both read/write through this module; C02 extends it with an update path.
- **Notes:** Zod schema for hotel fields + `rooms: RoomInput[]` (min 1). Reuses `lib/auth`'s `getCurrentUser`, not a new auth mechanism. See Decisions above for the promotion rule and the no-draft-state resolution.
- **Changes:** Added `src/lib/property-onboarding/submit-listing.ts` — `submitHotelListingCore(requestHeaders, input)` (testable core, mirrors `session.ts`'s explicit-Headers pattern) + `submitHotelListing(input)` (thin `"use server"` wrapper calling `await headers()`) + zod schemas (`hotelListingInputSchema`, room/media sub-schemas) + 4 small insert helpers (`linkAmenities`, `insertHotelMedia`, `insertRoomMedia`, `insertRoomWithRelations`) extracted after `fallow audit` flagged the first draft's inline transaction body at CRAP 37.1 (threshold 30) — extraction dropped it to 0 introduced complexity findings. Added `submit-listing.test.ts` (5 tests: unauthenticated rejected, invalid input rejected without a DB write, guest→owner promotion + pending status + owner attribution, full room/amenity-link/media persistence in one transaction, forced FK-violation proves the transaction rolls back the hotel insert too — confirmed via `rejects.toThrow()`, not a graceful result, since drizzle's `db.transaction` rethrows). Also strengthened the shared `deleteTestUsers` test helper to delete a user's owned hotels first (`hotel.owner_id` has no `onDelete` cascade) — discovered when a crashed test run left an orphaned hotel+user pair that broke a later run's cleanup; fixed at the shared-helper level since every remaining Phase 3 test file will hit the same issue. Fixed `.fallowrc.jsonc` (added `dotenv` to `ignoreDependencies`, same false-positive class as the existing `tailwindcss` entry) and removed a now-stale `fallow-ignore-file` comment from `seed-amenities.ts` (the file became genuinely reachable once `db:seed` was added to `package.json` scripts). Verified: `tsc --noEmit` clean; `pnpm test` 23 files/47 tests green; `fallow audit --format json` — `complexity_introduced: 0`, `dead_code_introduced: 2` (both expected-transient: `hotelListingInputSchema` export and the `submitHotelListing` server action are unused until T-3B02 wires them into the form — no suppression added, since B02 is the very next task and will resolve both naturally).
  **Superseded by T-3B02**: wiring this module into the Client Component form broke the build (inline `"use server"` in a file also carrying `db`-touching helpers). T-3B02 split this file three ways — the schemas moved to a new `schema.ts`, the `submitHotelListing` wrapper moved to a new `actions.ts`, and this file kept only `submitHotelListingCore` and the persistence helpers. See T-3B02's Changes for the full explanation; noted here so this entry doesn't describe a file layout that no longer exists.

### [T-3B01] Media upload route (Vercel Blob client-upload tokens)

- **Spec:** l1-property-onboarding.md §5.1 (photo/video upload, both hotel- and room-level)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `pnpm add @vercel/blob`; `upload/route.test.ts` — unauthenticated `POST` → 401; authenticated `POST` returns a well-formed client-token payload. Live round-trip to actual blob storage is verified manually only if `BLOB_READ_WRITE_TOKEN` is present in the local `.env`; otherwise explicitly noted as unverified in this task's Changes field (same honesty standard as T-2C03's browser-tool gap), not claimed.
- **Handoff:** B03 calls this route from inside the intake form.
- **Notes:** Add `BLOB_READ_WRITE_TOKEN=` (with a comment, no value) to `.env.example`. Restrict accepted content types to `image/*` and `video/*` and cap size.
- **Changes:** Added `src/app/api/upload/route.ts` — `POST` gated on `getCurrentUser(request.headers)` (401 if absent), then `@vercel/blob/client`'s `handleUpload` issues the client token (`allowedContentTypes: ["image/*","video/*"]`, 100MB cap, `tokenPayload` carries the caller's user id). `onUploadCompleted` deliberately omitted — Vercel's webhook callback isn't reachable in local dev, and no DB write is needed at upload time (T-3A02's Server Action persists the URL when the form submits, not this route). Added `BLOB_READ_WRITE_TOKEN` to `.env.example`. Added `upload/route.test.ts` (2 tests: 401 unauthenticated, 200 + well-formed `clientToken` for an authenticated request). **Correction to this task's own Verify line**: read `@vercel/blob@2.6.1`'s actual source (`generateClientTokenFromReadWriteToken` in `chunk-A7B3MEJ5.cjs`) rather than assuming — client-token generation is a local HMAC signature over the read-write token, not a network call to Vercel, so a syntactically-valid fake token (`vercel_blob_rw_teststore123_testsecret`) exercises the real success path deterministically; no real Vercel Blob store or manual verification gate was actually needed. Hit one real environment issue along the way: the SDK's token-generation guard throws if a `window` global is present (anti-leak-to-browser-bundle check) — the project's default `jsdom` Vitest environment provides one, so the test file overrides it with a `// @vitest-environment node` docblock. Verified: `tsc --noEmit` clean; `pnpm test` 24 files/49 tests green; `fallow audit --format json` — `complexity_introduced: 0`, `dead_code_introduced: 2` (same two T-3A02 transients, unchanged by this task, resolved by T-3B02).

### [T-3B02] `/add-hotel/new` intake form

- **Spec:** l1-property-onboarding.md §5.1 Submission Sections (full field list)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `pnpm add zod`; component test (Vitest + Testing Library) — fills hotel fields + adds a second room block via the repeatable control + submits → mocked Server Action called with the expected shape; a separate test asserts an unauthenticated request to the page redirects to `/sign-in`.
- **Handoff:** B03 attaches the media widget into this form; C02 reuses the combined B02+B03 component pre-filled for the edit/resubmit flow.
- **Notes:** Built from existing `components/ui/` primitives (Input, Label, Card, Button, Checkbox) per `l2-tech-stack.md` §5.7 — no bespoke one-off inputs. Amenity checkboxes grouped by the four/five taxonomy groups from T-3A01. Media fields in this task's form state are plain URL-array placeholders — B03 replaces the placeholder with the real upload widget, it does not restructure the form's data shape.
- **Changes:** Added `src/app/add-hotel/layout.tsx` (reuses `Header`/`Footer`, matching `(marketing)/layout.tsx` — the footer already links `/add-hotel`, so this route needs the same site chrome, not the admin's chrome-less treatment), `src/app/add-hotel/new/page.tsx` (Server Component: `getCurrentUser(await headers())` gate → `redirect("/sign-in")`; fetches `amenity` rows and passes them to the form), `src/app/add-hotel/new/hotel-listing-form.tsx` (react-hook-form + `useFieldArray` for repeatable rooms, grouped `AmenityCheckboxGroup`, placeholder `HotelMediaEditor`/`RoomMediaEditor`), plus component/page tests. Added `AddHotelForm` namespace to `messages/ru.json` (~35 keys) — first component in the codebase to call `useTranslations()` directly rather than receiving translated props, a deliberate deviation from the sign-up/sign-in pattern justified by field count (documented in the Decisions section above).
  **Two real bugs caught only by dev-server verification, not by `tsc`/tests:**
  1. `starCategory` (optional) registered with `valueAsNumber: true` — an empty input produces `NaN`, not `undefined`, which fails zod's `.optional()` and silently blocked every submission. Fixed with `setValueAs: (v) => v === "" ? undefined : Number(v)`.
  2. **Architectural**: the original single `submit-listing.ts` mixed a `"use server"`-annotated function with plain server-only helpers (importing `db`, hence `pg`) in one file, then a Client Component imported it for its zod schema. Next.js rejected the inline `"use server"` outright (`/add-hotel/new` → 500); fixing that by moving the wrapper into its own `actions.ts` file still left the same file supplying the client-imported schema *and* the `db`-touching persistence code, so the bundler pulled `pg` (and Node builtins `net`/`tls`/`fs`/`dns`) into the client bundle anyway. Resolved by splitting into three files: `schema.ts` (pure zod, zero server-only imports — client-safe), `submit-listing.ts` (persistence logic, imports `schema.ts` + `db`, server-only), `actions.ts` (file-level `"use server"`, imports `submitHotelListingCore` from `submit-listing.ts` and types from `schema.ts`). This is the pattern every future Server-Action-backed Client Component in this project must follow — recorded in STATE.md Blocking Constraints.
  **Test-infra fix surfaced along the way**: the full suite intermittently timed out on one otherwise-instant test only when running alongside the other ~25 files (isolated `vitest run <file>` always passed). Root cause: every test file gets its own Postgres `Pool` (module isolation), and pg's default `max: 10` per pool means ~25 files can collectively request far more than Postgres's `max_connections` (100). Fixed with `pool: { max: process.env.VITEST ? 3 : undefined }` in `db/client.ts` (test-only, production keeps pg's default) and a global `testTimeout: 15000` in `vitest.config.ts` (the 5s default has too little headroom once ~25+ files compete for CPU). Also discovered `pnpm test -- <path>` does not reliably scope to one file in this project — `pnpm exec vitest run <path>` does.
  Verified: `tsc --noEmit` clean; `pnpm test` 26 files/52 tests green (twice, to confirm the concurrency fix held); `fallow audit --format json` — `verdict: "pass"`, `complexity_introduced: 0`, `dead_code_introduced: 0` (T-3A02's two transients now resolved); live dev-server check — unauthenticated `GET /add-hotel/new` → `307` to `/sign-in`; a real signed-up test user (cleaned up after) → `200` with the form title rendered, no error boundary in the response HTML.

### [T-3B03] Media upload widget wired into the intake form

- **Spec:** l1-property-onboarding.md §5.1 (photo/video upload, both hotel- and room-level)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** component test — selecting a file calls T-3B01's route (mocked), a successful response adds the returned URL into the form's media array (visible as a preview item), a remove control drops it back out; form submission payload includes the accumulated URLs alongside the rest of T-3B02's fields.
- **Handoff:** C02 reuses this widget as part of the combined form.
- **Notes:** Split from T-3B02 per the Planning Audit (see Track Structure) — isolates the one part of the form with real async/network behavior (upload progress, failure, retry) from the otherwise-synchronous field/validation surface.
- **Changes:** Replaced `HotelMediaEditor`/`RoomMediaEditor`'s placeholder URL-text-input with real `<input type="file">` widgets calling `@vercel/blob/client`'s `upload(file.name, file, {access:"public", handleUploadUrl:"/api/upload"})` — the browser uploads straight to Vercel Blob, this app's server only ever issues the token (T-3B01). Hotel media infers `photo`/`video` from the returned `contentType`; room media stays photo-only per the existing schema. Added `aria-label` to both file inputs (using the section's own translated label) — a genuine accessibility improvement, and what let the tests locate them via `getByLabelText` without a separate test-only hook. Added 2 tests to `hotel-listing-form.test.tsx` (mocking `@vercel/blob/client`): upload → preview appears with the returned URL → remove control clears it; a room-level upload's URL ends up in the submitted payload's `rooms[0].mediaUrls`. Verified: `tsc --noEmit` clean; `pnpm test` 26 files/54 tests green; `fallow audit --format json` — `verdict: "pass"`, 0 introduced dead code/complexity/circular-deps/boundary-violations; live dev-server check — authenticated `GET /add-hotel/new` still `200` with the form title rendered after wiring the real `@vercel/blob/client` import into the client bundle (confirms the T-3B02 schema/actions/persistence split holds under a heavier client dependency, not just the original minimal repro).
  **Superseded by T-3C02**: `hotel-listing-form.tsx` and its test moved from `add-hotel/new/` up to `add-hotel/` (shared between `/add-hotel/new` and `/add-hotel/[id]/edit`). File paths in this entry's own prior wording refer to the pre-move location.

### [T-3C01] `/add-hotel` owner dashboard (my submissions)

- **Spec:** l1-platform-foundation.md §4 ("owner can later view its moderation status"); l1-property-onboarding.md §3
- **Status:** Done
- **Assignment:** Agent
- **Verify:** integration test on the query helper (`getOwnerListings(userId)`) — returns only hotels where `ownerId` matches the caller, correct status/reason shape; page-level test confirms unauthenticated access redirects to `/sign-in`.
- **Handoff:** links a `rejected` row into T-3C02's edit route.
- **Notes:** Server Component, no client interactivity needed beyond the existing Link/redirect primitives.
- **Changes:** Added `src/lib/property-onboarding/queries.ts` — `getOwnerListings(ownerId)`, plain `db.select` filtered by `hotel.ownerId`, ordered newest-first. No schema/actions split needed (server-only, this page never gets imported by a Client Component). Added `src/app/add-hotel/page.tsx` — same `getCurrentUser(await headers())` gate as T-3B02, lists the caller's hotels with a status `Badge` (`pending→secondary`, `published→default`, `rejected→destructive`), shows `moderationReason` and an edit link for rejected rows, and a "new listing" button using the Base UI `Button`'s `render={<Link .../>}` polymorphic pattern (already used once in the admin surface's `authentication.tsx`, now the first first-party usage). Added `AddHotelDashboard` namespace to `messages/ru.json`. Added `queries.test.ts` (2 tests: only the caller's own hotels are returned even with another owner's hotel present; status/moderationReason shape correct for a rejected row) and `page.test.ts` (unauthenticated → redirect, mirroring T-3B02's page test). Verified: `tsc --noEmit` clean; `pnpm test` 28 files/57 tests green; `fallow audit --format json` — `verdict: "pass"`, 0 introduced dead code/complexity/circular-deps/boundary-violations; live dev-server check — unauthenticated `GET /add-hotel` → `307` to `/sign-in`; authenticated → `200` with the dashboard title rendered.

### [T-3C02] Edit and resubmit a rejected submission

- **Spec:** l1-property-onboarding.md §5.2 Submission Lifecycle ("Rejected + reason → Returned to owner for edits → Owner fills hotel + room sections")
- **Status:** Done
- **Assignment:** Agent
- **Verify:** integration test — owner of a `rejected` hotel can resubmit (`status` flips back to `pending`, `moderationReason` cleared); a different authenticated user attempting the same `id` gets 403/404; attempting to edit a `pending` or `published` hotel is rejected with a clear reason (not silently a no-op).
- **Handoff:** feeds T-3T01's full-lifecycle test.
- **Notes:** Extends T-3A02's module with an `updateHotelListing` variant rather than a parallel implementation — same validation schema, different write path (update, not insert; ownership + status guard before either). UI reuses the combined T-3B02+T-3B03 form component pre-filled from the existing row, not a second form.
- **Changes:** Added `updateHotelListingCore(requestHeaders, hotelId, input)` to `submit-listing.ts` — auth → existence (`NOT_FOUND`) → ownership (`FORBIDDEN`) → status-is-rejected (`NOT_EDITABLE`) → validation, in that order; on success, deletes the hotel's rooms (cascades to room_amenity/room_media) and hotel-level amenity/media links, then updates the hotel row in place (`status: "pending"`, `moderationReason: null`) and re-inserts children via the same `linkAmenities`/`insertHotelMedia`/`insertRoomWithRelations` helpers T-3A02 built — a full resubmission always carries the complete desired state, so replace-not-diff was the simpler and correct choice. Extended `SubmitListingResult`'s error union in `schema.ts` with `NOT_FOUND | FORBIDDEN | NOT_EDITABLE` (submit's callers only ever see the original two). Added `updateHotelListing(hotelId, input)` to `actions.ts`. Added `getHotelForEdit(hotelId)` to `queries.ts` — reshapes a hotel + its rooms/amenity-links/media back into the same `HotelListingInput` shape the form and Server Actions already use, so the edit page pre-fills the exact component T-3B02/B03 built, not a second form.
  **Moved `hotel-listing-form.tsx` (+ its test) from `add-hotel/new/` up to `add-hotel/`** — now genuinely shared between `/add-hotel/new` and the new `/add-hotel/[id]/edit`, not owned by one route. Added `hotelId?`/`defaultValues?` props: presence of `hotelId` switches the submit handler to `updateHotelListing` and the button label to `resubmitLabel`/`resubmitPendingLabel` (new `ru.json` keys) instead of `submitLabel`/`submitPendingLabel`.
  Added `src/app/add-hotel/[id]/edit/page.tsx` — auth gate, then a single combined check (`!result || wrong owner || status !== "rejected"`) that resolves to `notFound()` for all three cases rather than distinguishing 403 vs 404: a non-owner probing a real hotel ID gets the same response as a genuinely missing one, so the page never confirms a resource's existence to someone with no right to see it.
  Added `update-listing.test.ts` (5 tests: unauthenticated, `NOT_FOUND`, `FORBIDDEN` from a second real signed-up user, `NOT_EDITABLE` for a `pending` hotel, and the full happy path asserting `status`→`pending`, `moderationReason`→`null`, and the room set actually replaced) and `[id]/edit/page.test.ts` (4 tests: unauthenticated redirect, notFound for missing/foreign/non-rejected — mocking `next/headers` with a swappable real-or-empty `Headers` per test, matching the T-3B02 page-test pattern). Extended `hotel-listing-form.test.tsx` with one edit-mode test (pre-filled name value, submits via `updateHotelListing` with `hotelId` as the first argument, `submitHotelListing` never called).
  Verified: `tsc --noEmit` clean; `pnpm test` 30 files/67 tests green; `fallow audit --format json` — `verdict: "pass"`, 0 introduced dead code/complexity/circular-deps/boundary-violations; live dev-server check — inserted a real rejected hotel via `psql`, confirmed `GET /add-hotel/[id]/edit` renders the pre-filled name (`200`), unauthenticated → `307` to `/sign-in`, the same hotel flipped to `pending` → `404` for its own owner, and the dashboard (T-3C01) correctly lists it with the rejection reason and status badge.

### [T-3T01] Validate the submission and edit/resubmit lifecycle

- **Goal:** Prove the full Phase 3 lifecycle end-to-end and confirm the phase's exit gate, mirroring T-2T01's role in Phase 2.
- **Method:** One integration test driving: guest submits (role promotes, hotel `pending`) → admin rejects via the existing Phase 2 `/api/admin/hotel/[id]/reject` endpoint with a reason → owner edits and resubmits via T-3C02 (`pending` again, reason cleared) → a second, unrelated authenticated user is proven unable to read or edit the first owner's submission through T-3C01/T-3C02. Then the full quality gate: `pnpm test`, `pnpm exec biome check .`, `pnpm exec tsc --noEmit`, `pnpm exec fallow audit --format json` (expect `verdict: "pass"`, `circular_dependencies: 0`, `boundary_violations: 0`).
- **Status:** Done
- **Changes:** Added `src/lib/property-onboarding/lifecycle.test.ts` — one test driving the complete cross-phase lifecycle: `submitHotelListingCore` (guest→owner promotion, `pending`) → the real Phase 2 `POST` handler from `app/api/admin/[resource]/[id]/reject/route.ts` (imported and invoked directly, not re-implemented — `rejected` + reason persisted) → `updateHotelListingCore` (back to `pending`, reason cleared, fields actually changed) → a third, unrelated signed-up user proven both unable to see the listing via `getOwnerListings` (T-3C01's scoping) and unable to edit it (`updateHotelListingCore` → `FORBIDDEN`, and the row's content verified unchanged afterward — the hijack attempt wasn't just rejected, it was a true no-op). Full exit gate run: `pnpm test` — 31 files/68 tests green; `pnpm exec biome check .` — clean (0 errors, 208 files, no autofix needed after one formatting pass on the new test file); `tsc --noEmit` — clean; `fallow audit --format json` — `verdict: "pass"`, `dead_code_introduced: 0`, `complexity_introduced: 0`, `circular_dependencies: 0`, `boundary_violations: 0` (the 28 unused-dependency findings visible in the human-readable `fallow audit` output are the same pre-existing shadcn-admin-kit vendor deps from Phase 2, confirmed inherited not introduced — `fallow audit` itself reports "audit gate excluded 28 inherited findings"). Live dev-server regression check across the whole session's surface: `/` → `200`, `/add-hotel` (unauth) → `307`, `/add-hotel/new` (unauth) → `307`, `/admin` → `200` — no fresh errors in the dev server log.
  **Not independently re-verified in this task** (already covered live, per-task, in T-3B02/B03/C01/C02's own Changes entries): the intake form's actual browser rendering, the real Vercel Blob upload round-trip (no `BLOB_READ_WRITE_TOKEN` configured in this dev environment — T-3B01/B03 already documented this gap honestly), and clicking through the edit form's pre-filled fields in an actual browser (chrome-devtools/Playwright MCP tools remained disconnected this entire session, same gap noted in Phase 2's T-2C03).
