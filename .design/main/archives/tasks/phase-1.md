---
phase: 1
name: "Platform Foundation"
status: Done
subsystem: "src/"
requires: []
provides:
  - "Next.js 16 scaffold (App Router, strict TypeScript, Turbopack)"
  - "Biome lint/format toolchain scoped to src/** and app configs"
  - "Vitest test framework"
  - "Tailwind CSS v4 + shadcn/ui (base-nova preset)"
  - "Fallow dev-time quality gate (0 circular deps, 0 boundary violations)"
  - "14-table Drizzle schema, migrated, constraint-tested"
  - "Local Postgres dev environment (docker-compose, port 5433)"
  - "src/lib/db/client.ts — Drizzle client over node-postgres"
  - "Root layout shell: Header, Footer, LanguageSwitcher"
  - "next-intl i18n (ru, non-routing single-locale mode)"
  - "404 page and /privacy-policy route"
  - "Shared FeedbackPopup component (Dialog-based)"
  - "Responsive nav: mobile hamburger (native details/summary) + desktop list"
key_files:
  created:
    - "package.json, tsconfig.json, next.config.ts, biome.json"
    - "vitest.config.ts, postcss.config.mjs, components.json, drizzle.config.ts"
    - "docker-compose.yml, .env.example, .fallowrc.json"
    - "src/app/layout.tsx, page.tsx, not-found.tsx, privacy-policy/page.tsx, globals.css"
    - "src/components/header.tsx, footer.tsx, language-switcher.tsx, feedback-popup.tsx"
    - "src/components/ui/button.tsx, dialog.tsx"
    - "src/lib/db/schema.ts, client.ts"
    - "src/lib/utils.ts"
    - "src/i18n/request.ts"
    - "messages/ru.json"
    - "src/no-hardcoded-copy.test.ts (standing regression guard for later phases)"
  modified: []
patterns_established:
  - "Single shared entity model authored in one pass — later phases extend the schema, never restructure it"
  - "user/session/account/verification match Better Auth's Drizzle adapter shape exactly, ready for Phase 2 to adopt"
  - "Shared amenity taxonomy reused across hotel and room, not duplicated per context"
  - "Server Component by default; Client Component only where interactivity requires it — native <details>/<summary> over JS for the mobile nav"
  - "next-intl non-routing single-locale mode; every UI string lives in messages/ru.json, never inline"
  - "Async Server Components calling next-intl/server's getTranslations cannot be unit-tested with Vitest at all — verify via a live dev-server/browser request instead"
  - "Dependency policy: stay on latest including major version bumps; fix real breakage as it surfaces rather than reverting versions"
  - "Container (async Server, resolves translations) / presentational (Client, props-only) split for any component needing both i18n and interactivity"
duration_minutes: ~
---

# Stage 1 Tasks — Platform Foundation

**Phase:** 1
**Status:** Done
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
- [x] [T-1B01] Author the Drizzle schema for the complete entity graph
- [x] [T-1B02] Encode hierarchy and moderation constraints at the schema level
- [x] [T-1B03] Provision the database client module
- [x] [T-1C01] Build the root layout shell
- [x] [T-1C02] Externalize all user-facing copy behind the i18n layer
- [x] [T-1C03] Implement the 404 page, privacy-policy route, and feedback popup
- [x] [T-1C04] Deliver responsive parity for the shell
- [x] [T-1T01] Validate the entity model against the foundation relationship graph
- [x] [T-1T02] Validate shell invariants against the platform-shell specification

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
- **Status:** Done
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
- **Changes:** Verified Better Auth's exact Drizzle adapter table shape before
  writing anything (`user`/`session`/`account`/`verification`, singular names,
  `text` PKs) so Phase 2 adopts this schema rather than migrating it; `role` added
  to `user` as an enum via the documented `additionalFields` pattern. Confirmed
  current stable `drizzle-orm`/`drizzle-kit` (0.45.x/0.31.x) against npm's
  `latest` dist-tag before installing — the docs site's own "new project" guide
  pushes `@rc` (1.0, `defineRelations`) which isn't promoted to `latest` yet;
  used the classic stable `pgTable`/`relations()`/`one()`/`many()` API instead.
  `location` modeled as embedded `address`/`latitude`/`longitude` columns on
  `hotel` rather than a separate table — the specs describe it as hotel-owned
  submission data, not a reusable place taxonomy (no evidenced second use site).
  `amenity` is one shared taxonomy table + `hotel_amenity`/`room_amenity` join
  tables, per property-onboarding's own instruction to reuse one taxonomy across
  both contexts rather than duplicating it — directly evidenced, not speculative
  normalization. `room.hotel_id` is `uuid NOT NULL` with a cascading FK — the
  hierarchy invariant verbatim. Room/hotel/review each get their own `status`
  (shared `moderation_status` enum); `article` has none — admin-authored content
  is explicitly exempt from the moderation checkpoint per content-publishing's
  own resolution. Extracted repeated `status`/`timestamps` column groups into
  shared spreadable objects after the IDE's own duplicate-code diagnostic (Fallow)
  flagged the original per-table repetition.
  - `pnpm exec drizzle-kit generate` — exit 0 — 14 tables, all FKs wired
    (`drizzle/0000_gorgeous_triton.sql`).
  - `pnpm exec tsc --noEmit`, `pnpm exec biome check .` (16 files), `pnpm test`
    (2/2) — all exit 0, no regression.

