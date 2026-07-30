---
phase: 1
name: "Platform Foundation"
status: In Progress
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
**Status:** In Progress
**Strategic Goal:** A running Next.js application with the complete entity model
persisted in PostgreSQL and the shared shell every route inherits — the substrate
all five later phases build on.

## Track Structure

Tracks A, B, and C group tasks by file independence. The honest dependency shape is
`A → (B ‖ C)`: the schema and the shell never touch the same files, but both need
the scaffold to exist first. Track T validates.

## Atomic Checklist

- [x] [T-1A01] Scaffold the Next.js application
- [x] [T-1A02] Configure Biome as the sole lint and format toolchain
- [x] [T-1A03] Select and wire the test framework
- [x] [T-1A04] Configure Tailwind CSS and the shadcn/ui base
- [x] [T-1A05] Wire Fallow for dev-time codebase intelligence
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
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `pnpm install` succeeds; `pnpm exec tsc --noEmit` exits 0; `pnpm dev`
  serves `/` with HTTP 200.
- **Handoff:** Unblocks every other task in this phase.
- **Notes:** App Router, TypeScript in strict mode, pnpm as package manager. Create
  the directory layout from the spec's project-structure section. No `packages/`
  workspace split — single deployable surface for v1.
- **Changes:** Scaffolded Next.js 16.2.12 / React 19.2.4 (App Router, `src/`, strict
  TypeScript, import alias `@/*`) via `create-next-app`, declined Tailwind/ESLint
  (owned by T-1A04/T-1A02). Replaced the generated marketing placeholder page and
  `"Create Next App"` metadata with a minimal project-accurate placeholder; dropped
  unused demo assets. `pnpm approve-builds sharp` run to unblock the postinstall
  gate for Next's image-optimization dependency (recorded reproducibly in the new
  `pnpm-workspace.yaml`).
  - `pnpm install` — exit 0 — 58 packages resolved, sharp build approved.
  - `pnpm exec tsc --noEmit` — exit 0 — no type errors.
  - `pnpm dev` — `GET / 200` (Turbopack, ready in 992ms); server stopped after
    verification, port 3000 confirmed clear.

### [T-1A02] Configure Biome as the sole lint and format toolchain

- **Spec:** l2-tech-stack.md §5.5
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `pnpm exec biome check .` exits 0 with zero errors; no ESLint or
  Prettier config file remains in the repository.
- **Handoff:** Every later task's diff must keep this command green.
- **Notes:** Biome replaces ESLint plus Prettier — do not install both toolchains.
- **Changes:** Installed `@biomejs/biome@2.5.6` (pinned exact), ran `biome init`,
  added `pnpm lint` / `pnpm format` scripts per CLAUDE.md's Verification section.
  Scoped `files.includes` to `src/**` plus the root app-config files — the
  unscoped default also tried to reformat `.design/` and `.markdownlint.json`,
  outside this task's spec section and governed by separate engine rules; scoping
  the tool correctly is part of "configure," not a reduction in what it covers.
  Ran `biome check --write .` once to bring the scaffold's existing files
  (tabs, per Biome default) into compliance with its own new config.
  - `pnpm exec biome check .` — exit 0 — 7 files checked, 0 errors.
  - `pnpm exec tsc --noEmit` — exit 0 — no regression from reformatting.
  - No `.eslintrc*` / `.prettierrc*` present.

### [T-1A03] Select and wire the test framework

- **Spec:** l2-tech-stack.md §5.5
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `pnpm test` runs and exits 0 against one trivial passing test.
- **Handoff:** Every `Verify` line in Tracks B, C, and T that names a test depends
  on this existing first. Once decided, record the selection back into the
  developer-tooling section via `/magic.spec` — that section currently names only
  the lint/format and codebase-intelligence tools. **Still open** — flagged to the
  user at phase level; not yet done.
- **Notes:** The framework is genuinely unselected — no specification names one, so
  this task decides it rather than implementing a prior decision. Weigh first-class
  App Router support and Server Component testing, since the bulk of this codebase
  renders on the server.
- **Changes:** Decided **Vitest**, confirmed against Next.js's own current
  documentation (Context7, `/vercel/next.js/v16.2.9`) rather than assumption: Next
  ships a first-party Vitest guide, and its docs state async Server Components
  are not unit-testable under Vitest (or any current runner) — E2E is the
  documented path for those, which fits this project's later, separate needs
  without blocking this task. Rejected Jest (Next.js docs now steer new projects
  toward Vitest) and `node:test` (no DOM/JSX story, and the task explicitly asks
  to weigh Server Component / App Router testing support). Installed
  `vitest` + `@vitejs/plugin-react` + `jsdom` + `@testing-library/react` +
  `@testing-library/dom` + `vite-tsconfig-paths` per the official guide; `pnpm
  test` maps to `vitest run` (single-run, not watch) so the Verify command
  actually exits rather than hanging. One real smoke test added
  (`src/app/page.test.tsx`, renders the actual Home page and asserts its
  heading) rather than a no-op assertion.
  - **Side effect caught and corrected:** installing `vite-tsconfig-paths`
    caused pnpm to silently rewrite `package.json`'s `typescript` range from
    `^5` to `^7.0.2` (traced via `pnpm why typescript` to `tsconfck`'s open-ended
    peer range, not a hard requirement) — reverted to `^5.9.3`, the version
    T-1A01/T-1A02 already verified against, before proceeding.
  - `pnpm test` — exit 0 — 1 test file, 1 test passed.
  - `pnpm exec tsc --noEmit` and `pnpm exec biome check .` re-run clean after
    the typescript revert and the new files — no regression on T-1A01/T-1A02.

