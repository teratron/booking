---
name: git-flow
description: Write conventional commit messages, manage GitFlow branch lifecycles (features, releases, hotfixes), execute pre-commit validation gates, create structured PR descriptions, generate user-facing changelogs, and run parallel tasks using Git worktrees.
---

# Git Flow

Comprehensive skill for Git operations: branch lifecycle management, conventional commits, pre-commit validation gates, pull request engineering, changelog generation, and isolated worktree workflows.

## 1. Preflight Invariants & Branch Taxonomy

Before executing any Git operation, perform mandatory preflight verification:

1. Ensure the working tree is clean (`git status` shows no uncommitted changes).
2. Identify the active target branch and confirm its prefix matches the operational intent.
3. Determine the repository workflow preset (Classic GitFlow vs GitHub Flow vs GitLab Flow) to set correct target branches.

### Branch Taxonomy & Naming Rules

- `main` / `master`: Production branch. Contains deployable release history. Direct commits forbidden.
- `develop`: Integration branch for features. Main staging branch.
- `feature/<kebab-case>`: Feature development off `develop` (e.g. `feature/user-authentication`).
- `release/<vX.Y.Z>`: Release preparation off `develop` (e.g. `release/v2.1.0`).
- `hotfix/<vX.Y.Z>`: Urgent production fix off `main`/`master` (e.g. `hotfix/v2.1.1`).
- `support/<version>`: Maintenance branch for historical major versions (optional).

Always use lower kebab-case for topic names (`<type>/<kebab-case>`).

## 2. Branch Lifecycle Workflows

### Feature Workflow

1. Start Feature:

   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b feature/your-feature-name
   ```

2. Work & Commit:
   - Make atomic commits using Conventional Commit format.
   - Run pre-commit validation gate before every commit.
3. Synchronize with Base Branch:

   ```bash
   git checkout develop
   git pull origin develop
   git checkout feature/your-feature-name
   git merge develop
   ```

   - Resolve any code conflicts locally.
   - Run the project test suite to verify no regressions occurred.
4. Push & Open PR:

   ```bash
   git push --set-upstream origin feature/your-feature-name
   ```

   - Create Pull Request target branch `develop`.
5. Finish Feature:
   - Merge PR using `--no-ff` (non-fast-forward) to preserve feature history.
   - Clean up branches:

     ```bash
     git checkout develop
     git pull origin develop
     git branch -d feature/your-feature-name
     git push origin --delete feature/your-feature-name
     git fetch --prune
     ```

### Release Workflow

1. Start Release:

   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b release/vX.Y.Z
   ```

2. Polish Release:
   - Perform version bump in project metadata files (e.g. `.version`, `package.json`).
   - Run documentation sync and update `CHANGELOG.md`.
   - Apply only bug fixes and release documentation tweaks. No new features.
3. Finish Release:
   - Merge release branch into `main`/`master` with `--no-ff`:

     ```bash
     git checkout main
     git pull origin main
     git merge --no-ff release/vX.Y.Z -m "release: merge release vX.Y.Z into main"
     git tag -a vX.Y.Z -m "Release vX.Y.Z"
     git push origin main --tags
     ```

   - Merge back into `develop` with `--no-ff`:

     ```bash
     git checkout develop
     git pull origin develop
     git merge --no-ff release/vX.Y.Z -m "chore: sync release vX.Y.Z into develop"
     git push origin develop
     ```

   - Remove release branch locally and remotely:

     ```bash
     git branch -d release/vX.Y.Z
     git push origin --delete release/vX.Y.Z
     git fetch --prune
     ```

### Hotfix Workflow

1. Start Hotfix:

   ```bash
   git checkout main
   git pull origin main
   git checkout -b hotfix/vX.Y.Z
   ```

2. Fix & Version:
   - Implement critical bugfix.
   - Bump patch version in project metadata.
   - Update `CHANGELOG.md` under `Fixed` / `Security`.
3. Finish Hotfix:
   - Merge hotfix branch into `main` with `--no-ff`:

     ```bash
     git checkout main
     git pull origin main
     git merge --no-ff hotfix/vX.Y.Z -m "hotfix: merge hotfix vX.Y.Z into main"
     git tag -a vX.Y.Z -m "Hotfix vX.Y.Z"
     git push origin main --tags
     ```

   - Merge hotfix branch back into `develop` with `--no-ff`:

     ```bash
     git checkout develop
     git pull origin develop
     git merge --no-ff hotfix/vX.Y.Z -m "chore: sync hotfix vX.Y.Z into develop"
     git push origin develop
     ```

   - Clean up hotfix branch:

     ```bash
     git branch -d hotfix/vX.Y.Z
     git push origin --delete hotfix/vX.Y.Z
     git fetch --prune
     ```

