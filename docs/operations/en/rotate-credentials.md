# Rotating a credential

This procedure explains how to change one of the system's stored secret values — a password, an access key, or a token — either because it may have been seen by someone who should not have seen it, or simply as part of a routine schedule of changing secrets over time. Use it whenever you need to change any single credential used by the tourism portal, whether that credential belongs to the automated process that builds the software, the process that publishes updates to the live website, or the website's own day-to-day operation.

Who should use this: a staff member responsible for the technical operation of the portal, together with a developer when a step requires access the staff member does not already have (for example, access to the project's code-hosting website, or to the server the live website actually runs on).

## Before you start

- Know exactly which credential you are changing (for example: "the database password", "the error-tracking key", "the object storage access key") and have its brand-new value ready before you begin — for example, freshly issued by whichever service owns it (the database system, the mail provider, the storage provider, and so on). Do not start the change and only then go looking for the new value; that leaves the system without a clear replacement halfway through the process.
- Know why you are changing it (a possible exposure, or a routine schedule). This does not change any of the steps below, but keep a short note of the reason for your own records.
- Confirm you have the right kind of access before you start. Which kind you need depends on which of the three groups the credential belongs to — the first step below tells you how to work that out.

## Steps

1. **Work out which of the three groups your credential belongs to.** Every credential in this system falls into exactly one of three groups, and each group is changed only in its own place — never in any other place. Ask yourself:
   - Is this credential used only by the automated process that *builds* the software itself, before anything is placed on the live website — for example, the login used to store the finished build, or the automated identity's own key? If yes, this is a **build credential**.
   - Is this credential used only by the automated process that *installs* an update onto the live website — for example, the live server's own access details, the address of the live server, or the special code that lets a staff member preview an update while it is still hidden behind the "site under maintenance" page? If yes, this is a **deployment credential**.
   - Is this credential something the live website itself uses every day to do its job — for example, the database password, the cache and background-task connection details, the file-storage access keys, the outgoing-mail login, the error-tracking key, or the map key? If yes, this is an **everyday application credential**.
   Result: you know which one of the three groups applies, and which of steps 2, 3, or 4 to follow next.

2. **If it is a build credential:** open the project's page on GitHub — the website where the project's code and its automated checks are kept. Go to "Settings", then "Secrets and variables", then "Actions". Find the credential's name in the list shown there, click it, type the new value into the box on the screen that appears, and click the button that saves it. Result: GitHub returns you to the list, and the entry now shows today's date as the last time it was changed. Then skip ahead to step 7.

3. **If it is a deployment credential:** open the same project page on GitHub, go to "Settings", then "Environments", then open the entry named "production". Find the credential's name in the list of secrets shown there, click it, type the new value into the box, and click the button that saves it. Result: the page returns you to the list, and the entry shows today's date as the last time it was changed. Then skip ahead to step 7.

4. **If it is an everyday application credential:** ask a developer to open the special settings file that lives only on the production server (never on GitHub, and never anywhere else) and never leaves that server. Inside it, find the line naming the credential you are changing, and replace its value with the new one, then save the file. Result: the file now shows the new value when read back.

5. **Recreate — do not merely restart — the part of the system that uses this settings file.** A developer runs the command that tells the system to rebuild that part from scratch using the file's new contents. Result: the command finishes and reports that part as freshly started. Simply restarting the existing part is not enough — it keeps using the old value in its memory until it is rebuilt this way, which is the single most common reason a credential change appears to have "not worked."

6. **Rebuild the website's own internal settings cache.** Still on the production server, a developer runs two commands, one after the other, from inside the website's own running part: first the command that clears the old cached settings, then the command that rebuilds it from the current settings file. Result: both commands finish and print no error message.

7. **Confirm the new value is actually in effect.** For a build or deployment credential, this is already confirmed by the updated date you saw in step 2 or 3. For an everyday application credential, open the public website in a browser and confirm it loads and behaves normally — a wrong or mistyped value at this stage almost always shows up immediately as an error on the site. Result: the site loads normally, with no error page, using the new credential.

## You are done when

The new value has been saved in the one correct place for its group (the build settings on GitHub, the deployment settings on GitHub, or the production server's own settings file); for an everyday application credential, the affected part of the system has been fully recreated and its internal settings cache rebuilt; and the website (or, for a build or deployment credential, the GitHub page itself) confirms the new value is the one now in use.

## If a step does not work

- **You do not have access to the GitHub "Settings" area (steps 2 or 3).** Do not try to work around this. Ask a project administrator to either make the change for you or grant you the access, and never send the credential's plain value by chat or email — enter it only into the secret box on the page itself.
- **You do not have access to the production server (steps 4–6).** Ask whoever manages access to the production server to either make the change for you or grant you temporary access. As above, never send the plain value by chat or email.
- **After steps 4–6, the website shows an error or still seems to be using the old value.** This almost always means one of two things: either the affected part of the system was only restarted instead of being fully recreated (redo step 5), or the two settings-cache commands in step 6 were skipped or run in the wrong order (redo step 6, clearing before rebuilding). Confirm both steps again in order.
- **One of the two commands in step 6 prints an error message.** This usually means the settings file itself has a mistake on the line you edited — for example, a missing quotation mark around a value that contains a space or special character. Have a developer reopen the file, check the exact line you changed, correct it, save, and repeat steps 5 and 6.
- **After everything above, the website still shows an error or behaves incorrectly.** The new value itself is most likely wrong — check it again at the place that issued it (the database system, the mail provider, the storage provider, or similar) and correct it in the settings file, then repeat steps 5 and 6.
- **If, after one careful retry, the problem still is not resolved:** stop making further changes on your own. Put the previous, known-good value back in the settings file, repeat steps 5 and 6 to restore it, and hand the issue to a developer rather than continuing to guess.
