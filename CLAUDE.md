# Agent Instructions

## Stack

- **Framework**: Next.js (App Router) — Server Components by default; Route Handlers / Server Actions cover backend logic, no separate API service.
- **Language**: TypeScript, strict mode. No `any` on public surfaces.
- **Package manager**: pnpm.
- **Styling / UI**: Tailwind CSS + shadcn/ui.
- **Data**: PostgreSQL via Drizzle ORM — schema-as-code, no separate migration DSL.
- **Auth**: Better Auth (Drizzle adapter) — guest / owner / admin roles.
- **Payments**: Fondy (marketplace split payments to hotel owners); WayForPay as the single-recipient alternative if payouts stay manual.
- **Admin / moderation**: React-admin (Drizzle adapter) — auto-generated resource CRUD + custom approve/reject actions.
- **Lint / format**: Biome.
- **Codebase intelligence**: Fallow — dead code, duplication, circular dependencies, architecture-boundary and design-system-drift detection. Dev-time only, no runtime footprint.

## Project Structure

```plaintext
src/
├── app/                  # Next.js App Router routes
│   ├── (marketing)/      # home, catalog, hotel/[id], blog, blog/[id]
│   ├── add-hotel/
│   ├── admin/            # React-admin mount point
│   └── api/              # Route Handlers where a Server Action isn't a fit
├── components/           # shadcn/ui-based shared components
├── lib/
│   ├── db/               # Drizzle schema + client
│   ├── auth/             # Better Auth config
│   └── i18n/
└── styles/
```

Single Next.js app for now — no `packages/` workspace split until a second deployable surface (e.g. a separate admin app or a native client) actually exists.

## Implementation

- Business logic lives in `lib/`, not scattered across route handlers or components — components stay presentation-focused.
- Externalize all user-facing strings (localization); Russian is the primary locale, but no template or data model may assume it's the only one.
- Reach for a Client Component only where interactivity requires it; default to Server Components.

## Verification

- `pnpm lint` / `pnpm format` (Biome) — zero errors.
- `tsc --noEmit` — type-checks.
- `fallow audit --changed-since <base>` — no new dead code, duplication, circular dependencies, or architecture-boundary violations (`--format json` in CI).
- Project test suite — green (test framework not yet finalized).

## Completion Protocol (Mandatory Checklist)

Before declaring a task complete, verify:

- [ ] Required quality gates are green (lint + format + type-check + fallow audit + tests).
- [ ] Technical content (code, comments, docs) in English; conversational replies in Russian.
- [ ] No design/spec-layer references leaked into source files.
