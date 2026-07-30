---
phase: 1
name: "Platform Foundation"
status: Todo
subsystem: "src/"
requires: []
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 1 Tasks — Platform Foundation

**Phase:** 1
**Status:** Todo
**Strategic Goal:** A running Next.js application with the complete entity model
persisted in PostgreSQL and the shared shell every route inherits — the substrate
all five later phases build on.

## Track Structure

Tracks A, B, and C group tasks by file independence. The honest dependency shape is
`A → (B ‖ C)`: the schema and the shell never touch the same files, but both need
the scaffold to exist first. Track T validates.

## Atomic Checklist

- [ ] [T-1A01] Scaffold the Next.js application
- [ ] [T-1A02] Configure Biome as the sole lint and format toolchain
- [ ] [T-1A03] Select and wire the test framework
- [ ] [T-1A04] Configure Tailwind CSS and the shadcn/ui base
- [ ] [T-1A05] Wire Fallow for dev-time codebase intelligence
- [ ] [T-1B01] Author the Drizzle schema for the complete entity graph
- [ ] [T-1B02] Encode hierarchy and moderation constraints at the schema level
- [ ] [T-1B03] Provision the database client module
- [ ] [T-1C01] Build the root layout shell
- [ ] [T-1C02] Externalize all user-facing copy behind the i18n layer
- [ ] [T-1C03] Implement the 404 page, privacy-policy route, and feedback popup
- [ ] [T-1C04] Deliver responsive parity for the shell
- [ ] [T-1T01] Validate the entity model against the foundation relationship graph
- [ ] [T-1T02] Validate shell invariants against the platform-shell specification

## Detailed Tracking

### [T-1A01] Scaffold the Next.js application

- **Spec:** l2-tech-stack.md §5.1, §5.2, §5.6
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `pnpm install` succeeds; `pnpm exec tsc --noEmit` exits 0; `pnpm dev`
  serves `/` with HTTP 200.
- **Handoff:** Unblocks every other task in this phase.
- **Notes:** App Router, TypeScript in strict mode, pnpm as package manager. Create
  the directory layout from the spec's project-structure section. No `packages/`
  workspace split — single deployable surface for v1.

### [T-1A02] Configure Biome as the sole lint and format toolchain

- **Spec:** l2-tech-stack.md §5.5
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `pnpm exec biome check .` exits 0 with zero errors; no ESLint or
  Prettier config file remains in the repository.
- **Handoff:** Every later task's diff must keep this command green.
- **Notes:** Biome replaces ESLint plus Prettier — do not install both toolchains.

### [T-1A03] Select and wire the test framework

- **Spec:** l2-tech-stack.md §5.5
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `pnpm test` runs and exits 0 against one trivial passing test.
- **Handoff:** Every `Verify` line in Tracks B, C, and T that names a test depends
  on this existing first. Once decided, record the selection back into the
  developer-tooling section via `/magic.spec` — that section currently names only
  the lint/format and codebase-intelligence tools.
- **Notes:** The framework is genuinely unselected — no specification names one, so
  this task decides it rather than implementing a prior decision. Weigh first-class
  App Router support and Server Component testing, since the bulk of this codebase
  renders on the server.

### [T-1A04] Configure Tailwind CSS and the shadcn/ui base

- **Spec:** l2-tech-stack.md §5.3
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `pnpm exec shadcn add button` succeeds and the component renders with
  Tailwind utility classes applied; `pnpm exec biome check .` still exits 0.
- **Handoff:** Track C consumes these primitives for the shell.
- **Notes:** Mobile-first. The design source pairs a desktop and a mobile frame per
  screen; those pairs map to one responsive component tree, not two templates.

### [T-1A05] Wire Fallow for dev-time codebase intelligence

- **Spec:** l2-tech-stack.md §5.5
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `pnpm exec fallow audit --format json` runs and reports zero circular
  dependencies and zero architecture-boundary violations on the scaffolded tree.
- **Handoff:** Becomes a standing quality gate for all later phases.
- **Notes:** Dev-time only — it must not appear in the production dependency graph.

### [T-1B01] Author the Drizzle schema for the complete entity graph

- **Spec:** l1-platform-foundation.md §5.2; l2-tech-stack.md §5.4
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `pnpm exec drizzle-kit generate` emits a migration whose tables cover
  every node in the foundation spec's entity relationship diagram — account, hotel,
  room, review, article, location, reservation; `pnpm exec tsc --noEmit` exits 0.
