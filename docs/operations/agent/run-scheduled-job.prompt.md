# Run Scheduled Job

This procedure directs you, an AI agent, to make one of the system's scheduled background jobs run immediately instead of waiting for its normal timed schedule — for example, to force an on-demand database backup before a risky change, or to make a stuck notification retry immediately. Follow every step in order. Do not skip verification of an expected outcome before moving to the next step.

## Preconditions (verify all before Step 1)

- You have been given, in this conversation, the exact name of the job to run, OR you can determine it unambiguously from the canonical list below. Canonical list of job names: `availability:sweep-stale`, `journal:archive`, `placement:sweep-expiry`, `analytics:rollup`, `analytics:compact`, `objects:sweep-staleness`, `availability:sweep-confirmation`, `notifications:retry-dispatch`, `content:archive-promotions`, `content:withdraw-news`, `seo:generate-sitemaps`, `backup:database`, `backup:media`, `backup:cleanup`, `backup:monitor`, `horizon:snapshot`.
- You have a working shell session with confirmed command execution access to the production server (not staging, not local development).
- Note: if the requested job is `backup:database`, a human operator could alternatively use the "Run backup now" button on the "Backups" page (staff panel, "System" section) instead of this procedure. That alternative is point-and-click only and is not available to you as an agent unless you have been given browser/UI automation tooling for the staff panel; absent that, use this command-line procedure, which covers `backup:database` and every other job.

## Steps

1. Identify the exact job name to run.
   Precondition: The job name has been supplied to you, or is uniquely identifiable in the canonical list above.
   Expected outcome: You hold one exact string matching, character-for-character (including the colon), one entry in the canonical list.
   If not: Do not guess or approximate a name. Stop and ask for clarification of which job is intended.

2. Open or confirm a command-line session connected to the production server.
   Precondition: You have the means to execute shell commands against the production host (directly, or via an already-connected session).
   Expected outcome: A command you issue returns output attributable to the production server (not an error indicating no such connection exists).
   If not: Do not attempt to fabricate a connection or proceed against an unconfirmed environment. Stop and hand back to a person (see final section).

3. Run: `docker compose exec app php artisan schedule:list`
   Precondition: Step 2 succeeded.
   Expected outcome: The command exits without a connection or execution error and prints a list of scheduled jobs. The exact job name identified in Step 1 appears verbatim in that output.
   If not: If the command itself fails to execute (for example "command not found", connection refused), you are likely not on the correct production host — do not retry blindly; verify the target host with a person. If the command succeeds but the job name is absent from the output, do not substitute a similar-looking name — the deployed job list may have changed since this procedure was written; stop and confirm the current correct name with a person before continuing.

4. Run: `docker compose exec app php artisan schedule:test --name=THE-JOB-NAME` (substitute the exact name confirmed in Step 3 for `THE-JOB-NAME`).
   Precondition: Step 3 confirmed the exact job name exists in the live schedule list.
   Expected outcome: The command exits with no error output. This is the sole success signal — the job has now executed immediately, instead of waiting for its normal schedule.
   If not: Any printed error output names the specific failure inside the job itself, not a fault in how you invoked the command. Do not re-run the same command repeatedly expecting a different result. Capture the exact error text verbatim and report it; do not attempt to diagnose or repair the underlying application, database, or storage issue yourself.

## You are done when

Step 4's command has exited with no error output. Report to the requester which exact job name was run and confirm no error was printed.

## If a step does not work

- Step 2 fails (no confirmed production command-line access, or credentials rejected): do not attempt alternate or guessed credentials. Stop and hand back to a person.
- Step 3's command fails to execute at all (for example "command not found", connection error): you are likely connected to the wrong host. Stop and hand back to a person to confirm the correct target before any retry.
- Step 3's command succeeds but the requested job name is not present in its output: do not substitute a similar name. Stop and hand back to a person to confirm the current correct job name.
- Step 4's command prints an error: capture the verbatim error text and report it. Do not retry the same command hoping for a different outcome, and do not attempt to fix the underlying cause yourself.

## Stop and hand back to a person if:

- The job name you were given does not appear, verbatim, in the canonical list in Preconditions, and cannot be resolved to one of those exact names.
- You cannot confirm, with certainty, that the command-line session you are about to use is connected to the production server rather than staging, local, or any other environment.
- `schedule:list` output does not contain the requested job name, even after checking for exact spelling.
- `schedule:test` prints any error output — the underlying job failure requires a person (developer) to diagnose; you must not attempt to resolve it by re-running the command, altering data, or restarting services on your own initiative.
- The requester has not given you, in this conversation, explicit authorization to run this specific job against the production system right now — do not run a job "to be helpful" without that explicit instruction, since even a nominally routine scheduled job executes real, production-affecting logic (for example, `backup:database`, `content:withdraw-news`, and `placement:sweep-expiry` all change or move real data).
