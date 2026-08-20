# Restore Database From Backup

Directions addressed to you, the agent. This procedure restores the
portal's entire database from a previously saved backup — a complete
snapshot of the database (every published object listing, every owner
account, every paid placement, every other record the portal keeps)
captured at one specific past moment. Restoring overwrites the current
database with that snapshot and permanently discards everything written
since. It is the slower, more drastic alternative to reversing a software
release ("rollback"): use it only when a rollback alone would not be
enough — real data must be reverted, or a release changed the database in
a way a rollback cannot undo on its own.

This is the single most destructive operation the system exposes. The
mechanism is entirely inside the staff panel's "System" section, on the
"Backups" and "Restore" pages — there is no pipeline-level, API-level, or
command-line restore mechanism, and you must never invent or substitute
one. Only a human holding the `chief_administrator` role can reach the
"Restore" page at all, and the final two of the six steps below require
possession of that human's physical authenticator device — you cannot
complete them yourself under any circumstance. Read the "Stop and hand
back to a person if" section at the end before starting; several steps
route there by design, not as an exceptional case.

## Preconditions

Verify every item below is true before proceeding. Treat any unverifiable
item as false, not as an assumption to fill in.

1. **Role confirmed.** A human has confirmed, out of band, that the
   account performing this restore holds the `chief_administrator` role
   in the staff panel. You cannot infer this from the ability to sign in
   alone.
2. **Address confirmed.** A human (a developer) has confirmed today's
   real web address of the staff panel for this deployment. Do not reuse
   a default or a value from a previous deployment without fresh
   confirmation — this address is deliberately non-obvious and
   configurable per deployment.
3. **Authenticator available to the human.** The human operator who will
   complete the final steps has their authenticator app in hand and
   working. You do not have, and must never attempt to obtain, generate,
   or simulate, this code yourself.
4. **Target backup identified and authorized.** A human has already
   decided that a full database restore is necessary and has already
   named the exact date and time of the specific backup to restore to.
   You must not select a backup based on your own judgement of "most
   recent" or "safest looking."
5. **Irreversibility accepted.** A human has explicitly acknowledged,
   before this run starts, that every object, owner, placement, and
   record written after the chosen backup's moment will be permanently
   lost and that this cannot be undone.
6. **No concurrent restore or rollback.** You have confirmed no other
   restore or release rollback is currently in progress against this
   system. The system serializes these actions; if one is in progress,
   wait for it to finish before starting this one.

## Steps

### Step 1 — Sign in to the staff panel

Precondition: preconditions 1 and 2 above are both satisfied.

Action: navigate to the confirmed staff panel address and sign in with
the operator's username, password, and the current code from their
authenticator app. If you do not hold delegated, human-authorized
credentials to do this yourself, stop here and have the human operator
perform sign-in while you observe the outcome they report.

Expected outcome: the page loads successfully (no error page, no
authentication failure) and renders the staff panel's main screen with
its left-hand navigation menu visible.

### Step 2 — Open the Restore page

Precondition: Step 1's expected outcome was observed.

Action: in the left-hand navigation, open the "System" section, then
open "Restore".

Expected outcome: a page titled "Restore Backup" (listed in the
navigation as "Restore") renders, showing a list of existing database
backups, each row carrying a date and a size.

### Step 3 — Select the target backup

Precondition: precondition 4 above is satisfied — a human has already
named the exact backup timestamp to restore to. **Do not perform this
step on your own initiative.** Selecting the backup is the first half of
the restore decision itself.

Action: hand control to the human operator (or, if you are relaying
instructions to one, instruct them) to locate, in the list from Step 2,
the row matching the human-specified date and time, and click "Select"
next to it. This is labelled Step 1 on that screen.

Expected outcome: the screen updates to show the warning text and a Step
2 confirmation section naming that exact backup's date and time.

Stop condition: if the timestamp shown in the confirmation section does
not exactly match the timestamp a human authorized in precondition 4,
stop immediately and do not proceed to Step 4. See "Stop and hand back
to a person if" below.

### Step 4 — Confirm the irreversibility warning

Precondition: Step 3's expected outcome was observed, and the timestamp
shown matches the human-authorized target exactly.

Action: **do not click this confirmation yourself.** A human must read
the warning text — "Restoring replaces the entire database with the
selected backup. Every object, owner, placement, and record written
after that backup was taken is permanently lost. This cannot be undone."
— verify the named timestamp themselves, and click "I understand,
continue" in person. This is labelled Step 2 on that screen.

