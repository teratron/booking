# Running a Scheduled Job Immediately

This guide explains how to make one of the system's automatic background tasks — called a **scheduled job** — run right now, instead of waiting for it to run on its normal timer. The portal runs a fixed set of these small housekeeping tasks in the background at set times: for example, taking a backup copy of the database, or retrying a message to a customer that failed to send the first time. Sometimes you need one of them to run immediately rather than wait — for example, to force a fresh backup right before a risky change, or to make a stuck notification retry sending without waiting for its normal turn. Use this guide whenever you need that.

This guide is for anyone responsible for day-to-day operation of the portal who has been given access to the production server's command line — a plain text window where instructions are typed directly to the computer running the live website, instead of clicking buttons on a screen. If you don't have that access, ask a developer to arrange it, or to run the command for you.

## Before you start

- You know exactly which job you need to run right now, and you can find its exact name — spelled exactly, including the colon in the middle — in the list below. The full list of job names currently in the system is: `availability:sweep-stale`, `journal:archive`, `placement:sweep-expiry`, `analytics:rollup`, `analytics:compact`, `objects:sweep-staleness`, `availability:sweep-confirmation`, `notifications:retry-dispatch`, `content:archive-promotions`, `content:withdraw-news`, `seo:generate-sitemaps`, `backup:database`, `backup:media`, `backup:cleanup`, `backup:monitor`, `horizon:snapshot`.
- You have access to a command line connected to the production server — the real computer, run by the hosting provider, that serves the live website to visitors. This is normally arranged in advance by a developer or system administrator.
- If the job you need is specifically `backup:database` (an on-demand database backup), there is a simpler alternative to everything below: sign in to the staff panel (ask a developer for its exact web address, since it is deliberately not a standard or guessable one and can differ between installations), open the "Backups" page under the "System" section, and click the button labelled "Run backup now". That one button does the same thing as this guide, in a single click, for that one job only. The steps below use the command line instead, and work for every job in the system, including `backup:database` — use them if you need to run any job other than a backup, or if you simply prefer one consistent method.

## Steps

1. **Action:** Decide which job you need to run right now, and copy or write down its exact name from the list above.
   **Result:** You have one exact job name, spelled precisely as it appears in the list (for example, `notifications:retry-dispatch`), with no extra spaces or changed letters.

2. **Action:** Open a command line (terminal) window connected to the production server.
   **Result:** You see a command prompt — a line waiting for you to type — that belongs to the production server, not to your own computer.

3. **Action:** Type the following command exactly as written and press Enter: `docker compose exec app php artisan schedule:list`
   **Result:** The command prints a list of every scheduled job currently configured on this server. Find the job name you chose in Step 1 somewhere in that list, spelled exactly the same way.

4. **Action:** Type the following command, replacing `THE-JOB-NAME` with the exact name you confirmed in Step 3, and press Enter: `docker compose exec app php artisan schedule:test --name=THE-JOB-NAME`
   **Result:** The command finishes and returns you to the command prompt. No error message is printed — the job has now run immediately, instead of waiting for its normal schedule.

## You are done when

The command from Step 4 has finished and returned you to the command prompt without printing an error message. The job you chose has now run.

## If a step does not work

- **You cannot open a command line connected to the production server, or you are asked for a login you don't have (Step 2).** Do not try to guess credentials or find another way in. Ask a developer or system administrator to grant you access, or to run the command on your behalf.
- **The command `docker compose exec app php artisan schedule:list` fails to run at all, or the window reports something like "command not found" (Step 3).** This usually means the command line you opened is not actually connected to the correct production server. Confirm with a developer that you are on the right machine before trying again.
- **Your chosen job's name does not appear anywhere in the list printed by `schedule:list` (Step 3).** Double-check the spelling against the list in "Before you start", including the colon. If it still isn't there, the list of available jobs may have changed since this guide was written — ask a developer to confirm the current, correct name before continuing.
- **The `schedule:test` command prints an error message instead of finishing quietly (Step 4).** The message itself names what went wrong. This means the job itself hit a problem — not that you typed the command incorrectly. Copy the exact error message and pass it to a developer; do not try to run the command again repeatedly hoping it changes.