### [T-1A04] Configure Tailwind CSS and the shadcn/ui base

- **Spec:** l2-tech-stack.md §5.3
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `pnpm exec shadcn add button` succeeds and the component renders with
  Tailwind utility classes applied; `pnpm exec biome check .` still exits 0.
- **Handoff:** Track C consumes these primitives for the shell.
- **Notes:** Mobile-first. The design source pairs a desktop and a mobile frame per
  screen; those pairs map to one responsive component tree, not two templates.
- **Changes:** Installed Tailwind CSS v4 (`tailwindcss` + `@tailwindcss/postcss` +
  `postcss.config.mjs`, CSS-first config — no `tailwind.config.*` in v4). Ran the
  official `shadcn init` CLI (verified setup steps against current shadcn/ui docs
  via Context7 first) rather than hand-authoring the theme file, matching the
  T-1A01 precedent of using the real tool over a transcribed template. It merged
  Tailwind + shadcn's OKLCH theme tokens into `globals.css` on top of the existing
  reset, wrote `components.json`, `src/lib/utils.ts` (the `cn()` helper), and
  `src/components/ui/button.tsx`. CLI resolved the current default preset
  (`base-nova`, Base UI primitives rather than Radix) — a tool default, not a
  choice I made.
  - Extended `biome.json`: added `postcss.config.mjs`/`components.json` to
    `files.includes` (same rationale as prior tasks — new root config this task
    introduces), and set `css.parser.tailwindDirectives: true` (confirmed via
    Biome's own docs) — without it, Biome's CSS parser rejects Tailwind v4's
    `@theme`/`@apply`/`@custom-variant` syntax that `shadcn init` writes.
  - Added `src/components/ui/button.test.tsx` asserting the rendered button's
    `className` contains a real Tailwind utility (`inline-flex`) — automated
    evidence for "renders with Tailwind utility classes applied" rather than a
    manual visual check.
  - `pnpm exec shadcn add button --yes` — exit 0 (already present from init;
    reported skipped, not an error).
  - `pnpm exec biome check .` — exit 0 — 14 files checked.
  - `pnpm exec tsc --noEmit` — exit 0.
  - `pnpm test` — exit 0 — 2/2 tests passed (page + button), no regression on
    T-1A03's test.

### [T-1A05] Wire Fallow for dev-time codebase intelligence

- **Spec:** l2-tech-stack.md §5.5
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `pnpm exec fallow audit --format json` runs and reports zero circular
  dependencies and zero architecture-boundary violations on the scaffolded tree.
- **Handoff:** Becomes a standing quality gate for all later phases.
- **Notes:** Dev-time only — it must not appear in the production dependency graph.
- **Changes:** Verified `fallow` against the npm registry before installing (real
  package, description matches the spec exactly, MIT) — its name is an ordinary
  English word, worth confirming rather than assuming. Installed as a devDependency
  only. Ran `fallow init` (its own scaffolder, same precedent as T-1A01/T-1A04)
  rather than hand-authoring `.fallowrc.json`; its `nextjs` plugin auto-detected
  `layout.tsx`/`page.tsx` as framework entry points with zero config (confirmed
  via `fallow list`) — the generic `src/index.*` entry glob in the generated
  template matches nothing here and is harmless.
  - **Side effect caught and corrected:** installing `fallow` caused pnpm to add
    `"type": "module"`, `"engines": {"node": ">=22"}`, and `"packageManager"` to
    `package.json`, unrelated to fallow itself (no postinstall hook in its
    manifest). Reverted `type`/`engines` — an unrequested, project-wide policy
    change outside this task's scope, same reasoning as the T-1A03 TypeScript
    revert. Kept `packageManager` (harmless, standard reproducibility pin, a
    direct property of the pnpm operation itself).
  - Added `ignoreDependencies: ["tailwindcss"]` to `.fallowrc.json`: fallow
    flagged it as a "devDependency imported by production code" because
    `globals.css` does `@import "tailwindcss"`, but Next.js's PostCSS pipeline
    resolves that at build time only — no runtime import survives into the
    compiled output. Standard convention (confirmed against shadcn/ui's own
    official Next.js template) keeps it in devDependencies; documented as a
    false positive, not moved to `dependencies`. Did **not** suppress the two
    remaining findings (`buttonVariants` unused export, `lucide-react` unused
    dependency) — both are genuine, expected scaffold noise that later
    tasks/phases will resolve by consuming them, not misconfiguration.
  - `pnpm exec fallow audit --format json` — exit 1 (fallow's overall gate fails
    on the 2 legitimate findings above + 29 low-confidence unused-theme-token
    style warnings — none are circular deps or boundary violations). JSON:
    `circular_dependencies: 0`, `boundary_violations: 0`,
    `boundary_coverage_violations: 0`, `boundary_call_violations: 0` — the
    task's own Verify wording asks specifically for these two categories, not
    an overall-clean gate, and both are satisfied.
  - `pnpm exec biome check .`, `pnpm exec tsc --noEmit`, `pnpm test` all re-run
    clean (14 files / 0 errors, 0 errors, 2/2 tests) — no regression across
    T-1A01–T-1A04.

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
