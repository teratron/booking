---
phase: 2
name: "Identity & Back Office"
status: Todo
subsystem: "src/lib/auth/, src/app/api/auth/, src/app/(marketing)/, src/app/admin/, src/app/api/admin/"
requires:
  - "Phase 1 — Platform Foundation (scaffold, Drizzle schema with Better-Auth-shaped account tables, db client, layout shell)"
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 2 Tasks — Identity & Back Office

**Phase:** 2
**Status:** Todo
**Strategic Goal:** Every actor is an authenticated account carrying a
`guest | owner | admin` role, and an operator can approve or reject
externally-submitted content through a back office — the two things Phases 3–6
gate on.

## Track Structure

Honest execution shape: `A → B01 → (B02–B03 ‖ C01–C03) → T`.

- **Track A** (auth core) is the critical path — both other tracks import from it.
- **B01** (layout split) is a prerequisite for Track C, not merely part of Track B:
  the admin surface must not render inside the marketing chrome, and B02/B03 touch
  the same header/layout files, so doing it first prevents two tracks colliding on
  `layout.tsx` and `header.tsx`.
- After A and B01, **B02–B03** (auth UI) and **C01–C03** (back office) touch
  genuinely disjoint files and can run in parallel.

## Re-decomposition note

The previous version of this file carried a single `Blocked [!]` placeholder
(`T-2C01`) because `l2-third-party-integrations.md` §5.3 specified AdminJS, which
cannot mount in a Next.js App Router application. That spec was amended to v0.2.0
(react-admin via shadcn-admin-kit), so the blocker is resolved and Track C is now
decomposed into real work. Task IDs were renumbered rather than `.N`-suffixed:
nothing in Phase 2 had executed, so no `Changes` field, commit, or downstream
reference points at the old IDs and no traceability is lost.

## Atomic Checklist

- [x] [T-2A01] Install Better Auth and configure it against the existing schema
- [x] [T-2A02] Mount the Better Auth route handler
- [x] [T-2A03] Wire the three actor roles onto the account record
- [x] [T-2A04] Provide server-side session access and a route-protection helper
- [x] [T-2B01] Split the root layout so the admin surface sheds the marketing chrome
- [ ] [T-2B02] Build the sign-up and sign-in surfaces
- [ ] [T-2B03] Make the shell header session-aware
- [ ] [T-2C01] Build the admin REST surface with mandatory admin authorization
- [ ] [T-2C02] Mount the react-admin back office at `/admin`
- [ ] [T-2C03] Implement the approve and reject actions
- [ ] [T-2T01] Validate the actor-roles and moderation-checkpoint invariants

## Detailed Tracking

### [T-2A01] Install Better Auth and configure it against the existing schema

- **Spec:** l2-third-party-integrations.md §5.1; l2-tech-stack.md §5.6
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `pnpm exec drizzle-kit generate` reports "No schema changes,
  nothing to migrate" — proving Better Auth adopted Phase 1's existing
  `user`/`session`/`account`/`verification` tables rather than demanding its own;
  `pnpm exec tsc --noEmit` exits 0.
- **Handoff:** Every other task in this phase imports this auth instance.
- **Notes:** Config lives in `src/lib/auth/` (the directory `l2-tech-stack.md`
  §5.6 already reserves), using `drizzleAdapter` over the existing
  `src/lib/db/client.ts`. Phase 1 shaped those four tables to Better Auth's
  documented schema precisely so this step is adoption, not migration — if a
  migration *is* emitted, that is a real mismatch to investigate, not something
  to apply. `BETTER_AUTH_SECRET` and `BETTER_AUTH_URL` go in `.env` with
  placeholders committed to `.env.example` (never real values).