- **Handoff:** Blocks Phase 2 (auth tables extend `account`), Phase 3, Phase 5, and
  Phase 6 (reservation state machine).
- **Notes:** This is the shared write surface for the entire plan. Model the full
  graph in one pass, including the account role discriminator, the moderation
  `status` column on externally-originated content, and the reservation's paid
  state — so later phases extend the schema rather than restructure it. Shape the
  account tables to match what the auth library's Drizzle adapter expects, so
  Phase 2 adopts them instead of introducing a parallel user table.

### [T-1B02] Encode hierarchy and moderation constraints at the schema level

- **Spec:** l1-platform-foundation.md §3
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** inserting a room with a null hotel reference fails with a not-null or
  foreign-key violation; `status` exists on hotel, room, and review with a default
  of `pending`; a test asserting both cases passes under `pnpm test`.
- **Handoff:** Phase 2's moderation queue reads this `status` field; Phase 4 filters
  discovery results on it.
- **Notes:** Two invariants are enforced here, not in application code — a room
  belongs to exactly one hotel, and externally-originated content is not publicly
  visible before it clears the moderation checkpoint.

### [T-1B03] Provision the database client module

- **Spec:** l2-tech-stack.md §5.4, §5.6
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** a script importing the client and executing `select 1` exits 0 against
  a local PostgreSQL instance; connection configuration reads from the environment
  with no credentials committed to the repository.
- **Handoff:** Every server-side query in later phases imports this module.
- **Notes:** Business logic belongs in the library layer, not in route handlers or
  components.

### [T-1C01] Build the root layout shell

- **Spec:** l1-platform-shell.md §3, §5.1
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** header (Catalog, Map, Blog, language switcher) and footer (brand,
  About, contact details, social links, Add Hotel call to action) both render on `/`
  and on an arbitrary nested route; a test asserting their presence on two distinct
  routes passes under `pnpm test`.
- **Handoff:** All six remaining phases render inside this shell.
- **Notes:** Implement as a layout wrapping every route, not as per-page markup, so
  the domain surfaces inherit it automatically. Server Component by default.

### [T-1C02] Externalize all user-facing copy behind the i18n layer

- **Spec:** l1-platform-foundation.md §3; l2-tech-stack.md §4
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** no literal Cyrillic string appears in a JSX text or label position
  anywhere under the app and component directories; the `ru` catalog resolves every
  key referenced by the shell; `pnpm exec tsc --noEmit` exits 0.
- **Handoff:** Establishes the pattern every later phase follows for new copy.
- **Notes:** Russian is the only locale shipping initially, but no template or data
  model may assume it is the only one — the header language switcher is a structural
  commitment to that.

### [T-1C03] Implement the 404 page, privacy-policy route, and feedback popup

- **Spec:** l1-platform-shell.md §3, §5.1
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** an unresolved route returns HTTP 404 and renders the styled page;
  `/privacy-policy` returns HTTP 200; the feedback popup opens from a shell-level
  trigger and closes without a full page navigation.
- **Handoff:** Phase 6 invokes the feedback popup from the room detail surface.
- **Notes:** The popup is a shared component invokable from any page, not a
  route-local one. It is the only Client Component required by this task.

### [T-1C04] Deliver responsive parity for the shell

- **Spec:** l1-platform-foundation.md §3; l1-platform-shell.md §3
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** at a 375px viewport the navigation collapses to a hamburger menu and
  the footer stacks vertically; at 1280px both render in the desktop arrangement; a
  test asserting both breakpoints passes under `pnpm test`.
- **Handoff:** Sets the responsive pattern every later surface reuses.
- **Notes:** Responsive parity is a platform invariant — a surface is not complete
  until both presentations are specified and implemented.

### [T-1T01] Validate the entity model against the foundation relationship graph

- **Goal:** Verify T-1B01 and T-1B02 implement the specification's entity model.
- **Method:** Assert that every edge in the foundation spec's entity relationship
  diagram is realized as a foreign key or join table, and that the three actor roles
  are representable on the account record. Run `pnpm exec drizzle-kit generate` and
  confirm it reports no pending schema drift.
- **Status:** Todo

### [T-1T02] Validate shell invariants against the platform-shell specification

- **Goal:** Verify Track C implements every invariant in the shell specification.
- **Method:** One test per invariant — header and footer on every route, dedicated
  404, privacy-policy route, feedback popup availability, language switcher
  presence, and both responsive presentations. Run `pnpm test`, then
  `pnpm exec biome check .` and `pnpm exec tsc --noEmit` as the phase exit gate.
- **Status:** Todo
