# Rotate Credentials

## Purpose

This document directs you, an AI agent, through rotating one stored credential (a password, access key, or token) used by the tourism portal — either because it may have been exposed, or as part of a routine rotation schedule. Follow the steps below in order. Do not skip the precondition checks.

## Preconditions

- You have been told exactly which credential to rotate, and its new value is available (freshly issued by the service that owns it — the database system, mail provider, storage provider, GitHub, etc.). If you do not have the new value, stop before step 1 and request it.
- You know, or can determine from the classification test in step 1, which of the three credential tiers applies.
- You will not print, log, commit, or otherwise expose the plaintext credential value in any output, commit message, issue, comment, or chat transcript at any point during this procedure — only into the one authorized secret-entry field or `.env` file named in the applicable step.

## Step 1 — Classify the credential

Precondition: You know the credential's name and its functional purpose (what consumes it).

Action: Classify it into exactly one of three tiers using this test:
- Tier 1 (repository build secret) — consumed only by the "build" job of the Release GitHub Actions workflow (e.g., container registry login credentials, the automation identity's own key). Stored under the repository's Settings → "Secrets and variables" → "Actions".
- Tier 2 (production environment secret) — consumed only by the "deploy" job, after a human reviewer's approval (e.g., production server access credentials, the deployment target address, the maintenance-mode bypass secret). Stored under the repository's Settings → "Environments" → "production".
- Tier 3 (application runtime credential) — consumed by the running application itself (e.g., database password, cache/session/queue connection, object storage keys, mail relay credentials, error-tracking key, map-tile provider key). Stored only in the production server's own `.env` file — never committed to the repository.

Expected outcome: Exactly one tier is selected, with a stated reason. Proceed to step 2 for Tier 1, step 3 for Tier 2, or step 4 for Tier 3.

## Step 2 — Update a Tier 1 (repository build) secret

Precondition: The credential was classified as Tier 1 in step 1, and you have administrator access to the repository's Settings on GitHub.

Action: Navigate to the repository → Settings → "Secrets and variables" → "Actions". Locate the secret by name. Open it, set its value to the new credential, and save.

Expected outcome: The secrets list shows this secret with an "Updated" timestamp equal to the current date/time. No other secret in the list changed.

Then skip to step 7.

## Step 3 — Update a Tier 2 (production environment) secret

Precondition: The credential was classified as Tier 2 in step 1, and you have administrator access to the repository's Settings on GitHub.

Action: Navigate to the repository → Settings → "Environments" → "production". Locate the secret by name under that environment's secrets. Open it, set its value to the new credential, and save.

Expected outcome: The environment's secrets list shows this secret with an "Updated" timestamp equal to the current date/time. No other secret in that environment changed.

Then skip to step 7.

## Step 4 — Update a Tier 3 (application `.env`) credential

Precondition: The credential was classified as Tier 3 in step 1, and you have access to edit the `.env` file on the production server (this file is never committed to the repository and exists only on that server).

Action: Open the production server's `.env` file. Locate the line for this credential's key and replace its value with the new credential. Save the file.

Expected outcome: Reading the file back shows the new value on that line, and no other line in the file changed.

## Step 5 — Recreate the affected container(s)

Precondition: Step 4 completed and the `.env` file on disk holds the new value.

Action: Run `docker compose up -d` for the affected service(s) only. Do not run a plain restart command — a restart reuses the already-running process, which keeps the old value cached in memory.

Expected outcome: The command output shows the affected service(s) as recreated (Docker Compose reports a fresh container for a service whose environment changed, not merely "unchanged"). `docker compose ps` shows the affected service(s) in a healthy/running state.

## Step 6 — Clear and rebuild the application configuration cache

Precondition: Step 5 completed and the affected container(s) are running.

Action: Inside the application container, run `php artisan config:clear`, then `php artisan config:cache`, in that order.

Expected outcome: Both commands exit with status 0 and print no error text. If either prints an error, do not proceed to step 7 — see the stop conditions below.

## Step 7 — Verify the new value is in effect

Precondition: Step 2 or step 3 completed (Tier 1/2 path), or steps 4–6 completed (Tier 3 path).

Action:
- Tier 1/2: re-read the secret's "Updated" timestamp captured in step 2/3; no further action needed.
- Tier 3: check the public site's built-in health-check address, or otherwise confirm the site returns a normal (non-error) response.

Expected outcome: For Tier 1/2, the timestamp check from step 2/3 already confirms success. For Tier 3, the site responds normally with no error page and no failed health check attributable to this credential.

## Completion

The procedure is complete when: the new value is saved in the single correct location for its tier (Tier 1 or Tier 2 GitHub secret, or the Tier 3 `.env` file); for Tier 3, the affected container(s) were fully recreated (not merely restarted) and the configuration cache was cleared and rebuilt; and step 7's verification confirms the new value is the one in effect. Report the tier, which secret/key name was rotated (never its value), and the verification result.

## Stop and hand back to a person if:

- You do not have the new credential value and cannot obtain it through the normal channel that issues it (database system, mail provider, storage provider, GitHub, etc.). Do not generate, guess, or invent a replacement value yourself.
- You cannot determine with confidence which of the three tiers applies (step 1) — do not guess; misclassification risks changing a secret in the wrong place, leaving two live copies with different values.
- You lack the access required for the identified tier (repository administrator access for Tier 1/2, or production server access for Tier 3) and cannot obtain it through a normal access-grant request.
- Step 6's configuration-cache commands fail with an error you cannot attribute to a specific, correctable typo in the `.env` line you just edited — i.e., the failure is not obviously self-inflicted and easily fixed.
- After completing the applicable steps and one careful, verified retry, step 7's verification still fails (the site errors, or the health check still fails). This is a production incident, not a retry loop: restore the previous known-good value (repeat steps 5–6 with it), then stop — do not keep attempting new values.
- Any action would require you to transmit, print, or persist the plaintext credential value outside the one authorized secret-entry field or `.env` file (for example, into a commit, an issue, a chat message, or a log). Refuse that specific action and continue only through the authorized channel.
- The credential being rotated is the automation identity's own key (Tier 1) and rotating it could revoke your own access mid-procedure. Confirm with a person before proceeding, since this can lock you out before you are able to verify success.
