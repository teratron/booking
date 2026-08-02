# Project Instructions

## Tech Stack

- **Framework**: Next.js (App Router) — Server Components by default; Route Handlers / Server Actions cover backend logic, no separate API service.
- **Language**: TypeScript, strict mode. No `any` on public surfaces.
- **Package Manager**: pnpm.
- **Styling / UI**: Tailwind CSS + shadcn/ui.
- **Data**: PostgreSQL via Drizzle ORM — schema-as-code, no separate migration DSL.
- **Auth**: Better Auth (Drizzle adapter) — guest / owner / admin roles.
- **Payments**: Fondy (marketplace split payments to hotel owners); WayForPay as the single-recipient alternative if payouts stay manual.
- **Admin / Moderation**: React-admin (Drizzle adapter) — auto-generated resource CRUD + custom approve/reject actions.
- **Lint / Format**: Biome.
- **Codebase Intelligence**: Fallow — dead code, duplication, circular dependencies, architecture-boundary and design-system-drift detection. Dev-time only, no runtime footprint.

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

## Implementation Guidelines

- Business logic lives in `lib/`, not scattered across route handlers or components — components stay presentation-focused.
- Externalize all user-facing strings (localization); Russian is the primary locale, but no template or data model may assume it's the only one.
- Reach for a Client Component only where interactivity requires it; default to Server Components.

## Rules for Using shadcn MCP Server

1. **Always Check Registry First**
   - Before creating custom components, search the registries for existing solutions.
   - Use `mcp_shadcn_search_items_in_registries` to find relevant components.
   - Check `mcp_shadcn_list_items_in_registries` to see all available options.

2. **Component Discovery Workflow**
   - Start with semantic search using `mcp_shadcn_search_items_in_registries`.
   - View detailed component info with `mcp_shadcn_view_items_in_registries`.
   - Get usage examples with `mcp_shadcn_get_item_examples_from_registries`.
   - Use `mcp_shadcn_get_add_command_for_items` to get installation commands.

3. **Component Installation**
   - Use the provided add commands from the registry.
   - Ensure components are properly imported and configured.
   - Do not install example- components directly, use them as reference to create your components.
   - Follow the component's usage examples for proper implementation.
   - Do not overwrite ui or registry/ui components unless explicitly requested.

## Rules for Using shadcn-admin-kit Registry

- The `shadcn-admin-kit` registry mainly consists of a single block component called `admin`, which will install the `<Admin>` component along with all the necessary components to create an admin (such as `<List>`, `<Edit>`, `<DataTable>`, `<TextField>`, `<TextInput>`, etc.).
- The `shadcn-admin-kit` registry contains only the UI components, and relies on `ra-core`, a headless admin framework for React, to provide the logic and data fetching capabilities. For instance, the `<Resource>` component comes from `ra-core`.
- If asked to bootstrap a new Admin, you can use the `example-admin` component from the `shadcn-admin-kit` registry to get a working example, configured with a sample dataProvider, which you can use as basis.
- Shadcn Admin Kit requires a specific TS config to work: the `verbatimModuleSyntax` option must be set to `false`.

### Fixing TS Config for Admin Kit

When initializing a new Admin:
Set the `verbatimModuleSyntax` option to `false` in `tsconfig.app.json`:

```json
{
  // ...
  "compilerOptions": {
    // ...
    // (keep the other options)
    // ...
    "verbatimModuleSyntax": false
  }
}
```

## Rules for Using the `<Admin>` Component

### Client-Side Component Requirement

The `<Admin>` component from `shadcn-admin-kit` is a client-side component. Therefore, it must be either:

- Used in a Single Page Application (SPA), for instance created with Vite.
- Marked with the `"use client"` directive if used in a Server-Side Rendered (SSR) application, for instance a Next.js app.

### Root Component Setup

The entry point of the admin page is the `<Admin>` component. Specify a Data Provider to let the Admin know how to fetch data from the API.

If no Data Provider is specified, use `ra-data-json-server` and JSONPlaceholder as endpoint: `https://jsonplaceholder.typicode.com/`.

Install `ra-data-json-server`:

```bash
npm install ra-data-json-server
```

Example usage:

```tsx
"use client";

import { Admin } from "@/components/admin/admin";
import jsonServerProvider from "ra-data-json-server";

const dataProvider = jsonServerProvider(
  "https://jsonplaceholder.typicode.com/",
);

export const App = () => (
  <Admin dataProvider={dataProvider}>{/* Resources go here */}</Admin>
);
```

### Resource Declarations

Declare the CRUD routes of the application using `<Resource>` from `ra-core`.
For each resource, specify `name` and the `list`, `edit`, `create`, and `show` components.

Example with guessers:

```tsx
"use client";

import { Resource } from "ra-core";
import jsonServerProvider from "ra-data-json-server";
import { Admin } from "@/components/admin/admin";
import { ListGuesser } from "@/components/admin/list-guesser";
import { ShowGuesser } from "@/components/admin/show-guesser";
import { EditGuesser } from "@/components/admin/edit-guesser";

const dataProvider = jsonServerProvider(
  "https://jsonplaceholder.typicode.com/",
);

export const App = () => (
  <Admin dataProvider={dataProvider}>
    <Resource
      name="posts"
      list={ListGuesser}
      edit={EditGuesser}
      show={ShowGuesser}
    />
  </Admin>
);
```

## Verification

Before marking tasks complete, run and verify the following quality checks:

- `pnpm lint` / `pnpm format` (Biome) — zero lint or formatting errors.
- `tsc --noEmit` — zero TypeScript type errors.
- `fallow audit --changed-since <base>` — verify no new dead code, duplication, circular dependencies, or architecture-boundary violations.
- Project test suite — all automated tests pass.

## Completion Protocol (Mandatory Checklist)

Before declaring any task or work item complete, the agent MUST explicitly verify every item on this checklist:

- [ ] **Quality Gates & Verification**:
  - [ ] `pnpm lint` and `pnpm format` run with zero errors.
  - [ ] `tsc --noEmit` completes without type errors.
  - [ ] `fallow audit` passes with no architectural violations or dead code regressions.
  - [ ] All unit and integration tests pass cleanly.
- [ ] **Language Policy**:
  - [ ] All code identifiers, comments, docstrings, git commit messages, and documentation files are strictly in English.
  - [ ] All user-facing chat interactions, explanations, and planning discussions are in Russian.
- [ ] **Architecture & Design Principles**:
  - [ ] Business logic is kept within `lib/` and not leaked into presentation components or route handlers.
  - [ ] Server Components are used by default; Client Components (`"use client"`) are used only when interactive state is required.
  - [ ] UI components leverage shadcn/ui and registry standards without arbitrary overrides or duplicate implementations.
  - [ ] User-facing strings are properly externalized for i18n localization.
  - [ ] No design/spec-layer internal references or temporary debug hooks are leaked into committed source files.
- [ ] **Formatting Rules**:
  - [ ] No horizontal rules (`---`) are used within document bodies except in footers if strictly required.
