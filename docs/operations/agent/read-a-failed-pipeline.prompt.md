# Read a Failed Pipeline Run

Purpose: diagnose a failed "Release" GitHub Actions run — decide whether the change itself is at fault or the runner infrastructure had a transient hiccup, and act accordingly.

You are being asked to diagnose one failed run of the "Release" GitHub Actions workflow for this repository. Your job is strictly diagnostic: determine whether **Case A** ("the change itself is bad" — a step that actually inspects the project's own code or its database migrations failed) or **Case B** ("the runner is broken" — a step that only sets up the environment failed, before the project's own content was ever really touched) applies, and take the one matching, low-risk action defined below. You do not fix code, you do not approve a release, and you do not make any release/rollback/restore decision as part of this procedure.

## Preconditions

Verify all of the following before starting. If any is false, stop and follow the corresponding item in "Stop and hand back to a person if" below instead of proceeding.

- P1. You have read access to this repository's GitHub Actions runs — via an authenticated `gh` CLI session, an equivalent GitHub API token, or the GitHub web UI's "Actions" tab — sufficient to view run status, list step names, and read step logs.
- P2. You have been given, or can unambiguously determine, the identifier of the specific failed run to diagnose (a run ID, a run URL, or "the most recent run of the Release workflow triggered by tag `<tag>`").
- P3. You have a defined channel for handing back to a human developer (for example: posting a message, commenting on the run, or notifying whoever invoked this procedure) — every "Stop and hand back" condition below requires using it.

## Steps

1. **Open the failed run.**
   Precondition: P1 and P2 are satisfied.
   Action: Retrieve the run's step list, in the order the steps ran, with each step's status — for example `gh run view <run-id>`, or the equivalent GitHub API call, or the GitHub web UI's "Actions" tab entry for this run.
   Expected outcome: You have an ordered list of named steps for the "Release" workflow run, each labeled with a status of success or failure. If the run cannot be retrieved (bad ID, no access), treat this as the P1/P2 precondition failing and go to "Stop and hand back to a person if" below.

2. **Find the first failed step.**
   Action: Scan the ordered list from the top and locate the first step whose status is failure.
   Expected outcome: Exactly one step is identified as "the first failure". Any step after it in the list either did not run or was skipped as a consequence — do not inspect them for root cause, they carry no diagnostic value.

3. **Classify the failed step by its name.**
   Action: Read the exact name of the first failed step and place it into one of two groups.
   - Group A — the step actually inspects the project's own code or database migrations. Its name names a real check, for example "Run the PHP quality gate" or "Scan for destructive migrations".
   - Group B — the step only prepares the working environment, before the project's own content was ever really touched, for example "Checkout code", "Setup PHP", "Setup pnpm", "Set up Docker Buildx", or "Log in to the container registry".
   Expected outcome: The step is assigned to exactly one group. If the name does not clearly match either pattern, do not guess — proceed to step 4 and classify from the output content instead.

4. **Read the failed step's output.**
   Action: Retrieve the full log output of that step — for example `gh run view <run-id> --log-failed`, the equivalent API call, or expanding the step in the web UI — and read its final lines, where the failure is reported.
   Expected outcome: You have the literal text of the failure message from that step's log.

5. **Match the output to Case A or Case B.**
   Action: Compare the log text against these two signatures.
   - Case A signal: the output names a specific file, test, or rule that failed — a named test that did not pass, a named coding-style rule that was violated, or a database migration flagged as unsafe/irreversible.
   - Case B signal: the output names something outside the project's own content — a network timeout, "connection refused", a package or tool that failed to download, or a supporting service (such as a database container) that never became healthy.
   - Extra Case B corroboration: the exact same commit passed a run recently and nothing in the project has changed since.
   Expected outcome: You can state which case applies, in one sentence, citing the specific phrase in the log that supports it. If the output supports neither signature clearly, do not pick one — this is a hard-stop condition; go to "Stop and hand back to a person if" below and default the classification to Case A there.

6. **Act according to the case.**
   Action:
   - Case A: compose a message to the human developer responsible for the change, quoting the exact failed step name and the relevant log lines from step 4. Send it via the channel from P3. Do not modify, merge, revert, or otherwise alter the code, the migration, or the workflow yourself. Do not click "Re-run failed jobs".
   - Case B: trigger "Re-run failed jobs" for this run — for example `gh run rerun <run-id> --failed`, the equivalent API call, or the web UI's "Re-run failed jobs" button.
   Expected outcome:
   - Case A: the message has been sent through the P3 channel. This procedure ends here for this run; you are waiting on a human.
   - Case B: the run's steps begin executing again; the run's status will resolve to either all-success or a new failure.

7. **If you re-ran the pipeline (Case B), check the outcome.**
   Action: Poll the run's status until it resolves, then re-check every step's status.
   Expected outcome:
   - All steps succeeded: the transient hiccup is resolved. Nothing further to do; report the run as resolved through your normal reporting channel.
   - The same step failed again with the same or a materially similar failure signature: this is a hard-stop condition. Do not trigger "Re-run failed jobs" a second time for this run. Reclassify as Case A and go to step 6's Case A action, additionally noting in the message that a re-run was already attempted once and failed identically — this makes a genuine outage more likely than a passing hiccup.

## You are done when

You have correctly classified the failure as Case A or Case B and completed the matching action from step 6 — either the developer-facing message has been sent through the P3 channel and you are waiting on a human, or the pipeline was re-run once and every step now reports success.

## If a step does not work

- Step 1 returns no run, or an authorization error: P1 or P2 is not actually satisfied. Do not attempt to bypass access restrictions or guess at a run identifier. Go to "Stop and hand back to a person if" below.
- Step 3 cannot classify the step name into Group A or Group B with confidence: proceed to step 4 and classify from the log content instead — the log almost always resolves the ambiguity.
- Step 5 cannot match the output to either signature with confidence, even after reading the full log: do not guess. Default to Case A and go to "Stop and hand back to a person if" below.
- The "Re-run failed jobs" action is rejected (permission denied, no such action available to your credentials): do not attempt any workaround. Go to "Stop and hand back to a person if" below.
- Step 7's re-run fails with the same signature a second consecutive time: as stated in step 7, stop — do not re-run a third time under any circumstance.

## Stop and hand back to a person if:

- The failure is classified (or defaulted, per the rule above) as **Case A** — a check of the project's own code or database migrations failed. Send the developer-facing message and stop; do not attempt to fix, merge, revert, silence, or reclassify the finding yourself.
- The output cannot be matched to Case A or Case B with confidence after reading the full log (step 5's ambiguous case). Default to Case A and hand back — never proceed on an unresolved guess.
- A Case-B re-run fails with the same or a materially similar signature a second consecutive time (step 7). Stop permanently for this run; do not attempt a third re-run under any circumstance; report it to the developer as a likely genuine outage, quoting the output of both attempts.
- You lack sufficient access to view the run, read its logs, or trigger "Re-run failed jobs" (P1 not satisfied, or the action is rejected). Do not attempt to escalate your own privileges or bypass the restriction; request access or ask a human to perform the blocked action.
- The failed step's output indicates a database migration was flagged as unsafe or irreversible, or the failure otherwise concerns whether this release should proceed, be approved, rolled back, or have its data restored. Diagnosing which case applies is within scope of this procedure; deciding to approve, override, force through, or otherwise act on a release/rollback/restore/irreversibility decision is never within scope of this procedure — hand that decision to a person regardless of the Case A/B classification.
