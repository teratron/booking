# Project State

<!-- STATE.md — live project memory. Read FIRST in every workflow session. -->
<!-- Maximum 100 lines. Agent updates AFTER each completed action. -->

**Workspace:** main
**Updated:** 2026-07-31 07:45
**Phase:** 2 — Identity & Back Office
**Status:** Active

## Current Position

- **Task:** T-2C02 Mount the react-admin back office at `/admin` (Done)
- **Spec:** l2-third-party-integrations.md v0.2.0 — §5.1 (Better Auth), §5.3 (react-admin)
- **Next Action:** Execute T-2C03 (approve/reject actions)

## Progress

```
Phase 1: [14/14] ████████ 100%  DONE — archived
Phase 2: [9/11]  ████████ 82%   (Track A complete; T-2B01-03, T-2C01-02 done, no blockers)
Overall: [1/6]   █░░░░░░░ 17%   (1 of 6 planned phases complete)
```

## Recent Decisions

<!-- Last 3-5 locked decisions. Older entries → archived to PLAN.md -->

- 2026-07-31 **Decision:** T-2C02 installed via `pnpm dlx shadcn@latest add https://marmelab.com/shadcn-admin-kit/r/admin.json` (registry block, not npm) — flattened all ~85 files into `src/components/*.tsx` (no `admin/` subfolder) because of this project's `components.json` aliases; see Blocking Constraints for the fallout.
- 2026-07-31 **Decision:** `app/api/admin/[resource]` now accepts both singular (`hotel`, T-2C01's documented contract) and plural (`hotels`, react-admin's auto-guessed `ReferenceField` resource name) via `normalizeAdminResourceName` — purely additive, doesn't change T-2C01's verified behavior. `user`/`session`/`account` remain structurally unreachable.
- 2026-07-31 **Decision:** `app/api/admin/[resource]` uses an explicit allow-list (`hotel`/`room`/`review`/`article`). `article` has no `status` column by design (admin-authored, skips the moderation checkpoint); the REST layer handles this generically per-resource rather than forcing schema uniformity.
- 2026-07-31 **Pattern:** Any client-side auth mutation (sign-up/sign-in/sign-out) that should be reflected by a Server Component in the same layout (e.g. the header) must pair its redirect with `router.refresh()` — see Blocking Constraints.
- 2026-07-31 **Bug (test infra, not app):** `vitest.config.ts` has no `test.globals`/`setupFiles`, so `@testing-library/react`'s auto `afterEach(cleanup)` never self-registers under Vitest. Any test file with more than one `render()` call needs an explicit `afterEach(() => { cleanup(); vi.clearAllMocks(); })`.

## Blockers

<!-- Empty if none. Format: [severity] description -->

- none

## Blocking Constraints

<!-- Anti-patterns discovered through real failures. MANDATORY reading. -->
<!-- Agent MUST explicitly acknowledge each constraint before working. -->

- **Router Cache staleness after auth mutations:** a plain `fetch()`-based
  auth call (sign-up/sign-in/sign-out) sets/clears the session cookie but
  gives Next.js's client Router Cache no signal to invalidate — a shared
  layout Server Component (e.g. `Header`) that already rendered will keep
  showing the OLD session state after `router.push()` alone. Always pair the
  redirect with `router.refresh()`. Discovered live in T-2B03 (header stayed
  on "Войти" after a successful sign-up until this was added).
- **Stale `useState` across a conditional-branch prop flip:** a component
  whose top-level return differs by a boolean prop (e.g. `AuthNav`'s
  `authenticated`) is NOT remounted by React on that flip — it's the same
  fiber at the same position in the parent, so its internal `useState` state
  persists across branches even though the rendered DOM subtree changes.
  Any "pending/loading" flag must be reset in the success path too, not only
  on error, or it can resurface stale on a later render of the other branch.
  Discovered live in T-2B03 (sign-out button reappeared permanently disabled
  after a later sign-in, from a stale `pending=true` set by an earlier
  sign-out click).
- **`biome.json` breaks on a `//` comment block placed directly before the
  `"overrides"` key:** Biome's config deserializer then reports
  `Incorrect type, expected an object, but received an array` at line 1 (not
  the comment's line), and — worse, silently — `useIgnoreFile`/`files.includes`
  stop being honored, so `biome lint .` wanders into `.next/`/`.magic/` and
  produces 30k+ false findings. Comments elsewhere in the same file are fine;
  only this exact position broke. `biome.json` currently has no inline
  comments as a result — if adding one, verify with `pnpm exec biome check .`
  (not a scoped path — `biome check src` did NOT reproduce the ignore-file
  breakage) before trusting a clean result. Discovered live in T-2C02.
- **`src/components/` has no clean vendor/first-party path boundary:**
  shadcn-admin-kit's registry install flattened into `src/components/*.tsx`
  directly (T-2C02 Decision above). Both `biome.json` (`overrides.includes`)
  and `.fallowrc.jsonc` (`ignorePatterns`) exclude the vendor set via a
  positive glob (`src/components/*.{ts,tsx}`) with this project's own ~15
  files negated (`!src/components/header.tsx` etc.) — if a new first-party
  file is added directly under `src/components/` (not `ui/`), it must be
  added to BOTH negation lists or it silently loses lint/health coverage.

## Session Continuity

**Last Session Ended:** 2026-07-30 19:54
**Handoff File:** none
**Bootstrap Mode:** false