- **Changes:** `src/lib/auth/index.ts` — `betterAuth({ database:
  drizzleAdapter(db, { provider: "pg", schema }), emailAndPassword: { enabled:
  true } })`. Verified the current import path before writing anything: the
  package export map (`npm view better-auth exports`) confirms
  `better-auth/adapters/drizzle` is a real subpath of the core `better-auth`
  package — no separate adapter package needed, despite one search result
  suggesting `@better-auth/drizzle-adapter` (which also happens to exist on
  npm, likely a re-export; used the core package's own path instead to avoid
  an unnecessary dependency). `emailAndPassword` enabled now, not deferred to
  T-2A02, since that task's own Verify line needs it live and an auth instance
  with no enabled method would be a non-functional hand-off.
  `BETTER_AUTH_SECRET` generated via `openssl rand -base64 32` (not a
  placeholder string) into `.env` (gitignored); `.env.example` carries a
  generation instruction, not a real value.
  - **Honest scope of this task's own Verify line:** `drizzle-kit generate`
    diffs `schema.ts` against existing migrations — since this task didn't
    touch `schema.ts`, "no changes" was structurally guaranteed and is not by
    itself proof the adapter accepts the schema at runtime. That proof is
    T-2A02's job (an actual sign-up call against a live server). Recorded here
    so "Verify passed" isn't overstated as "adapter confirmed working."
  - `pnpm exec drizzle-kit generate` — exit 0 — "No schema changes, nothing to
    migrate", 14 tables unchanged.
  - `pnpm exec tsc --noEmit`, `pnpm exec biome check .` (36 files), `pnpm test`
    (12 files / 14 tests) — exit 0, no regression.

### [T-2A02] Mount the Better Auth route handler

- **Spec:** l2-third-party-integrations.md §5.1
- **Status:** Done
- **Assignment:** Agent
- **Verify:** against a live `pnpm dev`, `POST /api/auth/sign-up/email` with a
  test credential returns 2xx and creates exactly one row in `user`
  (confirmed via `psql`); `GET /api/auth/get-session` with the returned cookie
  returns that user.
- **Handoff:** Unblocks T-2A04 and, through it, Tracks B and C.
- **Notes:** Use Better Auth's first-party Next.js handler
  (`toNextJsHandler` from `better-auth/next-js`) at
  `src/app/api/auth/[...all]/route.ts` — this is the App-Router-native path, no
  Express shim involved.
- **Changes:** `src/app/api/auth/[...all]/route.ts` — `toNextJsHandler(auth)`
  exporting `GET`/`POST`. This is the task that actually proves T-2A01's
  adapter works at runtime, not just at the schema-file level.
  - `POST /api/auth/sign-up/email` — HTTP 200, returned a `user` object with
    `id`/`email`/`name`. `psql` confirmed exactly one row in `user`, with
    `role` already defaulting to `guest` from Phase 1's schema default (T-2A03
    formally wires this into the session/escalation-guard, but the DB-level
    default was already correct).
  - `GET /api/auth/get-session` with the returned session cookie — HTTP 200,
    returned the same user plus session metadata (`expiresAt`, `token`).
  - Test data cleaned up via `psql` after verification (`DELETE 1` for both
    `session` and `user`); confirmed `count(*) = 0` on `user` afterward.
  - `pnpm exec tsc --noEmit`, `pnpm exec biome check .` (37 files), `pnpm test`
    (12 files / 14 tests) — exit 0, no regression. Dev server stopped after
    verification.

### [T-2A03] Wire the three actor roles onto the account record

- **Spec:** l1-platform-foundation.md §3 (Actor roles); l2-third-party-integrations.md §4, §5.1
- **Status:** Done
- **Assignment:** Agent
- **Verify:** a newly signed-up account has `role = 'guest'` in the database; the
  session object exposes `role`; a test asserts a client-supplied `role` in the
  sign-up payload is **ignored** (does not escalate to `admin`); `pnpm test`
  exits 0.
- **Handoff:** Phase 3 gates submission on `owner`; T-2C01 gates the entire admin
  API on `admin`.
- **Notes:** Declare `role` via Better Auth's `additionalFields` with
  `input: false` so it is server-owned — a self-assignable role field is a
  privilege-escalation hole, and the negative test above is the point of this
  task, not an extra. The column and its `actor_role` enum already exist from
  Phase 1; this makes the auth layer aware of them.
- **Changes:** `src/lib/auth/index.ts` — `user.additionalFields.role`:
  `type: ["guest", "owner", "admin"]`, `defaultValue: "guest"`, `input: false`.
  Verified the exact config shape (array-of-literals for enum, `input: false`
  semantics) against Better Auth's current docs before writing it, since this
  is the actual privilege-escalation guard, not a place to guess syntax.
  `src/lib/auth/index.test.ts` — real Vitest tests calling
  `auth.api.signUpEmail()` directly against the live Postgres (the same
  pattern as T-1B02's `constraints.test.ts`; `auth` is plain server code, not
  a React Server Component, so it isn't subject to the async-RSC testing
  limitation from Phase 1/T-2A01–02). Two tests, both empirical rather than
  trusting the docs' unstated behavior for unknown fields: default role is
  `guest`; a sign-up payload with `role: "admin"` (forced past the TS type
  with `@ts-expect-error`, simulating a caller bypassing the client type)
  still persists as `guest` in the database — the escalation is genuinely
  rejected server-side, not just hidden from the TS-typed client. Both tests
  clean up their own rows via `afterEach`.
  - `pnpm test` — exit 0 — 13 files / 16 tests (2 new), no regression.
    Confirmed `select count(*) from "user"` = 0 after the run.
  - `pnpm exec tsc --noEmit`, `pnpm exec biome check .` (38 files) — exit 0.

