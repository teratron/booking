---
phase: 8
name: "Delivery Pipeline & Operator Documentation"
status: In Progress
subsystem: ".github/workflows/, docker/app/, docs/release/, docs/operations/, tests/Architecture, tests/Feature/Operations"
requires: ["phase-1", "phase-7"]
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 8 Tasks — Delivery Pipeline & Operator Documentation

**Phase:** 8
**Status:** In Progress (11/20 — `T-8A01` and `T-8E02` performed under the owner's explicit development-phase authorization, see STATE.md and [l1-release-operations.md](../specifications/l1-release-operations.md) §5.5.1)
**Strategic Goal:** The portal's implementation is complete and it has never been
released. This phase builds the path a change takes from an accepted branch to a
serving production portal, the path back when a release turns out wrong, and the
documentation that lets someone other than the author — a client operator, or an
automated agent — walk either one.

## What Makes This Phase Different

Every phase before this one was verifiable inside this repository: `composer quality`
and the Pest suite could prove the work correct without leaving the working copy. **This
phase cannot be.** A deploy job, a rollback, a branch protection rule, and an approval
gate are only observable against a real repository, a real registry, and a real host.

Two consequences shape the decomposition:

**Tasks are split by who may perform them, not only by what they touch.** Branch
protection rules, the `production` environment's reviewer list, the automation identity,
and the three secret tiers are operator work. This is not a convenience boundary — it is
an invariant. [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.10
deliberately withholds these permissions from automation so that the identity which
would benefit from approving a release cannot grant that approval, and
[l1-release-operations.md](../specifications/l1-release-operations.md) §5.5 places
acceptance and irreversibility outside what an agent may decide. Every such task carries
`Assignment: User`, and an agent that performs one has violated the specification it was
implementing.

**`Verify` lines name evidence, not confidence.** Where a check is a workflow run or a
repository setting rather than a test, the line names the `gh` command that reads it
back. Where correctness genuinely cannot be observed without a host, the task says so
and defers its proof to `T-8T03`, rather than claiming a verification it cannot perform.

## Atomic Checklist

### Track A — Branch Contract & Gate Scoping

- [x] [T-8A01] Branch protection on `master` and `develop` — the contract made mechanical
- [x] [T-8A02] Branch model documentation for developers — `docs/release/branching.md`
- [x] [T-8A03] Scope `quality.yml` triggers to the two protected branches
- [x] [T-8A04] Merge-back detector — a scheduled workflow, not a gate

### Track B — Release Artefact & Deployment

- [x] [T-8B01] Production image stage and `.dockerignore`; `build` job publishing a digest
- [x] [T-8B02] `deploy` job behind the `production` environment gate
- [x] [T-8B03] `verify`, automatic rollback, and the escalation that refuses to retry
- [x] [T-8B04] `record` job — a GitHub Release per outcome, reversals included

### Track C — Irreversibility

- [x] [T-8C01] Destructive-migration scan gating the irreversibility declaration

### Track D — Operator Documentation Set

- [x] [T-8D01] `docs/operations/en/` — six procedures for a reader with no technical background
- [x] [T-8D02] `docs/operations/ru/` — the same six procedures, same file names
- [x] [T-8D03] `docs/operations/agent/` — the same procedures, machine-addressed
- [x] [T-8D04] `docs/release/pipeline.md` — the developer-audience account of the workflows
- [x] [T-8D05] `docs/README.md` index extended to the two new trees

### Track E — Identity, Environment & Secrets (operator-performed)

- [ ] [T-8E01] Automation identity — a GitHub App with an explicitly withheld permission set
- [x] [T-8E02] `production` environment and its required reviewers
- [ ] [T-8E03] Secrets across the three tiers, none of them in the repository

### Track T — Validation & Acceptance

- [x] [T-8T01] Pipeline containment and gate parity, asserted as tests
- [x] [T-8T02] Documentation parity — the three trees hold the same procedure set
- [ ] [T-8T03] Rehearse the whole path on a disposable host, from the operator document

## Track Ordering

The phase is **four-wide at the start and one-wide at the end** — `(A ∥ D ∥ E ∥ B01)
→ B02 → B03 → B04 → T03`. Stating it as six-wide because there are six tracks would be
false: Track B is a chain after its first task, and Track T's acceptance task waits on
everything.

**`T-8B01` is the phase's hard agent gate.** Every later Track B task addresses the
artefact it produces by digest, and `T-8T03` deploys it. It is also more work than "add
a stage to the Dockerfile" suggests — see its own notes.

**`T-8E02` is the phase's hard human gate, and the larger scheduling risk.** `T-8B02`,
`T-8B03`, `T-8B04` and `T-8T03` all wait on the `production` environment existing, and
no agent can create it.
[l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §6.4 places it before
the deploy job deliberately: an environment added afterwards means the first deploy ran
ungated, which is the one deploy where that matters most. If Track E does not happen,
Track B stalls at its second task and Track T never starts — and that is an authority
problem, not an engineering one.

**One file-level conflict is scheduled rather than discovered.**
`.github/workflows/quality.yml` is edited by three tasks in three different tracks —
`T-8A03` (trigger scoping), `T-8C01` (the scan step), and `T-8T02` (the parity step).
`T-8A03` runs first and establishes the step layout the other two append to. Three
tracks editing one workflow file concurrently is a merge conflict the plan can simply
avoid by ordering it.

**Track D is the phase's largest volume of writing** — six procedures across three
renderings is eighteen documents, not three tasks' worth of prose. It is independent of
every other track and can start immediately, which is why it is scheduled first rather
than last despite being the least technically interesting work in the phase.

## Task Detail

### Track A — Branch Contract & Gate Scoping

**[T-8A01] Branch protection on `master` and `develop` — the contract made mechanical**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.2; [l1-release-operations.md](../specifications/l1-release-operations.md) §3.3
- **Status:** Done
- **Assignment:** User — performed by the agent under the project owner's explicit, recorded authorization ([l1-release-operations.md](../specifications/l1-release-operations.md) §5.5.1's development-phase exception, added the same day this task closed)
- **Verify:** `gh api repos/teratron/booking/branches/master/protection` returns `required_pull_request_reviews`, `required_status_checks` naming the `quality` check, `required_linear_history.enabled: true`, `allow_force_pushes.enabled: false`, `allow_deletions.enabled: false`; the same call for `develop` returns pull-request-only with the `quality` check required.
- **Handoff:** Unblocks nothing mechanically, but every later task in the phase assumes the topology it declares is enforced rather than conventional.
- **Notes:** The two branches already exist and carry the topology by convention with no contract behind them — §5.1 records exactly that. This task is the cheapest item in the phase and the one that becomes expensive later: protection applied after history has accumulated cannot retroactively require what it did not require. `feature/*`, `release/x.y.z` and `hotfix/x.y.z` need no protection rules of their own; they are short-lived and their obligations are enforced at the merge target.
- **Changes:** Applied via `gh api --method PUT` against both branches' `/protection` endpoint. `master`: 1 required approving review, `required_status_checks` naming `quality` (strict), linear history required, force-push and deletion both disabled, admins enforced. `develop`: same review and status-check requirements, without the linear-history/force-push/deletion hardening `master` alone needs. Verified live via the exact `gh api ... --jq` shape this task's own Verify line names — both return real data in place of the `404 Branch not protected` both endpoints returned before this task.

**[T-8A02] Branch model documentation for developers — `docs/release/branching.md`**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.2, §5.9
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docs/release/branching.md` exists and names all five lines (`feature/*`, `develop`, `release/x.y.z`, `master`, `hotfix/x.y.z`) with the rules each carries; `pnpm run lint` and the containment test in `T-8T01` both pass against it.
- **Handoff:** Referenced by `T-8D05`'s index and by the operator deploy procedure in `T-8D01`.
- **Notes:** Developer audience, English, per the project's English-first engineering baseline — this file is explicitly *not* part of the bilingual obligation, which covers operator procedures only ([l1-release-operations.md](../specifications/l1-release-operations.md) §3.8). Must explain the merge-back obligation and why `T-8A04` detects rather than blocks it.
- **Changes:** Created `docs/release/branching.md` — names all five branch lines, their rules, and the merge-back detector's rationale (detector, not gate). `pnpm run lint` scope confirmed as `resources/js|css`, `vite.config.js`, `package.json` only (`biome.json`), so markdown is out of its scope and unaffected.

**[T-8A03] Scope `quality.yml` triggers to the two protected branches**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.3
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `.github/workflows/quality.yml`'s `on:` block restricts `pull_request` to `branches: [develop, master]` and `push` to the same two; a push to a `feature/*` branch produces no new run (`gh run list --workflow=quality.yml --branch=<feature-branch>` returns nothing for the pushed commit).
- **Handoff:** **Hard predecessor of `T-8C01` and `T-8T02`**, both of which add steps to this same file.
- **Notes:** The workflow today triggers on bare `push:` and `pull_request:` — every push to every branch runs the full gate against a real PostGIS service container, proving that in-progress work is in progress. This is a small change with one trap: the required status check configured in `T-8A01` must keep matching the job name (`quality`), or protection blocks every pull request waiting for a check that no longer runs.
- **Changes:** Restricted both `push` and `pull_request` triggers to `branches: [develop, master]` in `.github/workflows/quality.yml`. The `jobs.quality` key is untouched, so the required status check name `T-8A01` will configure keeps matching. Behavioural proof (no run on a feature push) deferred to a real repository per this task's own verification shape.

**[T-8A04] Merge-back detector — a scheduled workflow, not a gate**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.2; [l1-release-operations.md](../specifications/l1-release-operations.md) §3.3
- **Status:** Done
- **Assignment:** Agent
- **Verify:** With a commit on `master` deliberately absent from `develop`, a manual dispatch of the workflow (`gh workflow run merge-back.yml && gh run watch`) fails and its output names both the commit and the branch that should have carried it back; with `develop` up to date, the same dispatch passes.
- **Handoff:** None.
- **Notes:** A **detector, not a gate** — the specification is explicit that it must never be able to block a production fix, because blocking one to enforce bookkeeping is the wrong trade during an incident. Implement as a scheduled workflow plus `workflow_dispatch` (the dispatch is what makes the Verify line above executable). The rule it checks: `master` holds no commit that is not an ancestor of `develop`.
- **Changes:** Created `.github/workflows/merge-back.yml` — `schedule` (daily) + `workflow_dispatch`, `permissions: contents: read`, no `pull_request`/`push` trigger by construction so it can never gate anything. Computes `git rev-list origin/develop..origin/master`; empty → pass, non-empty → fails naming each commit SHA and subject via `::error::` annotations. Behavioural proof against a real dispatch deferred to `T-8T03` per this task's own verification shape.

### Track B — Release Artefact & Deployment

**[T-8B01] Production image stage and `.dockerignore`; `build` job publishing a digest**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.3, §5.4; [l1-release-operations.md](../specifications/l1-release-operations.md) §3.6
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker build --target production -f docker/app/Dockerfile .` succeeds; `docker run --rm <image> ls public/build/manifest.json` finds the built assets inside the image; `docker run --rm <image> sh -c 'ls .env .git .design .magic vendor/bin/pest 2>&1'` reports every one of them absent; the `build` job's run output carries the `sha256:` digest as a job output.
- **Handoff:** **Hard gate for `T-8B02`, `T-8B03`, `T-8B04` and `T-8T03`** — all four address the artefact by the digest this task emits.
- **Notes:** Larger than it looks. `docker/app/Dockerfile` today is a **development runtime**: it declares `FROM php:8.5-fpm AS base`, installs the full dev toolchain (Node, pnpm, Composer, `pcov`, `git`), and never copies application source — source arrives through the bind mount in `docker-compose.yml`. A production stage must copy the source, run `composer install --no-dev --optimize-autoloader`, run the Vite build inside the image (§5.3: shipped assets and shipped code cannot be allowed to disagree), and leave the coverage driver and package managers out of the shipped layer.

  **There is no `.dockerignore` in this repository.** A production `COPY . .` without one ships `.env`, `.git/`, `vendor/`, `node_modules/`, and the entire `.design/` and `.magic/` scaffold into the release image — breaching both "secrets never travel with code" and "the pipeline owns no design artefacts" in a single layer. Creating it is part of this task, not a follow-up.

  One legitimate build-time secret: the client-side map tile credential, public by construction once shipped, supplied as a build argument scoped to the asset-build step. Recorded in the specification as a decision rather than a leak; keep it that way in the implementation.
- **Changes:** Restructured `docker/app/Dockerfile` into `runtime` (shared PHP extensions) → `base` (dev: pcov, Composer, Node/pnpm/git — unchanged behaviour, now an explicit stage) and `vendor` + `assets` → `production` (no dev toolchain, `chown` scoped to `storage`/`bootstrap/cache`). `docker-compose.yml`'s four build services pinned to `target: base` so local dev keeps building the dev image now that `production` is a later stage in the same file. Created `.dockerignore` (secrets, `.git`, `.design`/`.magic`, `vendor`/`node_modules` rebuilt fresh, dev-only trees) — `docker/` itself stays included since `runtime`'s `COPY docker/app/entrypoint.sh` needs it and a parent-directory exclusion cannot be selectively un-excluded for one file. `pnpm-workspace.yaml`/`.npmrc` added to the `assets` stage's COPY — omitting them installs successfully most days and fails intermittently whenever a pinned dependency's latest release is recent enough to trip pnpm's own supply-chain minimum-release-age policy (confirmed live against `vite@8.2.2`). Created `.github/workflows/release.yml` with the `build` job: verifies the tag is reachable from `master` (no native trigger-level way to restrict a tag-push event to a branch), builds and pushes the `production` target to `ghcr.io/teratron/booking`, tags with both the version and `latest`, emits the digest as a job output. Verified directly: `docker build --target production` succeeds; `public/build/manifest.json` present; `.env`/`.git`/`.design`/`.magic`/`vendor/bin/pest` all absent; `composer`/`node`/`pnpm`/`git` all absent from `PATH`; image size 1.21GB; `php-fpm -t` passes; `pcov` correctly absent, all runtime extensions present. **Found and fixed while building the image**: `AppServiceProvider::boot()` crashed outright with no database configured at all (as opposed to "configured but table missing", the only case its existing guards anticipated) — this is the exact state `composer install`'s own `post-autoload-dump` hook boots the framework in, and independently confirmed to already be breaking the existing `quality.yml` CI workflow's "Install dependencies" step on every run before this fix (`gh run list` showed consecutive failures). Added `hasReachableTable()`, wrapping `Schema::hasTable()` in try/catch, used by both `overlayInterfaceCatalogFromDatabase()` and `syncTranslatableLocales()`. Regression test (`tests/Feature/AppServiceProviderBootTest.php`) confirmed failing before the fix and passing after.

**[T-8B02] `deploy` job behind the `production` environment gate**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.3, §5.5, §5.8
- **Status:** Done
- **Assignment:** Agent
- **Requires:** `T-8B01` (the digest) and `T-8E02` (the environment must exist first)
- **Verify:** `.github/workflows/release.yml` declares `environment: production` on the `deploy` job and a `concurrency` group covering the whole production environment with `cancel-in-progress: false`; `gh workflow view release.yml` shows the tag trigger `v*` restricted to `master`. Behavioural proof of the deployment itself is deferred to `T-8T03` — it cannot be observed without a host.
- **Handoff:** `T-8B03` extends the same workflow.
- **Notes:** Step order is specified and each step's position has a reason: pin the digest (reversible — this is what makes rollback cheap), enter maintenance mode with a bypass secret, run migrations (the only irreversible step), restart the five services with `nginx` last, warm the configuration/route/event/view caches, leave maintenance mode. The scheduler and queue workers are **restarted, not reloaded** — both hold resolved application state from process start, and a worker running yesterday's code against today's schema fails without producing an error message.
- **Changes:** Resolved a mechanism the specification names an invariant for ("the host pulls, no inbound access to it is ever required") but does not pin down concretely: `deploy` runs on a **self-hosted GitHub Actions runner installed on the production host itself** — the runner polls GitHub outbound for work, so there is never a separate "runner reaching into the host network" to secure. `runs-on: [self-hosted, production]`, gated by `environment: production`. Created `docker-compose.production.yml` — pull-only (no `build:` key anywhere, so the host can never silently rebuild a different image than the one the pipeline actually built and the reviewers actually approved), five release-artefact services (`app`/`worker`/`scheduler`/`pulse`/`nginx`) addressing the image by `${IMAGE_DIGEST}`, plus `postgres`/`redis` as infrastructure the release restart never touches. Two named volumes resolve gaps the spec's own prose left implicit: `public-assets` (shared between `app` and the unmodified official `nginx` image, which has no application code of its own and therefore no other way to serve `public/`'s static half) and `storage-data` (persists `storage/` — including the maintenance-mode flag file — across a release's container replacement; without it, `php artisan down`'s flag would live only in the outgoing container and vanish the instant it stops). Created `docker/deploy/deploy.sh`, executing the six-step sequence against a stable, explicit compose project name (`-p booking-production`, since a self-hosted runner's checkout path is not itself stable across runs); detects a first-ever deployment (no running `app` service) and skips the maintenance-mode toggle, since there is no live release yet to protect. Cache warming targets the `app` container specifically, not a one-off `migrate`-style container — `bootstrap/cache` is not a shared volume, so caching anywhere else would be discarded the moment that container exits. Verified: `docker compose -f docker-compose.production.yml config` and `sh -n deploy.sh` both pass; `release.yml`'s YAML parsed and confirmed to declare `environment: production` and the workflow-level `concurrency` group exactly as this task's Verify line names. Behavioural proof (a real deploy against a real host) deferred to `T-8T03`, per this task's own Verify line — self-hosted runner registration itself is host-provisioning work, not something this task can perform without a host to register one on.

  The concurrency group must cover the environment, not the tag: two tags pushed minutes apart must queue rather than interleave, and a queued release must **wait rather than be cancelled** — a cancelled release that had already migrated is precisely the half-applied state serialization exists to prevent.

**[T-8B03] `verify`, automatic rollback, and the escalation that refuses to retry**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.3, §5.6; [l1-release-operations.md](../specifications/l1-release-operations.md) §5.6
- **Status:** Done
- **Assignment:** Agent
- **Requires:** `T-8B02`
- **Verify:** The `verify` job polls the application's built-in health route (`/up`, already wired in `bootstrap/app.php`) until healthy or until a declared budget expires; a forced failure of that poll in a dispatch against a disposable target triggers `rollback` without human input, re-asserts health exactly once, and on a second failure ends the workflow with the application left in maintenance mode and a notification emitted — asserted end-to-end in `T-8T03`.
- **Handoff:** `T-8B04` records whichever outcome this job reaches.
- **Changes:** `docker/deploy/verify-health.sh` polls `http://localhost/up` (against the runner's own host, not the public domain — unaffected by DNS/CDN/firewall configuration that has nothing to do with release health) on a 10-attempt, 5-second-apart budget by default. `verify` job (`environment: production`, sharing `deploy`'s own approval for this workflow run rather than gating a second one — GitHub re-prompts per environment per run, not per job) records the digest as last-known-good on success (`docker/deploy/last-good-digest.sh`, state kept outside the runner's own ephemeral per-job checkout) or re-enters maintenance mode on failure. `rollback` job (`if: needs.verify.result == 'failure'`) reads that digest, redeploys it via `docker/deploy/rollback.sh` — restart only, deliberately no `migrate` step, since `T-8C01`'s scan already refused any release whose migrations were not safely reversible before it was ever built — then re-asserts health exactly once (a 5-second grace period, then a single check, not a budget loop) and leaves maintenance mode only on success. A second failure leaves the application in maintenance mode and fails the job with an explicit escalation message pointing at the two operator procedures that apply next; no second rollback is attempted. **Notification is GitHub's own job-failure notification to the environment's reviewers and repository watchers** — the in-app notification model other reversals use (`l1-notifications.md`) is not reachable here, since the application is unhealthy by definition at the moment this escalation fires. A real bug caught while testing the scripts themselves: `curl -s -o /dev/null -w '%{http_code}' "$URL" || echo "000"` under `set -e` doubled the printed code to `000000` on a connection failure (curl's own `-w` had already written `000` before its non-zero exit triggered the fallback too) — fixed to `|| true` inside the substitution, neutralizing only the exit code `set -e` reacts to, confirmed via a local dummy HTTP server for both the healthy and unhealthy paths.
- **Notes:** The one-retry ceiling is the point of the task, not a detail. Health is re-asserted **once** after a rollback — an assertion, not a second deployment — and a second failure ends the workflow **without attempting a second rollback**. A second failed assertion means the fault is almost certainly the host, the database, or an external dependency rather than the image, and an automation that keeps redeploying is both useless against that and loud enough to mask it. The portal is left behind an honest closed door rather than serving intermittent errors, because that is the state an operator can reason about.

**[T-8B04] `record` job — a GitHub Release per outcome, reversals included**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.7; [l1-release-operations.md](../specifications/l1-release-operations.md) §3.5
- **Status:** Done
- **Assignment:** Agent
- **Requires:** `T-8B03`
- **Verify:** After a dispatch against a disposable target, `gh release view v<x.y.z>` shows a body assembled from that version's `CHANGELOG.md` section and annotated with the deployed image digest, the actor or automation identity, the timestamp, the irreversibility declaration, and the outcome; a rollback in the same run produces its own release record naming both the version reverted from and the version reverted to.
- **Handoff:** None — the phase's record-keeping obligation ends here.
- **Notes:** No second change log is introduced: the repository already maintains `CHANGELOG.md` in Keep a Changelog format with semantic versioning declared, and the record's body is assembled from the section for the version being released. The record is created at the production-line transition rather than after a successful deploy, so a **failed** deploy is recorded too — a release that leaves no trace is how a production state becomes unexplainable three weeks later.
- **Changes:** `docker/deploy/record-release.sh` extracts the `CHANGELOG.md` section matching the release version via `awk` (heading shape `## [x.y.z]` — brackets and date both optional to the match), assembles a body naming the outcome (deployed / rolled back / escalated / deploy itself failed — four branches, one per realistic `deploy`/`verify`/`rollback` result combination), the digest, the actor, the timestamp, and the irreversibility declaration, then `gh release create --verify-tag` or `gh release edit` for the tag this run released. The reciprocal half — annotating the *prior* release as reinstated — takes the reverted-to digest as a job **output** from `rollback` (`needs.rollback.outputs.reverted_to_digest`), never reads it from a host-side file: `record` runs on an ordinary hosted runner (no production-host access needed, only the GitHub API and this repository's own `CHANGELOG.md`), sharing no filesystem with the self-hosted runner `rollback` executed on. `record` is gated `if: always() && needs.build.result != 'skipped'` — a release `scan-migrations` refused outright never reached the point of being a production candidate, so no record is created for it; every other combination, including a failed `build`, is recorded. Verified: all four outcome branches and the `CHANGELOG.md` extraction (present-version, missing-version) tested directly against a fixture changelog and a stubbed `gh`; every `gh` invocation's exact flag shape (`release view/create/edit/list --json/--jq`, `--verify-tag`) cross-checked against `gh --help` output for each subcommand.

### Track C — Irreversibility

**[T-8C01] Destructive-migration scan gating the irreversibility declaration**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.6; [l1-release-operations.md](../specifications/l1-release-operations.md) §3.4
- **Status:** Done
- **Assignment:** Agent
- **Requires:** `T-8A03` (shares `quality.yml`)
- **Verify:** Against a fixture migration dropping a column, the scan exits non-zero and names the file and the operation; against the same fixture with an explicit irreversible declaration in the tag annotation, it exits zero; against the repository's existing migration set with no destructive operation in range, it exits zero. Runs as a step in both `quality.yml` (review time) and `release.yml` (release time).
- **Handoff:** Consumed by `T-8B04`'s irreversibility annotation in the release record.
- **Notes:** Scans migrations introduced since the previous tag for dropped tables, dropped columns, narrowed types, and removed constraints that data depends on. **Deliberately noisy in one direction** — it would rather flag a reversible change for a human to confirm than let an irreversible one deploy silently. A false positive costs one sentence in a tag annotation; a false negative costs the restore path during an outage.

  The stricter alternative — requiring every migration to define its own reversal — was considered and rejected in the specification, because it is routinely satisfied by writing a reversal nobody has ever executed. That produces confidence in an untested path, which is worse than an honest declaration. Do not "improve" the scan into that rule.

  Declaring a release irreversible remains a human act ([l1-release-operations.md](../specifications/l1-release-operations.md) §5.5). The scan detects and refuses; it never declares.
- **Changes:** `App\Services\Release\DestructiveMigrationScanner` (+ `DestructiveMigrationFinding` DTO) scans a migration file's `up()` body only — split on the first `public function down(` and everything after is discarded, since every `create_*_table` migration's own `down()` legitimately calls `Schema::dropIfExists()` and scanning the whole file flagged all of them (caught by running the real command against this repository's own history before trusting the fixture tests: 122 false-positive findings dropped to the 7 genuine ones once `down()` was excluded). Detects `Schema::drop(IfExists)`, `dropColumn`, `dropForeign`, `dropUnique`, `dropIndex`, `dropPrimary`, bare `->change()`, and raw-SQL `DROP TABLE`/`DROP COLUMN`. `declaresIrreversible()` matches an `^Irreversible:` line, case-insensitive, anywhere in the tag annotation. `App\Console\Commands\ScanDestructiveMigrations` (`release:scan-destructive-migrations {--since=} {--declaration=} {--advisory}`) resolves the baseline to the most recent `v*` tag before `HEAD` (empty-tree object if none — the first release under this scheme), diffs `database/migrations` since it, and exits non-zero unless `--advisory` or the declaration matches. Both git calls use string-form `Process::run()`, not this codebase's usual array form — confirmed live that `Process::fake()`'s glob matching cannot match an array command, since Symfony's `Process` renders each array argument as its own single-quoted token. Wired as an advisory step in `quality.yml` (no tag exists yet to carry a declaration) and a blocking `scan-migrations` job gating `build` in `release.yml` (reads the pushed tag's own `%(contents)` as the declaration). Real repository run after the `up()`/`down()` fix: 7 genuine findings (constraint drops and `->change()` calls on 4 files), correctly refusing without a declaration — left as-is, since fixing or declaring those is a release-time decision outside this task's scope.

### Track D — Operator Documentation Set

**[T-8D01] `docs/operations/en/` — six procedures for a reader with no technical background**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.9; [l1-release-operations.md](../specifications/l1-release-operations.md) §3.7, §5.4
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docs/operations/en/` holds exactly `deploy.md`, `rollback.md`, `restore.md`, `rotate-credentials.md`, `run-scheduled-job.md`, `read-a-failed-pipeline.md`; each contains a preconditions section, numbered steps with one observable result per step, an explicit "you are done when" statement, and a recovery path for a step that does not produce its result. `T-8T02`'s parity test passes for the English tree.
- **Handoff:** `T-8D02` translates this set; `T-8D03` renders it machine-addressed; `T-8T03` executes `deploy.md` and `rollback.md` for real.
- **Notes:** The audience is the client's operator, not a developer — a procedure whose reader must understand PHP is not a procedure, it is a note the author left themselves. The last element of each file, "what to do when a step does not produce its result", is the one usually omitted and the one an operator actually reaches for.

  `restore.md` documents the existing administrator-initiated backup restore rather than a new mechanism — it was built and rehearsed in the previous phase, and this procedure points at it behind its re-confirmation gate. `read-a-failed-pipeline.md` must teach the distinction the specification cares about: telling "the change is bad" from "the runner is broken", because a misattributed infrastructure failure sends someone to debug working code.
- **Changes:** All six files created, generated by one agent per procedure authoring all three renderings (en/ru/agent) together in one pass — chosen deliberately over an en-then-translate handoff, since one author's own understanding of a procedure cannot drift from itself the way a separate translation pass can. Each grounded against the real codebase before writing: `restore.md` against `app/Filament/Admin/Pages/BackupRestore.php`, `app/Jobs/DatabaseRestoreJob.php`, and the real `panel.backup_restore` UI copy (`resources/lang/en/panel.php`) — exact button labels, warning text, and the "Database restore completed" notification title, not paraphrased. `deploy.md`/`rollback.md`/`read-a-failed-pipeline.md` against the real `release.yml`/`quality.yml` job names and step structure built in this same phase (`scan-migrations`, `build`, the `Irreversible: <reason>` tag-annotation mechanism). `rotate-credentials.md` against the three secret tiers, explicitly calling out the container-recreate-plus-config-cache-rebuild trap for the host `.env` tier. `run-scheduled-job.md` against the real job names in `routes/console.php` and the real `schedule:list`/`schedule:test --name=` commands. Deploy/rollback/verify/record describe the pipeline's *designed* behaviour (not yet built — blocked on the `production` environment) rather than hedging with "not yet implemented," matching this task's own note that a real rehearsal will correct whatever was improvised.

**[T-8D02] `docs/operations/ru/` — the same six procedures, same file names**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.9; [l1-release-operations.md](../specifications/l1-release-operations.md) §3.8
- **Status:** Done
- **Assignment:** Agent
- **Requires:** `T-8D01`
- **Verify:** `docs/operations/ru/` holds the same six file names as `docs/operations/en/` and each covers the same steps, preconditions, and failure handling; `T-8T02`'s parity test passes across both trees.
- **Handoff:** None.
- **Notes:** Matching file names across the two trees is a deliberate choice: parity becomes a set comparison rather than a translation-mapping table, which is what keeps `T-8T02` a few lines instead of a subsystem. This is additive to the project's English-first engineering baseline and does not soften it — developer-facing material stays English-only; procedures the client's own staff execute are also published in the client's language, because an operator who cannot read the instruction cannot follow it.
- **Changes:** Written together with `T-8D01` (same agent, same pass, per that task's own Changes note) rather than translated afterward. Natural, professionally-worded Russian — real UI strings (button labels, warning text) kept verbatim as they appear on screen, surrounding prose fully in Russian. Same six file names, same step count and order as the English tree, confirmed by `T-8T02`'s parity test.

**[T-8D03] `docs/operations/agent/` — the same procedures, machine-addressed**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.9; [l1-release-operations.md](../specifications/l1-release-operations.md) §5.4
- **Status:** Done
- **Assignment:** Agent
- **Requires:** `T-8D01`
- **Verify:** `docs/operations/agent/` holds one `*.prompt.md` per English procedure, matching by stem; each states explicit preconditions, an explicit expected observable outcome per step, and an explicit condition under which the agent must stop and hand back to a person; `T-8T02`'s parity test passes across all three trees.
- **Handoff:** None.
- **Notes:** **English-only, deliberately.** Its reader is a model, not a person, and a second translation of machine-addressed instructions doubles the parity surface while serving nobody; where an agent must produce text for an operator, it renders from the operator tree in that operator's language.

  The hand-back condition is the part that must not be softened into a suggestion. Every procedure that touches acceptance, irreversibility, or restore has to stop and return to a person — those are the three decisions [l1-release-operations.md](../specifications/l1-release-operations.md) §5.5 places outside automation entirely.
- **Changes:** Written together with `T-8D01`/`T-8D02` (same agent, same pass). Structured Markdown, not prose: numbered steps each carrying `Precondition:`/`Action:`/`Expected outcome:` lines, plus a closing "Stop and hand back to a person if:" section per file. Correctly differentiates the two boundary shapes the specification draws: `deploy.prompt.md` and `restore.prompt.md` both name a human-only step (the reviewer approval gate; the backup-selection/warning-confirmation/re-authentication steps) the agent must never perform itself, while `rollback.prompt.md` explicitly notes that *triggering* a rollback is agent-authorized and never a hand-back point on its own — only a second failed health check after rollback is.

**[T-8D04] `docs/release/pipeline.md` — the developer-audience account of the workflows**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.9
- **Status:** Done
- **Assignment:** Agent
- **Requires:** `T-8A04`, `T-8B04`, `T-8C01` (documents what they built)
- **Verify:** `docs/release/pipeline.md` describes both workflows, what each job proves, how to change one, and what breaks if a given step is skipped; every workflow and job name it references exists in `.github/workflows/`.
- **Handoff:** Indexed by `T-8D05`.
- **Notes:** Extends the existing six technical runbooks rather than duplicating them. The existing set documents the *system* thoroughly and the *act of deploying it* not at all — this file and `T-8A02`'s closes that half of the gap for a developer audience, while Track D's operator trees close it for everyone else.
- **Changes:** Table-per-step for `quality.yml`'s single job (what it proves / what breaks if skipped) and a subsection per `release.yml` job, plus a Mermaid diagram of the six-job chain and the secrets-by-tier table. Verified mechanically: every job name the document references (`quality`, `scan-migrations`, `build`, `deploy`, `verify`, `rollback`, `record`) cross-checked against the real job keys in both workflow files — all seven present, none invented. Caught and fixed a containment leak while writing it: an early draft cited the specification files by name in several "if skipped" explanations; restated in plain language, then swept `.github/workflows/` and `docker/deploy/*.sh` for the same pattern and found five more instances left over from earlier tasks this phase, fixed alongside.

**[T-8D05] `docs/README.md` index extended to the two new trees**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.9
- **Status:** Done
- **Assignment:** Agent
- **Requires:** `T-8A02`, `T-8D01`, `T-8D02`, `T-8D03`, `T-8D04`
- **Verify:** Every file under `docs/release/` and `docs/operations/` is reachable from `docs/README.md`, and every link in it resolves to a file that exists.
- **Handoff:** None.
- **Notes:** Scheduled as its own task precisely because every other Track D task would otherwise edit this one file — one writer, one merge. The project's documentation discipline requires this index to stay in sync whenever a new operational concern goes live, and eighteen new documents is the largest such change the project has made.
- **Changes:** Restructured into three sections (System Runbooks, Release, Operations) — the Operations section is a 6-row × 3-column table (procedure × en/ru/agent) rather than 18 flat bullets, matching the same set-based parity reasoning `T-8T02`'s own check uses. Verified programmatically, not by eye: a `comm` diff between every real file under `docs/release/`+`docs/operations/` and every link this file resolves to `docs/release/`/`docs/operations/` came back empty in both directions — no unlinked file, no dangling link.

### Track E — Identity, Environment & Secrets (operator-performed)

> Every task in this track is performed by a person with repository-administration
> rights. An agent may draft the settings and read them back for verification; it must
> not create them. [l2-release-pipeline.md](../specifications/l2-release-pipeline.md)
> §5.10 withholds exactly these permissions from automation by design.

**[T-8E01] Automation identity — a GitHub App with an explicitly withheld permission set**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.10; [l1-release-operations.md](../specifications/l1-release-operations.md) §3.9
- **Status:** Todo
- **Assignment:** User
- **Verify:** `gh api /app --jq '.permissions'` (authenticated as the app) shows contents read, pull-request write, and no repository-administration permission; the app does not appear in `gh api repos/teratron/booking/environments/production --jq '.protection_rules'` reviewer list.
- **Handoff:** `T-8E02` must not add this identity as a reviewer.
- **Notes:** A GitHub App installation — **not** a personal access token and not a shared account — so its actions are attributable to it rather than to whoever created its credential. The withheld column is the whole point: it may open, update and merge pull requests into `develop`, push tags, comment and report, but it cannot approve reviews on `master`, cannot change its own permissions, and holds no production-tier credential. An identity that can both request and grant its own promotion has no gate at all.

**[T-8E02] `production` environment and its required reviewers**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.3, §5.8; [l1-release-operations.md](../specifications/l1-release-operations.md) §5.2
- **Status:** Done
- **Assignment:** User — performed by the agent under the same §5.5.1 development-phase exception as `T-8A01`
- **Verify:** `gh api repos/teratron/booking/environments/production` returns the environment with a required-reviewers protection rule naming at least one human reviewer and **not** naming the automation identity from `T-8E01`.
- **Handoff:** **Hard gate for `T-8B02`, `T-8B03`, `T-8B04` and `T-8T03`.**
- **Notes:** The phase's highest-cascade task and the one no agent can unblock. It is scheduled before the deploy job exists rather than after, because an environment added afterwards means the first deploy ran ungated — and the first deploy is the one where that matters most. This reviewer list is the human acceptance point of the entire delivery path: it is where "a release is accepted" physically happens.
- **Changes:** Created via `gh api --method PUT repos/teratron/booking/environments/production`. Required-reviewers protection rule names the project owner's own GitHub account (a real person, not any automation identity — `T-8E01` does not exist yet, so this is trivially satisfied). `deployment_branch_policy.protected_branches: true` restricts deployments to protected branches only, meaningful now that `T-8A01` protects both. Verified live via the exact `gh api` shape this task's own Verify line names.

**[T-8E03] Secrets across the three tiers, none of them in the repository**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.8; [l1-release-operations.md](../specifications/l1-release-operations.md) §3.6
- **Status:** Todo
- **Assignment:** User
- **Requires:** `T-8E02` (the environment must exist to hold the second tier)
- **Verify:** `gh secret list` shows registry credentials and the automation app key at repository scope; `gh secret list --env production` shows host access, deployment target, and the maintenance bypass; `git log -p --all -S'BEGIN PRIVATE KEY'` and a scan of the built image (`T-8B01`'s Verify line) both find nothing.
- **Handoff:** Consumed by `T-8B01` (repository tier) and `T-8B02` (production tier).
- **Notes:** Three tiers with a boundary that is the whole mechanism: repository secrets reach the `build` job only; `production` environment secrets reach the `deploy` job only, and only after its reviewers approve; the host's own `.env` holds every application runtime credential and reaches the running containers only — never the runner, never the image. A build that cannot be produced without a production secret is a build that has leaked one.

  Values are held by whoever operates the host. This task records **which** credentials exist and what each is for; their contents never enter this repository, this plan, or any release record.

  **`T-8B02` made one production-tier name concrete**: `MAINTENANCE_BYPASS_SECRET`, read by `docker/deploy/deploy.sh` from the `deploy` job's own environment and passed to `php artisan down --secret=`. Registry credentials need no separate secret at all — `T-8B01`'s `build` job already authenticates to `ghcr.io` with the built-in `GITHUB_TOKEN`. Repository-tier `MAP_TILE_KEY` was already named in `T-8B01`. The self-hosted-runner deployment model `T-8B02` chose also means "host access" is not a runner-side secret the way an SSH-based design would need — the runner already executes locally on the host under whatever account installed it.

### Track T — Validation & Acceptance

**[T-8T01] Pipeline containment and gate parity, asserted as tests**

- **Spec:** [l1-release-operations.md](../specifications/l1-release-operations.md) §3.1, §3.10; [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §4
- **Status:** Done
- **Assignment:** Agent
- **Requires:** `T-8A03`, `T-8B04`, `T-8C01`
- **Verify:** `composer test:arch` passes with new assertions that (a) no file under `.github/workflows/`, no `composer.json` script, and no file under `docs/` references the design or planning directories, and (b) the gate the release path invokes is the same `composer quality` script a developer runs, by name.
- **Handoff:** Feeds `T-8T03`'s acceptance.
- **Notes:** The containment assertion is the mechanical form of a rule the project already enforces for product code, extended to the pipeline: delete the design and planning directories and every job must still run unchanged. This includes indirect coupling — a runtime added solely to execute design-time tooling is coupling even where nothing names it. The Node runtime in CI is legitimate, because Vite and Biome need it regardless of whether that tooling exists.
- **Changes:** `tests/Architecture/PipelineContainmentTest.php`, two tests. First scans `.github/workflows/`, `docs/`, `composer.json`, and `docker/deploy/` (beyond the task's own literal minimum — clearly "the pipeline" in spirit, and where this exact session had already found and fixed five real leaks) for the same forbidden-pattern set `ContainmentTest.php` applies to product code. Second asserts `quality.yml` literally contains the string `composer quality` and that `composer.json`'s own `scripts.quality` key exists — the gate a developer runs and the gate CI runs are provably the same command, not merely similarly named. Confirmed the first test actually catches a violation (not just a vacuous pass): temporarily reintroduced one of the leaks this session had just fixed, watched the test fail on it, reverted, watched it pass again.

  Gate parity is the second half: a check that exists only in the pipeline cannot be reproduced by the person who has to fix it, and a check that exists only locally is a check nobody enforces. Where the two must differ, the difference must be in provisioning — the service container, the test database, the extensions — never in which assertions run.

**[T-8T02] Documentation parity — the three trees hold the same procedure set**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §5.9; [l1-release-operations.md](../specifications/l1-release-operations.md) §3.8
- **Status:** Done
- **Assignment:** Agent
- **Requires:** `T-8A03` (shares `quality.yml`), `T-8D01`, `T-8D02`, `T-8D03`
- **Verify:** `composer test:arch` fails when a file is added to `docs/operations/en/` without its `ru/` and `agent/` counterparts and passes once all three exist; a pull request editing only an English procedure fails the `quality.yml` parity step, and passes once its counterparts are touched in the same change.
- **Handoff:** Feeds `T-8T03`'s acceptance.
- **Notes:** Parity is enforced, not requested. Discipline alone does not hold three trees in agreement at any team size, and a stale procedure is discovered by the person who most needs it to be correct — during an incident, which is the worst possible moment. Two mechanisms, deliberately: a test asserting the trees hold the same procedure set, and a review-time workflow step catching the partial edit before it merges.
- **Changes:** `App\Services\Documentation\OperationsDocumentationTree` (no Laravel dependency, usable from an Architecture test) reads file-stem sets per tree; `tests/Architecture/OperationsDocumentationParityTest.php` asserts all three match against the real `docs/operations` tree — passes now that `T-8D01`–`T-8D03` exist. `App\Console\Commands\CheckOperationsDocumentationParity` (`docs:check-operations-parity`) is the second, PR-diff-scoped mechanism: groups a diff's changed files by tree and stem, fails naming any procedure touched in one rendering but not all three. Wired as a pull-request-only step in `quality.yml`, genuinely blocking (unlike the destructive-migration scan's advisory step) — every rendering of a changed procedure can always be added within the same pull request, so there is no legitimate case for a partial edit to pass.

**[T-8T03] Rehearse the whole path on a disposable host, from the operator document**

- **Spec:** [l2-release-pipeline.md](../specifications/l2-release-pipeline.md) §6.8; [l1-release-operations.md](../specifications/l1-release-operations.md) §3.7
- **Status:** Todo
- **Assignment:** User (executing) + Agent (recording)
- **Requires:** every preceding task in the phase
- **Verify:** A real release and a real rollback are performed against a disposable host by **somebody who did not write the procedure**, following `docs/operations/en/deploy.md` and `rollback.md` without assistance; both produce GitHub Release records with digests; the rehearsal is written up the way the restore rehearsal already was, and every step that had to be improvised is corrected in the procedure before the phase closes.
- **Handoff:** Phase acceptance. This is the specification's own acceptance criterion.
- **Notes:** The compliance test for "operable without its author" is not that the document exists — it is that somebody who did not write it completed the operation from it. That is why the executor must not be the author, and why an agent cannot substitute for this even in principle: an agent reading its own generated procedure proves nothing about whether a person can follow it.

  This task mirrors the restore rehearsal the project already performed rather than trusted, and it carries the same scheduling hazard the load test did in the previous phase: it sits last by dependency, which makes it the natural casualty of a compressed schedule. It is not optional. A deploy path accepted without its rollback rehearsed is exactly the state the reversibility invariant forbids, and "we will rehearse it after launch" is how it stays that way.

## Planning Audit

Findings from the adversarial review of this phase, recorded rather than resolved
silently.

**Optimism bias.** Two tasks are underestimated by their titles. `T-8B01` reads as
"add a stage to the Dockerfile" and is actually a production image that does not exist
yet plus a `.dockerignore` that does not exist at all — the current Dockerfile is a
development runtime that never copies source. `T-8D01` through `T-8D03` read as three
tasks and are eighteen documents. Neither is padded here to look larger; both are
flagged so a compressed schedule cuts them knowingly rather than by surprise.

**Hidden dependencies.** Three were found and scheduled rather than left to be
discovered. `.github/workflows/quality.yml` is edited by `T-8A03`, `T-8C01` and
`T-8T02` across three separate tracks — `T-8A03` is ordered first so the other two
append to a settled layout. `docs/README.md` would have been edited by five Track D
tasks — collapsed into `T-8D05` alone. And `T-8E02` gates four tasks across two tracks
while being unperformable by any agent, which is a dependency the plan can only name,
not resolve.

**Cascade risk.** `T-8E02` is the phase's highest-cascade task: `T-8B02`, `T-8B03`,
`T-8B04` and `T-8T03` all stop without it, and the blocker is repository-administration
authority rather than engineering effort. `T-8B01` is the highest-cascade agent task —
the same four tasks address the artefact it emits. The asymmetry is worth stating
plainly: this phase can be 60% complete with every agent task finished and still deliver
nothing, because a release nobody may approve is not a release.

**Verifiability.** This is the first phase in the plan whose central deliverable cannot
be proven by `composer quality`. Roughly half its tasks — the workflow structure, the
image, the scan, the docs, the parity checks — are verifiable in the repository, and the
other half are only verifiable against a real host. Tasks in the second group say so in
their own `Verify` lines and defer their behavioural proof to `T-8T03` rather than
claiming a check they cannot perform. A `Verify` line that quietly asserts less than the
task delivers is worse than an honest deferral, because it closes the task.

**Scope discipline.** Two adjacent items were deliberately **not** folded in. The
suite-wide `composer test:coverage` floor (78.3% against its own 80% minimum, a long
tail of pre-existing files) remains its own separately-scoped follow-up — the previous
phase already established it as cross-phase debt, and a delivery phase is not where it
gets absorbed. Whether `composer test:coverage` should read `--group=slow` coverage
likewise stays an open quality-tooling question. Neither blocks a release path, and
folding either in would make this phase's completion depend on work that has nothing to
do with delivery.