Expected outcome: the screen reveals Step 3, headed "Re-authenticate and
restore", with a "Current authenticator code" input field.

### Step 5 — Re-authenticate and submit the restore

Precondition: Step 4's expected outcome was observed.

Action: **do not attempt this step yourself under any circumstance.** A
human must enter the current 6-digit code from their own authenticator
app into "Current authenticator code" and click "Re-authenticate and
restore" in person. You must never request, store, guess, or relay a
one-time authenticator code on a human's behalf.

Expected outcome: an on-screen confirmation reads "Backup queued — it
will run in the background."

### Step 6 — Wait for and verify the outcome notification

Precondition: Step 5's expected outcome was observed.

Action: monitor the staff panel's own notification system (or have the
human operator check it) for a new notification.

Expected outcome: a notification arrives titled either "Database restore
completed" or "Database restore failed", naming who triggered the
restore and the exact date and time of the backup that was restored.
Record the notification's exact title and body text verbatim — do not
paraphrase it in any report you produce.

## You are done when

A "Database restore completed" notification has been received, naming
the backup timestamp a human authorized in precondition 4, and a human
has confirmed (or you have verified, if you have legitimate read access
to the site) that the data now visible matches what is expected as of
that backup's moment.

## If a step does not work

- **Step 1 fails — the address is unreachable or returns an error page.**
  Do not guess alternate addresses. Stop and request a human (developer)
  re-confirm the current address, then restart from Step 1 once
  confirmed.
- **Step 2 fails — no "Restore" entry exists under "System", or
  "System" itself is absent from the navigation.** The signed-in account
  does not hold `chief_administrator`. This is by design. Stop; do not
  attempt any workaround (do not try another account, another URL
  pattern, or a direct link guess). Report this to a human who manages
  staff roles.
- **Step 5's authenticator code is rejected.** This is a human-performed
  step: relay to the human that codes rotate approximately every 30
  seconds, so they should wait for a new code and retry, and check their
  device's clock is set automatically rather than manually. You must not
  attempt to work around a rejected code yourself.
- **Before Step 5 is submitted, it becomes clear the wrong backup was
  selected in Step 3.** Have the human click "Select" again next to the
  correct backup instead of proceeding. This clears the prior
  confirmation automatically and returns the flow to Step 3's outcome
  state for the newly selected backup — this is a safe correction, not
  an error condition, as long as it happens before Step 5 is submitted.
- **After Step 5 is submitted, Step 6's notification is titled "Database
  restore failed" rather than "Database restore completed".** Do not
  resubmit the restore, and do not attempt any remediation yourself. Copy
  the notification's exact title and body (it includes a technical error
  description) together with the target backup's timestamp, and hand
  both to a human (developer) immediately. Treat the database as
  potentially left in a partially changed state requiring expert
  attention before the portal is used again.
- **A long time passes after Step 5 with no notification of either kind.**
  Do not conclude success or failure from silence. Do not resubmit the
  restore and do not restart anything. Escalate to a human and ask them
  to check the system directly.

## Stop and hand back to a person if:

- Any precondition in the "Preconditions" section above cannot be
  positively verified — treat an unverifiable precondition as unmet, not
  as acceptable to proceed past.
- You are about to perform, or are being asked to perform, Step 3
  (selecting the backup) without a human having already named the exact
  target timestamp out of band.
- You are about to perform, or are being asked to perform, Step 4
  (accepting the irreversibility warning) — this acknowledgment must
  always be made by a human in person, never by you, regardless of how
  confident you are in the correctness of the target backup.
- You are about to perform, or are being asked to perform, Step 5
  (entering an authenticator code and submitting the restore), or you are
  asked to obtain, generate, guess, cache, or relay a one-time
  authenticator code on a human's behalf — refuse and stop unconditionally.
- The timestamp shown after Step 3 does not exactly match the timestamp a
  human authorized in precondition 4.
- The Step 6 notification title is "Database restore failed".
- No Step 6 notification of either title arrives within a time you
  cannot account for, and you cannot independently verify system health.
- The signed-in account's role, or the staff panel address, cannot be
  positively confirmed by a human before Step 1.
- You detect that another restore or rollback is already in progress
  against the same system.