### [T-2A04] Provide server-side session access and a route-protection helper

- **Spec:** l2-third-party-integrations.md §5.1, §5.3; l2-tech-stack.md §5.6
- **Status:** Done
- **Assignment:** Agent
- **Verify:** a test asserts the helper returns `null` for an unauthenticated
  request and the user record for an authenticated one; a second asserts a
  role-gated helper rejects a `guest` where `owner` is required; `pnpm test`
  exits 0.
- **Handoff:** Phases 3, 5, and 6 import these instead of re-deriving session
  logic per route. **T-2C01 depends on the role-gated helper specifically** — it
  is the enforcement point for §5.3's authorization requirement.
- **Notes:** Server-side only, in `src/lib/auth/` — business logic belongs in
  the library layer, not in route handlers or components. Build the role check
  as one parameterised helper rather than three near-identical ones.
- **Changes:** `src/lib/auth/session.ts` — three functions:
  `getSessionFromHeaders(Headers)` (thin wrapper over `auth.api.getSession`),
  `getCurrentUser(Headers)`, and `requireRole(Headers, role)` — one
  parameterised gate, not three near-identical ones, per the task's own note.
  Deliberately takes a plain `Headers` object rather than calling `next/headers`
  internally: that keeps this module framework-agnostic and directly testable
  in Vitest (`next/headers` throws outside a real Next.js request scope); call
  sites (T-2B03, T-2C01) supply `await headers()` themselves. Roles are treated
  as three distinct actors, not a hierarchy — `requireRole` is an exact match,
  so an `admin` session does not satisfy an `owner` gate.
  - Test cookie handling: Better Auth signs its session cookie (HMAC), so a
    test can't hand-construct one — `session.test.ts` calls
    `auth.api.signUpEmail({ returnHeaders: true, ... })` to get the real
    `Set-Cookie` the server generates, forwards it as a request `Cookie`
    header, and exercises the helpers against that.
  - `pnpm test` — exit 0 — 14 files / 19 tests (3 new: unauthenticated → null,
    authenticated → user with `role: "guest"`, role-gate rejects
    guest-as-owner but accepts guest-as-guest). No regression. Confirmed
    `select count(*) from "user"` = 0 after the run.
  - `pnpm exec tsc --noEmit`, `pnpm exec biome check .` (40 files) — exit 0.

**Track A (auth core) complete — T-2A01 through T-2A04.**

### [T-2B01] Split the root layout so the admin surface sheds the marketing chrome

- **Spec:** l1-platform-shell.md §3; l2-tech-stack.md §5.6
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `/`, `/privacy-policy`, and an unresolved route still render the
  header and footer (Phase 1's `header.test.tsx` / `footer.test.tsx` and the live
  two-route check still pass); a placeholder `/admin` route renders **without**
  them; `pnpm test` exits 0.
- **Handoff:** Prerequisite for T-2C02 (the admin SPA mounts here) and sequenced
  before T-2B03 so the two do not collide on the same files.
- **Notes:** Phase 1 put `Header`/`Footer` in the root layout, which wraps every
  route — correct then, wrong once an operator back office exists inside the same
  app. Move the marketing routes into a `(marketing)` route group with its own
  layout carrying the chrome; keep the root layout minimal (`<html>`/`<body>`,
  fonts, `NextIntlClientProvider`). Route groups do not change URLs, so `/` and
  `/privacy-policy` stay put. **Watch `not-found.tsx`**: at the root it is the
  global 404; moved inside a group it becomes group-scoped — decide deliberately
  which behavior is wanted and keep Phase 1's verified "404 still renders the
  shell" result true for marketing routes.