### [T-1B02] Encode hierarchy and moderation constraints at the schema level

- **Spec:** l1-platform-foundation.md §3
- **Status:** Done
- **Assignment:** Agent
- **Verify:** inserting a room with a null hotel reference fails with a not-null or
  foreign-key violation; `status` exists on hotel, room, and review with a default
  of `pending`; a test asserting both cases passes under `pnpm test`.
- **Handoff:** Phase 2's moderation queue reads this `status` field; Phase 4 filters
  discovery results on it.
- **Notes:** Two invariants are enforced here, not in application code — a room
  belongs to exactly one hotel, and externally-originated content is not publicly
  visible before it clears the moderation checkpoint.
- **Changes:** Both constraints were already structurally in place from T-1B01
  (`room.hotel_id` not-null FK; `moderation` status default `pending` on
  hotel/room/review); this task adds `src/lib/db/constraints.test.ts` as the
  actual proof against the live database, executed after T-1B03's client
  existed. Resequenced ahead of T-1B03 in the checklist order but after it in
  execution — the test needs a working connection, so provisioning had to come
  first regardless of task numbering.
  - Null-`hotel_id` insert: asserted on the underlying Postgres error code
    (`23502`, not_null_violation) via `error.cause.code`, not a message-string
    match — drizzle-orm wraps the driver error in its own `"Failed query: ..."`
    message, so string matching against the outer error was fragile and
    initially failed even though the insert correctly rejected.
  - Confirmed `status: "pending"` on inserted hotel, room, *and* review rows
    (the task names all three); test cleans up its own rows after asserting.
  - `pnpm test` — exit 0 — 4 files / 5 tests, no regression.
  - `pnpm exec tsc --noEmit`, `pnpm exec biome check .` (19 files) — exit 0.

### [T-1B03] Provision the database client module

- **Spec:** l2-tech-stack.md §5.4, §5.6
- **Status:** Done
- **Assignment:** Agent
- **Verify:** a script importing the client and executing `select 1` exits 0 against
  a local PostgreSQL instance; connection configuration reads from the environment
  with no credentials committed to the repository.
- **Handoff:** Every server-side query in later phases imports this module.
- **Notes:** Business logic belongs in the library layer, not in route handlers or
  components.
