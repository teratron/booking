# Rollback — Return the Live Site to the Previous Release

Audience: AI agent.

You are executing a production rollback procedure. It returns the live site to the version that was running immediately before the one currently live, for a problem noticed after the automatic post-deploy health check already passed. Read this entire file before taking any action. Follow the numbered steps in order; do not skip, reorder, or combine them.

## Preconditions (verify all before starting)

- A new version was released recently, and the observed problem began after that release, not before it.
- The automatic safety check (see "Context" below) has already had its chance and either did not fix the problem, or clearly did not run: several minutes have passed since the release, the site is still unhealthy, and no "rolled back" entry has appeared on the repository's "Releases" page for this incident.
- The exact version number (tag) of the last release known to be healthy is identified — either supplied to you, or determinable by reading the "Releases" page.
- You hold, or have access to, GitHub credentials with permission to trigger workflow runs on this repository.

If any precondition cannot be confirmed as true, do not proceed past this section — see "Stop and hand back to a person if" below.

## Context: automatic rollback (informational, not an action you take)

After every deploy, the pipeline checks the new release's health within roughly a minute. If that check fails, the pipeline automatically redeploys the previous known-good version and checks health again, with no human or agent action required. A successful automatic rollback is visible as a new "rolled back" entry on the repository's "Releases" page. If such an entry already exists for the current incident, the procedure below does not apply — treat the rollback as already complete.

The steps below apply only when that automatic check already passed and the problem was noticed later.

Note on authority: triggering a manual rollback under this procedure is an action you are permitted to perform on your own, without waiting for a human to approve it — this is unlike the deploy pipeline's own reviewer-approval gate, which does require a human. The only action that always requires a human, and that never occurs in this procedure, is declaring a release "irreversible" — that declaration belongs solely to the deploy procedure and is never made or needed here, since a version that reached the live site was already required to declare any irreversible database change before it shipped, which is what makes redeploying its predecessor safe.

## Steps

### Step 1 — Identify the target version

Precondition: The "Releases" page on the repository is reachable and lists prior versions with their outcomes.
Action: Read the "Releases" page and identify the exact version number (tag) of the most recent release that was healthy before the current problem started. If this version was already supplied to you, verify it against the "Releases" page instead of trusting it blindly.
Expected outcome: One exact version string is confirmed (for example, `v1.4.0`).

### Step 2 — Open the Actions tab

Precondition: You have access to the repository on GitHub.
Action: Navigate to the repository's "Actions" tab.
Expected outcome: The Actions tab loads and lists the repository's workflows, including one named "Release".

### Step 3 — Open the "Release" workflow

Precondition: The "Release" workflow is visible in the Actions tab's workflow list.
Action: Open the "Release" workflow.
Expected outcome: The workflow's run history page loads and a "Run workflow" manual-trigger control is present.

### Step 4 — Start the rollback run

Precondition: The exact target version from Step 1 is confirmed.
Action: Use the "Run workflow" control to start a new run, supplying the target version identified in Step 1 as the requested input value.
Expected outcome: A new run appears at the top of the run list, labeled with the submitted version, in an in-progress state. If another release or rollback is already running against production, this run queues automatically and starts after it — the pipeline serializes releases and rollbacks by design, so this is expected behavior, not a fault, and requires no corrective action.

### Step 5 — Wait for the run to finish

Precondition: Step 4's run is in progress.
Action: Poll or watch the run's status until it reaches a terminal state. Do not start a second run against the same target while this one is in progress.
Expected outcome: The run reaches either success (all steps completed) or failure (one step reports non-zero / failed status). On failure, do not retry automatically — go to "If a step does not work" below and classify the failure before taking any further action.

### Step 6 — Confirm the site is healthy and the record exists

Precondition: Step 5 ended in success.
Action: Check the live site's health-check endpoint or observable behavior, and re-read the "Releases" page.
Expected outcome: The site reports healthy, and a new "Releases" entry exists naming the version now live, who or what triggered it, the timestamp, and a successful outcome.

## Done condition

The procedure is complete when the "Releases" page shows a new, successful entry for the rollback naming the target version from Step 1, and Step 6's health check confirms the live site is healthy.

## If a step does not work

- **Step 1 — the last-healthy version cannot be determined with confidence.** Do not guess. Treat this as a hard stop (see below).
- **Step 2 or 3 — the Actions tab, or the "Run workflow" control, is not accessible.** This indicates missing permissions on the credentials in use. Do not attempt to work around it (for example, by using different, unverified credentials). Treat this as a hard stop.
- **Step 5 — the run fails (red/failed status).** Read the failed run's step list and classify the failure before taking any further action:
  - *Code-level failure* — the failing step inspects the project's own code (for example, an automated quality-gate step) and its output names a specific failing check. This is unusual for a rollback, since it redeploys a version that was already live and healthy before. Do not retry. Report the exact failing step's name and output to a human developer and wait for guidance.
  - *Infrastructure-level failure* — the failing step is a setup step (checkout, environment setup, registry login, and similar) and its output mentions a network timeout, connection failure, or a dependency service that never became ready. Re-run only the failed jobs, once. If the identical failure recurs on that single retry, stop retrying — do not loop — and report it as an infrastructure outage.
- **Step 6 — the run succeeded but the site is not healthy.** Do not start a second manual rollback. The pipeline's own automatic safety net is already attempting one more recovery in this situation; if that also fails, it halts the site in maintenance mode and notifies whoever is responsible on its own. Treat this as a hard stop and wait for that notification, or escalate directly, rather than repeating this procedure.

## Stop and hand back to a person if:

- Any precondition in "Preconditions" above cannot be confirmed as true.
- The last-known-healthy version cannot be identified with confidence from the "Releases" page (Step 1) — never guess or infer a version number.
- Access to trigger the "Release" workflow is unavailable or unverified (Steps 2–4) — never substitute unverified or borrowed credentials to work around this.
- A run fails and step-inspection shows it is a code-level failure, not an infrastructure one (Step 5) — hand off to a developer with the exact failing step name and output; do not retry.
- An infrastructure-level failure (Step 5) recurs identically after exactly one "Re-run failed jobs" retry — report as an outage; never retry a second time.
- A rollback run succeeds but the live site remains unhealthy afterward (Step 6) — this means the pipeline's own second-attempt safety net is or will be engaged; do not run a third rollback yourself.
- Anything in this incident appears to require declaring a release "irreversible," or otherwise touches an irreversibility decision — that declaration is exclusively a human, deploy-time action and is never made as part of this procedure; stop and escalate rather than attempting to reason about it here.

Triggering the manual rollback itself (Steps 2–4) is explicitly not a hand-back condition — you are authorized to perform that action without waiting for human approval, per the "Context" section above.
