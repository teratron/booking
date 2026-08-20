# Restoring the Database From a Backup

This procedure walks a Chief Administrator through restoring the portal's
entire database from a previously saved backup copy. A backup is a
complete, saved snapshot of the database — the single central store that
holds every published object listing, every owner account, every paid
placement, and every other record the portal keeps — taken at one specific
moment in the past. Restoring puts the whole database back exactly as it
was at that moment, discarding everything written to it since.

This is a slower, far more drastic step than simply putting an earlier
version of the website's own software back in place (a "rollback"). Use
this procedure only when a rollback on its own would not be enough — for
example, because real data needs to be reverted, or because a recent
update changed the database in a way that putting the old software back
cannot undo by itself.

Only a person holding the "Chief Administrator" role can even reach the
screen this procedure uses, and that restriction is deliberate: restoring
the database is the single most destructive action available anywhere in
this system, because it permanently erases every change made after the
moment the chosen backup was taken.

## Before you start

- You hold the "Chief Administrator" role in the staff panel — the
  private administrative website used by the portal's own staff, separate
  from the public site visitors see. This particular screen exists only
  for that one role; nobody else can open it, on purpose.
- You know today's real web address for the staff panel. Ask a developer
  to confirm it for you before you begin. The address is deliberately not
  the commonly guessed one and can differ between deployments, so do not
  assume it.
- You have your authenticator app in hand and working. This is the app on
  your phone that generates the changing 6-digit code you already use
  every time you sign in to the staff panel; this procedure asks you to
  enter a fresh code from it a second time, separately from signing in.
- You and your team have already agreed that restoring the whole database
  is genuinely necessary, and you already know the exact date and time of
  the specific backup you need to restore to. Make this decision before
  you open the screen, not while looking at the list of backups.
- You accept, before you begin, that this action cannot be undone: every
  object, owner, placement, and record written to the portal after the
  chosen backup's moment will be permanently and irretrievably lost.
- Nobody else is currently restoring a backup or reversing a software
  release. The system only ever carries out one such action at a time; if
  you know one is already in progress, wait for it to finish before
  starting this one.

## Steps

1. Open a web browser and go to the real staff panel address you
   confirmed above. Sign in the normal way, with your username, your
   password, and the current code from your authenticator app.
   **Result:** the staff panel opens and you see its main screen with a
   menu running down the left-hand side.

2. In that left-hand menu, find the "System" section and click it, then
   click "Restore".
   **Result:** a page titled "Restore Backup" opens (it is listed in the
   menu simply as "Restore"), showing a list of existing database
   backups, each with its date and its size.

3. In that list, find the backup matching the exact date and time you
   and your team agreed on beforehand, and click the "Select" button
   next to it. This is Step 1 shown on that screen.
   **Result:** the screen updates to show a warning message and a Step 2
   confirmation section naming that backup's exact date and time.

4. Read the warning carefully. It reads exactly: "Restoring replaces the
   entire database with the selected backup. Every object, owner,
   placement, and record written after that backup was taken is
   permanently lost. This cannot be undone." Check that the date and
   time named directly beneath it match the backup you intend, then
   click the button labelled "I understand, continue". This is Step 2 on
   that screen.
   **Result:** the screen reveals Step 3, headed "Re-authenticate and
   restore", with a field asking for your "Current authenticator code".

5. Open your authenticator app, read the current 6-digit code it is
   showing for your staff panel account, type it into the "Current
   authenticator code" field, then click the "Re-authenticate and
   restore" button.
   **Result:** a confirmation message appears on screen reading "Backup
   queued — it will run in the background."

6. You do not need to keep the browser open. Watch instead for a
   notification inside the staff panel — its own built-in notification
   bell or notifications list.
   **Result:** you receive a notification titled either "Database
   restore completed" or "Database restore failed", naming who triggered
   the restore and the exact date and time of the backup that was
   restored.

## You are done when

You have received the "Database restore completed" notification, naming
the backup you selected, and you (or your team) have briefly checked the
site to confirm the data now visible matches what you expect from that
backup's moment in time.

## If a step does not work

- **You cannot reach the staff panel address at all, or it shows an
  error page.** The address may be wrong or may have changed. Stop and
  ask a developer to confirm the current, correct address before trying
  again — do not guess at variations of it yourself.
- **You are signed in, but you cannot find a "Restore" entry under
  "System", or the "System" section itself is missing from your menu.**
  Your account does not hold the "Chief Administrator" role. This is by
  design — restoring the database is intentionally limited to that one
  role. Contact whoever manages staff accounts and roles at your
  organization; do not attempt to work around this.
- **Your authenticator app's code is rejected**, either at sign-in or at
  the "Re-authenticate and restore" step. These codes change
  automatically about every 30 seconds. Wait for the app to show a
  brand-new code, then try again. If it keeps failing, check that your
  phone's clock is set correctly (automatic time, not manually
  adjusted), since the code depends on it.
- **You realize, after clicking "Select", that you picked the wrong
  backup, and you have not yet clicked "Re-authenticate and restore".**
  Simply click "Select" next to the correct backup instead. This
  automatically clears your earlier confirmation and takes you back to
  Step 2 for the newly chosen backup, so nothing is lost by changing
  your mind at this point.
- **After you submit Step 3, the notification you eventually receive is
  titled "Database restore failed" rather than "Database restore
  completed".** Do not try the restore again on your own. The
  notification's own text includes a technical description of what went
  wrong — copy it exactly, together with the date and time of the backup
  you had selected, and hand both to a developer immediately. The
  database may currently be in a partially changed state that needs
  expert attention before anyone uses the portal again.
- **A long time passes — well beyond what you would expect for a
  database of the portal's size — and no notification of either kind
  arrives.** Do not assume the restore succeeded, and do not assume it
  failed. Contact a developer and ask them to check the system directly,
  rather than guessing, submitting the restore a second time, or
  restarting anything yourself.
