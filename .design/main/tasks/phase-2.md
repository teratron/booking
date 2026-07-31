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
- [x] [T-2B02] Build the sign-up and sign-in surfaces
- [x] [T-2B03] Make the shell header session-aware
- [x] [T-2C01] Build the admin REST surface with mandatory admin authorization
- [x] [T-2C02] Mount the react-admin back office at `/admin`
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
- **Status:** Done
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
- **Changes:** `src/lib/auth/client.ts` (`createAuthClient` from `better-auth/react`,
  same-origin — no `baseURL` needed). Scaffolded `Input`, `Label`, `Card` shadcn
  primitives via `pnpm dlx shadcn add` (base-nova preset, matching `button.tsx`/
  `dialog.tsx`) rather than hand-rolling — keeps the design-system surface
  consistent per §5.7. `src/components/sign-up-form.tsx` and `sign-in-form.tsx`
  (Client Components, container/presentational split: `src/app/(marketing)/
  sign-up/page.tsx` and `sign-in/page.tsx` are async Server Components resolving
  `getTranslations` and passing strings as props, following the `Footer`/
  `FeedbackPopup` pattern from Phase 1/T-2B01). Added `SignUp`/`SignIn` keys to
  `messages/ru.json`. Password input carries `minLength={8}`/`maxLength={128}`
  matching Better Auth's documented default bounds. Errors from `onError` show a
  single localized generic message rather than the raw API error text, to avoid
  mixing unlocalized English into the Russian UI.
  - **Verified against a live `pnpm dev` (chrome-devtools MCP):** filled and
    submitted the sign-up form → `POST /api/auth/sign-up/email` 200 → redirected
    to `/` → `GET /api/auth/get-session` confirmed the new user, `role: "guest"`
    (escalation still blocked, per T-2A03). Exercised `POST /api/auth/sign-out`
    directly (no sign-out UI yet — that's `T-2B03`) → `get-session` afterward
    returned `null`. Signed back in with the same credential on `/sign-in` →
    redirected to `/` → session resolved to the same user again. Also checked
    the wrong-password path live: the form stayed in place, showed
    "Не удалось войти. Проверьте почту и пароль." via `role="alert"`, no
    navigation. Test row deleted after verification — `select count(*) from
    "user"` = 0.
  - **Bug caught in my own test setup, not the app:** the project's
    `vitest.config.ts` has no `test.globals: true` and no `setupFiles`, so
    `@testing-library/react`'s automatic `afterEach(cleanup)` never registers
    under Vitest (it self-registers only when it detects a global `afterEach`).
    My first pass at `sign-up-form.test.tsx`/`sign-in-form.test.tsx` had two
    `render()` calls per file with no explicit cleanup; the second test's
    queries silently resolved against the first test's still-mounted DOM,
    making the second (error-path) test fail nondeterministically depending on
    run order. Fixed by importing `cleanup` from `@testing-library/react` and
    `vi.clearAllMocks()` in an explicit `afterEach` in both files. No prior test
    file in the repo has more than one `render()` call per file, so this bug was
    latent, not previously triggered.
  - **Fallow flagged `CardAction`/`CardFooter` in the scaffolded `card.tsx` as
    unused exports.** Rather than pruning the shadcn primitive's public surface
    (fighting the "reusable, don't rebuild from scratch" component-architecture
    principle), added an `ignoreExports` rule in `.fallowrc.jsonc` scoped to
    `src/components/ui/**` — fallow's own documented mechanism for
    component-library barrels. `fallow audit --changed-since master`: 0 dead
    code / 0 complexity / 0 duplication (two `css-token-drift` advisories on
    shadcn-generated arbitrary grid values in `card.tsx`, warn-level, not
    blocking).
  - `pnpm test` — 16 files / 23 tests, exit 0 (4 new). `pnpm exec tsc --noEmit`,
    `pnpm exec biome check src` — exit 0 (added a `biome-ignore` on the
    scaffolded `Label` primitive's `noLabelWithoutControl` — it's a generic
    wrapper; callers supply `htmlFor`, as both forms do).

### [T-2B03] Make the shell header session-aware

- **Spec:** l1-platform-shell.md §3; l2-third-party-integrations.md §5.1
- **Status:** Done
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
  - **Observation, not a blocker:** `l1-platform-shell.md` §3/§5.1 predate
    Phase 2 and don't mention an auth affordance in the header's invariants or
    composition diagram — Layer 1 was written before auth existed. This task's
    own `Verify`/`Notes` fully specified the required behavior, so it was
    implemented as instructed rather than treated as a spec-ambiguity HALT;
    flagging here in case `l1-platform-shell.md` should be amended later to
    reflect the shell's actual current composition.
- **Changes:** `src/components/auth-nav.tsx` (Client Component: `Link` to
  `/sign-in` when unauthenticated; user name + sign-out `Button` when
  authenticated). `src/components/header.tsx` now reads
  `getCurrentUser(await headers())` and passes `authenticated`/`userLabel` plus
  translated labels to `AuthNav`, placed next to `LanguageSwitcher` (both are
  global utility controls, matching §5.1's existing slot next to nav). Added
  `signInLabel`/`signOutLabel`/`signOutPendingLabel` to the `Header` namespace
  in `messages/ru.json`.
  - **Two real bugs caught during live verification, both fixed:**
    1. After a client-side sign-up/sign-in redirect (`router.push("/")`),
       Next.js's Router Cache reuses the shared `(marketing)` layout's
       previously-fetched Header RSC payload rather than re-rendering it —
       the header kept showing "Войти" even though the session cookie was
       genuinely set (confirmed via a direct `get-session` fetch while the UI
       was stale). A plain `fetch()`-based auth call gives Next.js no signal
       to invalidate the cache the way a Server Action does. Fixed by calling
       `router.refresh()` alongside `router.push("/")` in both
       `sign-up-form.tsx` and `sign-in-form.tsx`'s `onSuccess`.
    2. `AuthNav`'s `pending` state (for the sign-out button) leaked across
       sign-out → sign-in cycles: because `<AuthNav>` stays the same
       component instance at the same position in `Header` regardless of the
       `authenticated` prop, React preserves its `useState` even though the
       returned JSX root type changes (`Link` vs `div`+`Button`) — clicking
       sign-out set `pending=true`, the success path only called
       `router.refresh()` without resetting it, and the stale `true`
       resurfaced the next time the same fiber rendered the authenticated
       branch again (showing a permanently-disabled "Выход…" button after a
       later sign-in). Fixed by resetting `pending` in the `onSuccess`
       callback too, not only `onError`. Caught by hard-reloading between
       steps to rule out stale HMR-preserved state before concluding it was a
       real bug, then reproducing the full sign-out → sign-in cycle again
       post-fix to confirm.
  - **Verified against a live `pnpm dev` (chrome-devtools MCP):** unauthenticated
    `/` and `/sign-in` show "Войти"; signed in → `/` and `/privacy-policy`
    (nested route) both show the user's name and an enabled "Выйти"; clicking
    it clears the session and reverts the header immediately, no stale
    disabled state on a subsequent sign-in. Test row deleted after
    verification — `select count(*) from "user"` = 0.
  - `pnpm test` — 17 files / 25 tests (2 new), exit 0. `pnpm exec tsc --noEmit`,
    `pnpm exec biome check src` (46 files) — exit 0.
    `fallow audit --changed-since master` — 0 dead code / 0 complexity / 0
    duplication (same two pre-existing `css-token-drift` advisories on
    scaffolded `card.tsx`, unrelated to this task).

### [T-2C01] Build the admin REST surface with mandatory admin authorization

- **Spec:** l2-third-party-integrations.md §5.3
- **Status:** Done
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
  - **Observation, not a blocker:** §5.3's prose calls all four resources
    "moderated" with a `status` column checkpoint, but `article` in
    `schema.ts` deliberately has no `status`/`moderationReason` columns — its
    own comment explains admin-authored articles skip the checkpoint (the
    author publishing it IS the trusted-actor act). Implemented generically
    against each table's actual columns rather than forcing a `status` column
    onto `article`'s schema to match the prose; `article` is still a fully
    functional admin resource (list/get/update), it just has no moderation
    filter, consistent with the schema's documented intent.
- **Changes:** `src/lib/admin/resources.ts` (resource allow-list: exactly
  `hotel`/`room`/`review`/`article` — `user`/`session`/`account` are never
  reachable through this route, by construction, not just by convention);
  `src/lib/admin/authorize.ts` (`requireAdmin`: 401 unauthenticated / 403
  wrong-role, built on T-2A04's `getCurrentUser`); `src/lib/admin/
  resolve-request.ts` (shared resource-name + auth + table/id-column
  resolution — extracted after fallow's own inline diagnostics flagged the
  3-way duplication across list/get/put before the audit gate even ran).
  `src/app/api/admin/[resource]/route.ts` (`GET` list: sort/range/filter via
  `getTableColumns` column lookup, `Content-Range` header) and
  `src/app/api/admin/[resource]/[id]/route.ts` (`GET` single, `PUT` update —
  update strips any body field with no matching column rather than letting
  Drizzle reject the whole request on react-admin's round-tripped `id` field).
  - **Verified against a live `pnpm dev` (curl + direct psql):**
    unauthenticated `GET /api/admin/hotel` → 401; guest-role session → 403;
    `GET /api/admin/user` (outside the allow-list) → 404 regardless of auth.
    Promoted the guest test user to `admin` directly in Postgres (no UI path
    to do this yet — expected, out of scope), re-tested with the same
    session cookie (Better Auth re-reads the user row per request, not a
    cached claim): list → 200, `Content-Range: hotel 0-0/1`; filter
    `{"status":"pending"}` + sort `["createdAt","DESC"]` + range `[0,9]` →
    only the pending row; `GET /api/admin/hotel/{id}` → that record; `PUT`
    with `{"id":...,"status":"published"}` → 200, row updated. Caught and
    fixed a cosmetic edge case live: an empty result set produced
    `Content-Range: hotel 0--1/0` (negative end from `start + 0 - 1`) —
    react-admin's parser only reads the total after `/` so this wasn't
    functionally broken, but fixed it to `0-0/0` for a sane header either
    way. Test data (hotel row, promoted user) deleted after verification.
  - `pnpm test` — 18 files / 28 tests (3 new: unauthenticated → 401,
    guest → 403, out-of-allow-list resource → 404), exit 0. `pnpm exec tsc
    --noEmit`, `pnpm exec biome check src` (52 files) — exit 0. `fallow audit
    --changed-since master` — 0 dead code / 0 complexity / 0 duplication.

### [T-2C02] Mount the react-admin back office at `/admin`

- **Spec:** l2-third-party-integrations.md §5.3
- **Status:** Done
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
  - **Decision recorded (item 1):** kept the registry's default English
    i18nProvider (`ra-language-english`) — the gap noted above is real
    (`ra-language-russian` 5.4.x vs `ra-core` 5.15.0 installed here), and this
    is an operator-only surface, not guest-facing, so English is an acceptable
    v1 tradeoff per the task's own Notes. Not wired to `next-intl` at all —
    react-admin's i18n is a fully separate system from the marketing site's.
  - **Decision recorded (item 2):** confirmed the split holds — see Changes.
- **Changes:** Installed `shadcn-admin-kit` via
  `pnpm dlx shadcn@latest add https://marmelab.com/shadcn-admin-kit/r/admin.json`
  (the registry-block mechanism, not a plain npm package — matches how this
  project already scaffolds shadcn/ui primitives) plus `ra-data-simple-rest`.
  `src/app/admin/App.tsx` ("use client", `<Admin dataProvider={simpleRestProvider("/api/admin")}>`
  with `<Resource>` for `hotel`/`room`/`review`/`article` using the kit's
  `ListGuesser`/`ShowGuesser`/`EditGuesser` — no Figma frame exists for this
  surface either, same as T-2B02, so no custom screens were hand-built beyond
  the guessers). `src/app/admin/page.tsx` replaced the T-2B01 placeholder with
  `dynamic(() => import("./App"), { ssr: false })` (required — the kit pulls
  `react-router`, which errors under SSR).
  - **Structural surprise, handled deliberately:** the registry's own file
    paths are `src/components/admin/*.tsx` (docs' import example is
    `@/components/admin`), but pulling it through *this* project's
    `components.json` aliases flattened all ~85 files straight into
    `src/components/*.tsx` with no `admin/` subfolder — confirmed via
    `--dry-run` before running for real, not assumed. Two knock-on effects,
    both fixed: (1) three files used relative imports (`../ui/button`) written
    for the un-flattened layout, now one level too shallow — retargeted to
    `@/components/ui/...` absolute imports, matching this project's existing
    convention. (2) no clean path boundary exists to scope tooling excludes at
    — addressed below via negated `src/components/*.{ts,tsx}` patterns instead
    of a directory prefix.
  - **Real compatibility bug, not a style choice — caught by `tsc`, not by
    the install:** the registry resolved this project's own `components.json`
    style (`base-nova`, Base UI) correctly for `button`/`input`/`label`/
    `card`/`dialog` (all pre-existing, all skipped as identical-or-close), but
    the *newly-installed* shared primitives it also pulled in
    (`popover`/`tooltip`/`dropdown-menu`/`select`/`accordion`/`avatar`/
    `checkbox`/`radio-group`/`switch`/`separator`/`command`/`drawer`) came in
    Radix-flavored (`asChild`) while the admin-kit's own consumer components
    (`app-sidebar.tsx`, `breadcrumb.tsx`, `sort-button.tsx`, etc.) are written
    against Base UI's `render` prop — 30+ `tsc` errors on first run. Fixed by
    re-pulling exactly those primitive names directly from the *core* shadcn
    registry with `--overwrite` (confirmed via `--diff` first that this also
    refreshed `button`/`input`/`dialog` with formatting-only, non-functional
    changes — safe). This is a real, reproducible upstream gap between
    shadcn-admin-kit's bundled registry dependencies and this project's
    `base-nova` style, not something specific to this install.
  - **Six remaining API-surface mismatches, hand-patched (Base UI's own
    surface moved out from under the registry's snapshot of it — event types
    gained `BaseUIEvent`/`preventBaseUIHandler`, `Separator` dropped
    `decorative`, `Accordion`'s `type="multiple"` became boolean `multiple`,
    `Menu`'s `forceMount` was removed):** `boolean-input.tsx`, `breadcrumb.tsx`,
    `bulk-export-button.tsx`, `error.tsx`, `radio-button-group-input.tsx`,
    `user-menu.tsx`. Each fix matched the *current* installed `@base-ui/react`
    (1.6.0, verified via its own `.d.ts`) rather than reverting the version,
    consistent with this project's standing "stay on latest, fix the real
    incompatibility" instruction from Phase 1/2.
  - **Two bugs found only through live browser verification, not by any
    automated gate — both fixed:**
    1. `ListGuesser`'s auto-generated `ReferenceField`s infer the target
       resource by *pluralizing* the foreign-key field name (`hotelId` →
       `hotels`), independent of how `<Resource name="hotel">` is registered
       — 404s in the console, blank reference cells in the UI. T-2C01's own
       singular-name REST contract is already documented and verified, so
       rather than reopening it, `src/lib/admin/resources.ts` gained
       `normalizeAdminResourceName`, accepting both `hotel` and `hotels` (and
       the other three) as aliases resolving to the same table — purely
       additive, `GET /api/admin/hotel` behaves exactly as T-2C01 verified.
       `guests`/`owners`/`authors` (→ `user`) still 404, by design — `user`
       stays structurally unreachable through this route.
    2. Those same `ReferenceField` lookups send `getMany`-style
       `filter: {"id": [...]}` (an array, for an `IN` lookup) — T-2C01's list
       route only built `eq()` conditions, so an array filter value produced
       `select count(*) from "hotel" where "hotel"."id" = $1` with an array
       parameter → Postgres `22P02 invalid input syntax for type uuid` → 500,
       confirmed in the dev server log. `route.ts` now uses `inArray()` when a
       filter value is an array, `eq()` otherwise — this is a completeness
       gap in T-2C01's own implementation of the `ra-data-simple-rest`
       contract it claimed to implement (the contract documents `getMany`
       explicitly), not new scope; not something its own `Verify` line
       happened to exercise, but real once this task actually drove traffic
       through it.
  - **Bundle-scope check (item 2 above), done behaviorally, not by reading
    Next.js's build summary (Turbopack's current output doesn't print a
    per-route JS table):** confirmed via `pnpm build` that `/admin` compiles
    as its own dynamic route entry, then fetched every JS chunk the `/`
    (marketing) route actually loads in the browser and grepped each one for
    `ra-core`/`react-router`/`shadcn-admin-kit` — zero matches. The
    `dynamic(..., { ssr: false })` boundary holds by construction.
  - **Tooling config, both extending patterns already established in T-2C01/
    earlier phases rather than inventing new ones:**
    - `biome.json` gained an `overrides` entry disabling the linter (not the
      formatter) for `src/components/*.{ts,tsx}` and `src/components/ui/*.tsx`
      — negating this project's own ~15 files by name, since the flattening
      above removed any directory boundary to scope a positive-only exclude.
      **Learned the hard way:** a multi-line `//` comment block placed
      immediately before the `"overrides"` key made Biome's config
      deserializer fail (`Incorrect type, expected an object, but received an
      array` — line 1, not the comment's line), which silently broke
      `useIgnoreFile` too and let `biome lint .` wander into `.next/`/`.magic/`
      (36k+ false findings). Comments elsewhere in this same file are fine;
      this exact position was not — removed rather than fought further, so
      `biome.json` currently has no inline comments (the reasoning lives here
      instead).
    - `.fallowrc.jsonc` gained a matching negated `ignorePatterns` entry (same
      file set, plus `src/hooks/use-mobile.ts`, `src/lib/field.type.ts`,
      `src/lib/i18nProvider.ts` — vendor utilities only ever imported by the
      now-excluded vendor components) to keep the ~85 files' complexity/
      duplication/styling noise out of the audit gate, the same reasoning as
      T-2C01's `ignoreExports` entry for `src/components/ui/**`.
    - `src/lib/auth/client.ts` (this project's own file, T-2B02) got flagged
      `unused-files` as a side effect — its only importers
      (`sign-up-form.tsx`, `sign-in-form.tsx`, `auth-nav.tsx`) are *not*
      excluded, and the import edges were confirmed live via grep, so this is
      a fallow false positive, not a real dead-file — suppressed inline with
      `// fallow-ignore-file unused-file` per fallow's own suggested action,
      documented in the file why.
    - Remaining `unused-dependencies` findings (`@base-ui/react`,
      `@radix-ui/react-*`, etc. — real runtime deps of the now-excluded
      vendor files, so fallow's static scan can no longer see them used) sit
      at the pre-existing `rules.unused-dependencies: "warn"` tier from
      `.fallowrc.jsonc` — already non-blocking project-wide, left as-is rather
      than adding 28 more explicit `ignoreDependencies` entries.
  - **Verified against a live `pnpm dev` (Playwright MCP — chrome-devtools MCP
    disconnected mid-task after a computer crash; environment, including
    Docker/dev-server, was restored and seed data confirmed to have survived
    via the Postgres volume):** unauthenticated and non-admin `/admin` show
    the expected 401 in console with no data; signed in as a promoted admin,
    all four resources (`hotel`/`room`/`review`/`article`) list their pending
    rows with correctly-resolving cross-references (e.g. a room's `Hotel`
    column shows the hotel's name, not a blank cell); clicking a row opens a
    `Show` detail view with all fields rendered; no marketing header/footer on
    `/admin` at any point. Test data (hotel/room/review/article rows, the
    promoted admin user) deleted after each verification pass —
    `select count(*)` = 0 across all five tables at close.
  - `pnpm test` — 18 files / 28 tests, exit 0 (unchanged — this task added no
    new automated tests of its own; the REST contract's tests are T-2C01's).
    `pnpm exec tsc --noEmit`, `pnpm exec biome check .` (174 files) — exit 0.
    `fallow audit --changed-since master` — 0 dead files / 0 unused exports /
    0 complexity findings / 0 duplication clone groups; 28 unused-dependency
    findings at `warn` (see above), 1 suppression applied, 0 stale
    suppressions.

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
