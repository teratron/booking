# Agents Instructions

## Frontend (`packages/` — TypeScript / React)

### Implementation

- **Presentation only**: the UI calls the core over the IPC bridge; no business logic in TypeScript.
- **Type-safe**: no `any` on public surfaces; externalize all user-facing strings (localization); honor the theme/design-token system.

### Verification (per affected package)

- `pnpm -C packages/<pkg> test` (vitest) — tests pass.
- lint + format (`biome`) — zero errors.
- `tsc --noEmit` — type-checks.
- `fallow audit --changed-since <base>` — no new dead code, duplication, circular dependencies, or architecture-boundary violations (structural gate; `--format json` in CI). The boundary rules enforce presentation-only UI with inward-pointing dependencies.

## Completion Protocol (Mandatory Checklist)

Before declaring a task complete, verify:

- [ ] Required quality gates are green for every touched package (tests + lint + type/format).
- [ ] Technical content (code, comments, docs) in English; conversational replies in Russian.
- [ ] No design/spec-layer references leaked into source files.