- **Changes:** `src/app/(marketing)/layout.tsx` (Header/{children}/Footer),
  `src/app/layout.tsx` reduced to `<html>`/`<body>`/fonts/`NextIntlClientProvider`
  only. `page.tsx` and `privacy-policy/` moved into `(marketing)/` (with their
  tests; fixed one relative import depth that shifted). Added
  `src/app/admin/page.tsx` as a placeholder (T-2C02 replaces it).
  - **Verified before deciding, not assumed:** fetched Next.js's own current
    `not-found.js` reference. It states plainly that the *root* `app/not-found.js`
    "handle[s] any unmatched URLs for your whole application" — a
    `(marketing)`-scoped one would not. Kept `not-found.tsx` at the app root
    (not moved into `(marketing)`) to preserve that global-catch-all guarantee,
    and since it therefore no longer nests under `(marketing)/layout.tsx`, it
    now imports and renders `Header`/`Footer` directly itself.
  - **A curl+grep false alarm, caught before it became a wrong conclusion:**
    the first pass checked `/admin`'s raw HTML for header/footer marker text
    and found them — appearing to fail this task's own core requirement. The
    actual rendered `<body>` held only `<div>Admin</div>`; the matches were
    inside Next.js's RSC "flight" payload (`self.__next_f.push(...)`), which
    pre-serializes the `not-found` boundary's content for instant client-side
    transitions regardless of whether it's currently displayed — not the
    visible DOM. Re-verified via chrome-devtools MCP's accessibility-tree
    snapshot (real rendered content, immune to this false positive) for all
    four cases: `/` and `/privacy-policy` show full chrome; a genuinely random
    nested unmatched path shows the global 404 *with* chrome (proving the
    root-`not-found.tsx` decision above actually works, not just compiles);
    `/admin` shows only "Admin", no chrome.
  - `pnpm test` — exit 0 — 14 files / 19 tests, no regression (import-path fix
    was the only source change required beyond the move itself).
  - `pnpm exec tsc --noEmit` — one failure on first run, fully explained: a
    stale `.next/dev/types/validator.ts` (Next's own generated cache) still
    referenced the old `src/app/page.tsx` location. Deleted `.next/`
    (gitignored build cache, not source) and reran clean.
  - `pnpm exec biome check .` (42 files) — exit 0.

### [T-2B02] Build the sign-up and sign-in surfaces

- **Spec:** l2-third-party-integrations.md §5.1; l2-tech-stack.md §5.7
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** against a live `pnpm dev`, submitting the sign-up form creates an
  account and redirects to an authenticated state; signing out clears the
  session; signing back in with the same credential succeeds. All copy resolves
  from `messages/ru.json` and `pnpm test` (including the hardcoded-copy guard)
  exits 0.
- **Handoff:** Phase 3's owner-gated intake redirects unauthenticated visitors
  here.
- **Notes:** **No Figma frame evidences an auth surface** — the inventoried
  frames cover the shell, catalog, hotel, rooms, onboarding, and blog, but no
  sign-in/sign-up screen. Build these minimally from existing shadcn primitives
  per §5.7 (composable, variant-driven, no bespoke one-offs) and treat the visual
  design as provisional pending design input. Do not invent brand styling.

### [T-2B03] Make the shell header session-aware

- **Spec:** l1-platform-shell.md §3; l2-third-party-integrations.md §5.1
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** against a live `pnpm dev`, the header shows a sign-in affordance
  when unauthenticated and an account/sign-out affordance when authenticated, on
  both `/` and a nested route; the existing header/footer invariant tests still
  pass under `pnpm test`.
- **Handoff:** Establishes how later surfaces read session state in the shell.
- **Notes:** The header is an async Server Component reading the session via
  T-2A04's helper. Keep the session-dependent interactive bits in a small Client
  Component receiving resolved props — the container/presentational split Phase 1
  established, since `next-intl/server` and session reads are both server-only.

### [T-2C01] Build the admin REST surface with mandatory admin authorization

- **Spec:** l2-third-party-integrations.md §5.3
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** against a live `pnpm dev` — an unauthenticated
  `GET /api/admin/hotel` is rejected (401/403); the same request with a
  `guest`-role session is rejected; with an `admin` session it returns 200 and a
  `Content-Range: hotel 0-9/N` header. `GET /api/admin/hotel?filter={"status":"pending"}&range=[0,9]&sort=["createdAt","DESC"]`
  returns only pending rows, each carrying an `id`. `GET /api/admin/hotel/{id}`
  returns that single record. A test covers the unauthenticated and wrong-role
  rejections under `pnpm test`.
- **Handoff:** T-2C02's data provider consumes exactly this contract; §5.3's own
  ordering note requires this to exist before the screens, since the kit's
  guessers scaffold from live responses.
- **Notes:** Implements the `ra-data-simple-rest` dialect over the existing
  Drizzle client for the four moderated resources (hotel, room, review, article):
  `GET /resource?sort=["field","ASC"]&range=[start,end]&filter={...}` returning
  `Content-Range: resource start-end/total`; `GET /resource/:id`;
  `PUT /resource/:id`. **Authorization is the point of this task, not a detail** —
  §5.3 makes it a hard requirement precisely because the admin UI is client-side
  and therefore cannot be the security boundary. Gate every method with T-2A04's
  role helper; an ungated handler leaves the moderation checkpoint bypassable by
  direct request.

### [T-2C02] Mount the react-admin back office at `/admin`

- **Spec:** l2-third-party-integrations.md §5.3
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** against a live `pnpm dev` with an `admin` session, `/admin` lists
  pending hotels, rooms, reviews, and articles, and a record opens in a detail
  view; the page renders without the marketing header/footer (T-2B01); a
  non-admin visiting `/admin` cannot read data (the API rejects it — verified in
  T-2C01). `pnpm exec biome check .` and `pnpm exec tsc --noEmit` exit 0.
- **Handoff:** T-2C03 attaches the approve/reject actions to these resources.
- **Notes:** `shadcn-admin-kit` (marmelab, built on `ra-core`) as a `"use client"`
  surface, with `ra-data-simple-rest` pointed at `/api/admin`. Two things to
  decide explicitly rather than drift into:
  1. **Admin locale.** react-admin ships its own i18n (`ra-i18n-polyglot`),
     separate from the app's `next-intl`. `ra-language-russian` exists but sits
     at 5.4.x against `ra-core` 5.12+ — wire it and check for untranslated
     framework strings rather than assuming full coverage. Operator-facing
     English is acceptable if the gap is large; record whichever is chosen.
  2. **Bundle scope.** The kit pulls `react-router` and `@tanstack/react-query`
     client-side. That is fine for an operator route but must not leak into the
     marketing bundle — confirm the split holds.

### [T-2C03] Implement the approve and reject actions

- **Spec:** l1-platform-foundation.md §3 (Content moderation checkpoint); l2-third-party-integrations.md §4, §5.3
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** approving a `pending` hotel sets `status = 'published'` in the
  database (confirmed via `psql`); rejecting sets `status = 'rejected'` and
  persists the operator's reason into `moderation_reason`; both actions are
  rejected for a non-admin session. A test covers the status transition and the
  authorization rejection under `pnpm test`.
- **Handoff:** Phase 3's intake flow depends on this to move owner submissions
  out of `pending`; Phase 4 renders only `published` rows.
- **Notes:** The `status` and `moderation_reason` columns already exist on
  hotel/room/review from Phase 1 (T-1B02) — this task writes them, it does not
  add them. Enforce the transition server-side in the Route Handler, not in the
  admin UI, for the same reason as T-2C01. Articles have no `status` column by
  design (admin-authored content is exempt from the checkpoint per
  `l1-content-publishing.md`) — they appear in the back office for authoring and
  editing, not for approval.

### [T-2T01] Validate the actor-roles and moderation-checkpoint invariants

- **Goal:** Verify this phase implements both `l1-platform-foundation.md` §3
  invariants it owns — actor roles, and the content moderation checkpoint.
- **Method:** Assert all three `actor_role` values are assignable and readable
  through the auth layer (not merely present as an enum in the schema); assert a
  submitted listing is attributable to its owner via `hotel.owner_id`; assert the
  escalation guard from T-2A03 holds. For the checkpoint: assert externally
  originated content is not publicly readable while `pending`, that only an
  `admin` can transition it, and that the transition is enforced server-side
  (a direct API call with a non-admin session is rejected). Run `pnpm test`, then
  `pnpm exec biome check .`, `pnpm exec tsc --noEmit`, and
  `pnpm exec fallow audit --format json` (expecting 0 circular dependencies and
  0 boundary violations) as the phase exit gate.
- **Status:** Todo
- **Notes:** Unlike the previous draft of this phase, moderation **is** in scope
  for validation now that §5.3 is implementable — both invariants must be proven,
  and neither may be reported as satisfied on the strength of the other.