- **Changes:** `src/lib/db/client.ts` — `drizzle(pool, { schema })` over
  `drizzle-orm/node-postgres` + `pg.Pool`, `connectionString` from
  `process.env.DATABASE_URL` only (`.env` is gitignored; `.env.example` carries
  the placeholder). Verified as `src/lib/db/client.test.ts` (`select 1`) rather
  than a throwaway script, matching this phase's testing convention. Wired
  `dotenv/config` into `vitest.config.ts` and `drizzle.config.ts` — Vitest, unlike
  Next.js, does not auto-load `.env`.
  - **Environment gap found:** no local Postgres existed; provisioned
    `docker-compose.yml` (postgres:18-alpine) as a self-contained, portable dev
    dependency rather than requiring a native install. Not in the original phase
    plan — added as a direct extension of this task's own scope.
  - **Two real breakages hit and fixed while standing it up (not previously
    known — evidence for future phases needing a local DB):**
    1. postgres:18+ images changed their volume-mount convention to
       `/var/lib/postgresql` (major-version-specific subdirs), not
       `/var/lib/postgresql/data` — the container crash-looped until corrected
       ([docker-library/postgres#1259](https://github.com/docker-library/postgres/pull/1259)).
    2. Host port 5432 was already bound by a **native** PostgreSQL 18 Windows
       service on this machine (`C:\Program Files\PostgreSQL\18`) — the Node
       client was silently connecting to that instead of the container and
       failing auth against unrelated credentials. Remapped the container to
       host port 5433 (`docker-compose.yml`, `.env`, `.env.example`) rather than
       touching the pre-existing native install.
  - Applied the T-1B01 migration: `pnpm exec drizzle-kit migrate` — exit 0 — all
    14 tables confirmed present via `psql \dt` inside the container.
  - `pnpm test` — exit 0 — 3/3 (adds the DB round-trip test; both prior tests
    still pass, no regression).
  - `pnpm exec biome check .` (18 files), `pnpm exec tsc --noEmit` — exit 0.

### [T-1C01] Build the root layout shell

- **Spec:** l1-platform-shell.md §3, §5.1; l2-tech-stack.md §5.7
- **Status:** Done
- **Assignment:** Agent
- **Verify:** header (Catalog, Map, Blog, language switcher) and footer (brand,
  About, contact details, social links, Add Hotel call to action) both render on `/`
  and on an arbitrary nested route; a test asserting their presence on two distinct
  routes passes under `pnpm test`.
- **Handoff:** All six remaining phases render inside this shell.
- **Notes:** Implement as a layout wrapping every route, not as per-page markup, so
  the domain surfaces inherit it automatically. Server Component by default.
  [ADDED] Header/footer/nav pieces built here are this phase's first shared
  components beyond `components/ui/button.tsx` — build them per §5.7 (composable,
  in `components/`, variant-driven over `cn()` where a piece genuinely varies,
  not duplicated per route) rather than inlined directly into the layout markup.
- **Changes:** `src/components/header.tsx`, `footer.tsx`, `language-switcher.tsx`
  (Server Components, no client interactivity needed yet); wired into
  `src/app/layout.tsx` unconditionally around `{children}`. Set `<html lang="ru">`
  (was `"en"`, unnoticed since the primary interface language is Russian).
  "Map" is a distinct nav item in the spec's Figma inventory but the sitemap only
  documents one `/catalog` route — linked it to `/catalog?view=map` rather than
  inventing a second route; Phase 4 (hotel-discovery) owns making that param do
  something. Nav/footer links point at not-yet-built routes (`/blog`,
  `/add-hotel`, `/privacy-policy`, `/about`) the same way T-1A01 linked ahead of
  build — Next.js resolves them at request time, not at link-authoring time.
  Contact details and social hrefs are explicit placeholders (no real values
  provided anywhere) — commented as such, not presented as real data.
  - RTL/jsdom can't cleanly render a `RootLayout` returning literal
    `<html>`/`<body>` tags, so tested Header/Footer directly
    (`header.test.tsx`, `footer.test.tsx`) rather than through the layout —
    "wraps every route" is a Next.js App Router structural guarantee (no
    per-route conditional in layout.tsx), not something that needs re-proving
    per route in a unit test.
  - Two-route proof for the Verify line's literal wording: `pnpm dev`, curled
    `/` (200) and `/some/nested/route` (404, unbuilt) — header/footer content
    present in both response bodies, confirming the layout wraps even routes
    that don't exist yet (Next's default not-found still inherits the root
    layout). Server stopped after verification.
  - `pnpm test` — exit 0 — 6 files / 7 tests, no regression.
  - `pnpm exec biome check .` (24 files, one real a11y fix: `aria-label` needs a
    role that supports it, not a bare `<span>`) and `pnpm exec tsc --noEmit` —
    exit 0.

### [T-1C02] Externalize all user-facing copy behind the i18n layer

- **Spec:** l1-platform-foundation.md §3; l2-tech-stack.md §4
- **Status:** Done
- **Assignment:** Agent
- **Verify:** no literal Cyrillic string appears in a JSX text or label position
  anywhere under the app and component directories; the `ru` catalog resolves every
  key referenced by the shell; `pnpm exec tsc --noEmit` exits 0.
- **Handoff:** Establishes the pattern every later phase follows for new copy.
- **Notes:** Russian is the only locale shipping initially, but no template or data
  model may assume it is the only one — the header language switcher is a structural
  commitment to that.
- **Changes:** `next-intl` — the candidate l2-tech-stack.md §4 already named.
  Verified (Context7-equivalent WebFetch against next-intl's own current docs)
  that it supports a **non-routing single-locale mode** — no `[locale]` URL
  segment, just message extraction + `getTranslations`/`useTranslations` — and
  used that instead of full i18n routing: introducing `[locale]` now, with one
  locale and a static (non-functional) switcher, would be exactly the
  speculative structure §5.7 warns against. `src/i18n/request.ts` (locale
  fixed to `"ru"`), `messages/ru.json`, `next.config.ts` wrapped with
  `createNextIntlPlugin()`, root layout wraps children in
  `NextIntlClientProvider`. Header/Footer/LanguageSwitcher converted to `async`
  Server Components calling `getTranslations()`.
  - Extracted every Cyrillic UI string (nav labels, footer links, the language
    label) **and** the English `aria-label`s alongside them — the Verify line's
    wording only names Cyrillic, but leaving hardcoded English labels next to
    freshly-translated Russian ones would violate the invariant's actual intent
    (no template may assume Russian is the only locale) even though it slides
    past the literal check. Left brand name ("Booking") and third-party proper
    nouns (Instagram/Telegram/Facebook) untranslated — standard i18n practice.
  - **Testing constraint hit and resolved:** converting Header/Footer/
    LanguageSwitcher to `async` broke their existing RTL render tests two ways —
    (a) React's client renderer can't resolve an async component's returned
    Promise at all, and (b) `next-intl/server`'s `getTranslations` explicitly
    throws under Vitest's jsdom environment ("not supported in Client
    Components"). This matches Next.js's own Vitest guide (found during
    T-1A03): async Server Components are an E2E concern, not a unit-test one.
    Rewrote the three tests to assert catalog-key completeness instead
    (`messages.Header.navCatalog` etc. resolve, no render attempted), and
    proved actual rendering via a live `pnpm dev` request — response body
    contains every Russian string (`Каталог`, `Блог`, `О нас`, `Ру`, etc.) and
    `lang="ru"`.
  - Added `src/no-hardcoded-copy.test.ts` — a permanent regression guard
    scanning every non-test `.ts`/`.tsx` under `app/` and `components/` for
    Cyrillic characters (none should remain outside `messages/`). This is the
    actual automated proof for the Verify line's first clause, and stays
    useful for every later phase's new copy.
  - `pnpm test` — exit 0 — 8 files / 9 tests (2 new catalog tests + the
    hardcoded-copy guard; no regression on the 6 prior tests).
  - `pnpm exec tsc --noEmit`, `pnpm exec biome check .` (27 files) — exit 0.

### [T-1C03] Implement the 404 page, privacy-policy route, and feedback popup

- **Spec:** l1-platform-shell.md §3, §5.1
- **Status:** Done
- **Assignment:** Agent
- **Verify:** an unresolved route returns HTTP 404 and renders the styled page;
  `/privacy-policy` returns HTTP 200; the feedback popup opens from a shell-level
  trigger and closes without a full page navigation.
- **Handoff:** Phase 6 invokes the feedback popup from the room detail surface.
- **Notes:** The popup is a shared component invokable from any page, not a
  route-local one. It is the only Client Component required by this task.
- **Changes:** `src/app/not-found.tsx` (Next.js's special-file convention —
  replaces the framework default, still inherits the root layout);
  `src/app/privacy-policy/page.tsx`. Both Server Components via
  `getTranslations`. Feedback popup: installed shadcn's `dialog` primitive
  (`shadcn add dialog`, same tool-over-hand-authoring precedent as T-1A04's
  button) rather than building a modal from scratch — declined its offer to
  overwrite the existing `button.tsx` it depends on, since that file is
  unchanged and re-fetching it added no value.
  `src/components/feedback-popup.tsx` is the phase's one Client Component
  (`"use client"`), composing `Dialog`/`DialogTrigger`/`DialogContent` with a
  presentational name+message form; submitting just closes the popup — no
  feedback backend exists anywhere in the spec set, so wiring a real endpoint
  would be inventing scope, not implementing evidenced scope. Trigger placed
  in the footer (a "shell-level trigger," per the spec's own wording) — Phase 6
  adds the room-detail-surface trigger the spec actually evidences, per this
  task's own Handoff line.
  - Caught a real bug in my own first draft before it shipped: the Cancel
    button's label was `submitLabel === messageLabel ? submitLabel : title` —
    leftover, nonsensical logic — replaced with a proper `cancelLabel` prop,
    itself translated (`messages/ru.json` → `FeedbackPopup.cancelLabel`).
  - `FeedbackPopup` receives already-translated strings as props rather than
    calling `getTranslations` itself — it's a Client Component, and
    `next-intl/server`'s functions are Server-only by construction; the
    server/client boundary here happens to be exactly the container
    (`Footer`, resolves translations) / presentational (`FeedbackPopup`, pure
    props) split that also makes it directly RTL-testable, unlike T-1C02's
    async components.
  - `feedback-popup.test.tsx` — real interaction test via
    `@testing-library/user-event` (newly installed): dialog absent → click
    trigger → dialog + description visible → click Cancel → dialog gone.
  - Two-route smoke test via live `pnpm dev`: `/some/nested/route` → HTTP 404,
    body contains "Страница не найдена"/"На главную"; `/privacy-policy` → HTTP
    200, body contains "Политика конфиденциальности". Server stopped after.
  - `pnpm test` — exit 0 — 9 files / 10 tests, no regression.
  - `pnpm exec tsc --noEmit`, `pnpm exec biome check .` (32 files, reformatted
    the shadcn-generated `dialog.tsx` into the project's own style) — exit 0.

### [T-1C04] Deliver responsive parity for the shell

- **Spec:** l1-platform-foundation.md §3; l1-platform-shell.md §3
- **Status:** Done
- **Assignment:** Agent
- **Verify:** at a 375px viewport the navigation collapses to a hamburger menu and
  the footer stacks vertically; at 1280px both render in the desktop arrangement; a
  test asserting both breakpoints passes under `pnpm test`.
- **Handoff:** Sets the responsive pattern every later surface reuses.
- **Notes:** Responsive parity is a platform invariant — a surface is not complete
  until both presentations are specified and implemented.
- **Changes:** Header nav: native `<details>/<summary>` disclosure for the
  hamburger (`md:hidden`) — zero JS, keyboard/screen-reader accessible by
  default (confirmed in the a11y tree as a proper `DisclosureTriangle` role) —
  plus a plain `<ul>` shown only `md:flex` for desktop. No new Client
  Component needed; stays consistent with "Client Component only where
  interactivity requires it," since `<details>` provides the interactivity
  natively. Footer: outer section container changed from always-`flex-col` to
  `flex-col md:flex-row md:justify-between` — it was actually vertical at
  *every* width before this task, not genuinely responsive.
  - **Testing constraint (same root cause as T-1C02):** Header/Footer are
    async Server Components calling `getTranslations`, which throws under
    Vitest's jsdom environment regardless of whether the result is rendered —
    can't unit-test them via RTL at all. `responsive-shell.test.ts` instead
    asserts the responsive Tailwind classes are present in source (a
    regression guard, not a layout assertion — jsdom doesn't compute real CSS
    layout either way).
  - **Real breakpoint verification via chrome-devtools MCP** (live `pnpm dev`,
    not curl — this needed actual CSS evaluation): at 375px, mobile trigger
    visible / desktop nav hidden / footer `flex-direction: column`; clicking
    the trigger genuinely expands Каталог/Карта/Блог (verified via the a11y
    snapshot, not just class presence). At 1280px: mobile trigger hidden /
    desktop nav visible / footer `flex-direction: row`.
  - **Caught and fixed a bug in my own first verification pass**: an initial
    `getComputedStyle(el).display !== 'none'` check reported the mobile
    trigger as "visible" at 1280px, which was wrong — `getComputedStyle` on an
    element doesn't account for an ancestor's `display:none` (the `<details>`
    parent was correctly hidden; the check just wasn't asking the right
    question). Fixed by checking `el.getClientRects().length > 0`, which
    reflects actual layout participation through the whole ancestor chain, and
    re-confirmed both breakpoints against the corrected check before trusting
    the result. The actual component code was right the first time.
  - `pnpm test` — exit 0 — 10 files / 12 tests, no regression.
  - `pnpm exec tsc --noEmit`, `pnpm exec biome check .` (33 files) — exit 0.

### [T-1T01] Validate the entity model against the foundation relationship graph

- **Goal:** Verify T-1B01 and T-1B02 implement the specification's entity model.
- **Method:** Assert that every edge in the foundation spec's entity relationship
  diagram is realized as a foreign key or join table, and that the three actor roles
  are representable on the account record. Run `pnpm exec drizzle-kit generate` and
  confirm it reports no pending schema drift.
- **Status:** Done
- **Changes:** `pnpm exec drizzle-kit generate` — "No schema changes, nothing to
  migrate" — `schema.ts` and the applied migration are in sync, zero drift.
  Edge-by-edge check against l1-platform-foundation.md §5.2:

  | Diagram edge | Schema realization |
  | --- | --- |
  | Account →role→ Guest/Owner/Admin | `user.role` (`actor_role` enum) |
  | Owner →submits→ Hotel | `hotel.owner_id` → `user.id` |
  | Hotel →has many→ Room | `room.hotel_id` → `hotel.id`, `NOT NULL` |
  | Guest →writes→ Review | `review.guest_id` → `user.id` |
  | Hotel →has many→ Review | `review.hotel_id` → `hotel.id` |
  | Hotel →has many→ NewsItem | `article.hotel_id` → `hotel.id` (nullable — content-publishing.md describes hotel-scoping as optional, not every article is hotel-scoped) |
  | Room →reserved via→ Reservation | `reservation.room_id` → `room.id` |
  | Guest →makes→ Reservation | `reservation.guest_id` → `user.id` |
  | Reservation →date range + guest count→ Room | `reservation.check_in`/`check_out`/`guest_count` columns |

  Two deliberate deviations from the diagram's literal shape, both already
  recorded in T-1B01's Changes — restated here since this task exists
  specifically to catch exactly this kind of gap:
  - **Hotel →located at→ Location**: not a separate table + FK. Embedded as
    `hotel.address`/`latitude`/`longitude`. No spec describes Location as a
    reusable, independently-queried entity (e.g. a city/region taxonomy) — every
    reference treats it as data the hotel submission itself carries. A separate
    table would be a join for a relationship that's always 1:1 and always
    fetched with its hotel.
  - **Admin →approves/rejects→ Hotel/Review**: no `admin_id`/"approved by" FK
    exists — only `status` + `moderation_reason`. No spec (including
    l2-third-party-integrations.md §5.3, which specifies the AdminJS
    approve/reject action itself) asks for an audit trail of which admin
    performed a given action. If that turns out to be needed, it's an additive
    column, not a restructure — consistent with T-1B01's "extend, don't
    restructure" goal.

### [T-1T02] Validate shell invariants against the platform-shell specification

- **Goal:** Verify Track C implements every invariant in the shell specification.
- **Method:** One test per invariant — header and footer on every route, dedicated
  404, privacy-policy route, feedback popup availability, language switcher
  presence, and both responsive presentations. Run `pnpm test`, then
  `pnpm exec biome check .` and `pnpm exec tsc --noEmit` as the phase exit gate.
- **Status:** Done
- **Changes:** Invariant-by-invariant check against l1-platform-shell.md §3:

  | Invariant | Evidence |
  | --- | --- |
  | Header nav + language switcher, every page, mobile hamburger | `header.test.tsx`, `language-switcher.test.tsx`, `responsive-shell.test.ts`; live-route curl (T-1C01) + chrome-devtools MCP breakpoint check (T-1C04) |
  | Footer contact/social/Add-Hotel CTA, every page | `footer.test.tsx`; live-route curl (T-1C01) |
  | Dedicated 404 page | `not-found.test.tsx` (new — see below); HTTP 404 + content confirmed live (T-1C03) |
  | Privacy-policy page, footer-linked | `privacy-policy/page.test.tsx` (new — see below); HTTP 200 + footer link confirmed live (T-1C03) |
  | Feedback popup, shared component | `feedback-popup.test.tsx` — real open/close interaction test |
  | Both responsive presentations | `responsive-shell.test.ts` + chrome-devtools MCP at 375px/1280px (T-1C04) |

  **Two real gaps found and fixed by this validation pass** (exactly what a
  dedicated Track-T task is for): `not-found.tsx` and `privacy-policy/page.tsx`
  had no catalog-completeness test, unlike every other i18n-touching
  component — added `src/app/not-found.test.tsx` and
  `src/app/privacy-policy/page.test.tsx` (same pattern as header/footer/
  language-switcher: assert the referenced `messages/ru.json` keys resolve,
  since the components themselves can't be unit-rendered).
  - Ran `fallow audit --format json` as an additional phase-close check beyond
    what this task's own Method line names (the standing gate T-1A05
    established): `circular_dependencies: 0`, `boundary_violations: 0` — clean
    across the phase's full growth (14 DB tables, 11 components, i18n). The 2
    remaining findings (`DialogOverlay`/`DialogPortal` unused exports) are
    shadcn-vended public API surface on a generated file, not application
    code — same class of expected noise already documented in T-1A05, not
    suppressed or removed.
  - `pnpm test` — exit 0 — 12 files / 14 tests (2 new, all prior still green).
  - `pnpm exec biome check .` (35 files), `pnpm exec tsc --noEmit` — exit 0.