## 3. Conventional Commits & Pre-Commit Validation Gate

### Message Format

Format: `type(scope): subject`
Optional Breaking Change: `type(scope)!: subject`

- **Allowed Types**: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`, `revert`.
- **Subject**: Imperative mood ("add", not "added"/"adds"), lowercase start, no trailing period, ≤ 72 characters. The subject completes: "If applied, this commit will...".
- **Scope**: Lowercase module/area touched (e.g. `auth`, `api`, `deps`).
- **Body**: Explains WHY the change was made, wrapped at 72 chars. Omit if self-explanatory.
- **Breaking Change**: Append `!` after type/scope AND add a `BREAKING CHANGE:` footer with migration details.

### Pre-Commit Validation Gate

Run these 4 validation checks BEFORE executing `git commit`:

1. Format Validation:
   Verify subject line matches regex:
   `^(feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert)(\([a-z0-9./-]+\))?!?: [a-z].{0,70}[^.]$`
2. Diff-Message Symmetry:
   Read `git diff --staged`. Ensure the subject fully covers all staged changes. If changes belong to multiple concerns, split into separate commits. One commit = one logical change.
3. Secret Sweep:
   Scan staged added lines against regex:
   `(api[_-]?key|secret|token|password|BEGIN [A-Z]+ PRIVATE KEY|sk-[A-Za-z0-9]{20,})`
   Block commit immediately on match. Never bypass secret hits.
4. Untracked Artifact Audit:
   Inspect `git status` output. Ensure `.env` files, credentials, local debug logs, or build artifacts are not staged.

### Breaking Change Detector

Check staged diff for any breaking change triggers:

- Removed or renamed exported function, public route, CLI flag, config key.
- Changed function signature, return type shape, database schema, API response contract.
- Added mandatory environment variable, required migration, or minimum runtime requirement.

If any trigger is present, format commit with `!` prefix and include `BREAKING CHANGE: <explanation>` footer.

## 4. Pull Request Standards

Keep PR descriptions concise, actionable, and under 300 words.

### PR Description Template

```markdown
## What
<one paragraph: high-level user-visible summary of changes>

## Why
<problem description or requirement link, issue reference>

## How to verify
1. <exact command or action step 1>
2. <exact command or action step 2>

## Risk & rollback
- Blast radius: <affected components or low/medium/high risk>
- Rollback procedure: revert this PR commit (`git revert -m 1 <merge-commit-hash>`)
```

### PR Requirements

- Split multi-purpose work into focused PRs. No "misc fixes" PRs.
- Include UI screenshots or video recordings for any frontend layout modifications.
- Ensure "How to verify" instructions are clear and runnable by any reviewer.

## 5. Automated Changelog Generation

When updating `CHANGELOG.md` during release or hotfix completion, adhere to Keep a Changelog standards.

### Inclusion & Filtering Rules

- **Include (User-Facing)**: Commits affecting end users, API consumers, CLI, or documented contracts.
  - `feat` -> `Added`
  - `fix` -> `Fixed`
  - `refactor`, `perf`, `style` -> `Changed`
  - `docs` (user-visible API/usage docs) -> `Changed`
  - Deprecations -> `Deprecated`
  - Removed features -> `Removed`
  - Security fixes -> `Security`
- **Exclude (Internal-Only)**:
  - `chore`, `build`, `ci`, `test`, internal tooling updates, merge commits.

### Format Standards

- Write entries in imperative present tense.
- Group entries under appropriate category headers (`Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`).
- Consolidate repetitive commits into single meaningful summary items.

## 6. Git Worktrees for Parallel Execution

Use Git worktrees to manage concurrent tasks or hotfixes without stashing uncommitted work.

### Worktree Operations

- Create isolated worktree for parallel task:

  ```bash
  git worktree add ../repo-<task-name> -b feature/<task-name>
  ```

- List active worktrees:

  ```bash
  git worktree list
  ```

- Remove worktree after completion:

  ```bash
  git worktree remove ../repo-<task-name>
  ```

### Worktree Hygiene

- Maintain strictly one branch per worktree.
- Never attach two worktrees to the same branch.
- Remove worktree directory immediately after merging the task branch.

## 7. Anti-Patterns & Safety Guards

- Non-descriptive commits: Never use vague commit subjects like `fix`, `wip`, `update`, `changes`.
- Rewriting shared history: Never force-push (`git push -f`) to public branches (`main`, `develop`). Use `--force-with-lease` only on personal feature branches if strictly necessary.
- Bypassing pre-commit validation: Never use `git commit --no-verify` to bypass validation hooks.
- Orphaned local branches: Always run `git fetch --prune` after merging PRs to clean up remote tracking branches.
- Direct main branch commits: Never commit directly to `main` or `develop` branches without PR review.
