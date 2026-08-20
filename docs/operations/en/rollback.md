# Rolling Back a Release

This procedure returns the live website to the version that was running immediately before the one currently live, when something has gone wrong after a new version was released. Use it if you, a colleague, or an automated monitor notices the site is broken, behaving incorrectly, or clearly worse than it was before the most recent update — and the problem is still there sometime later, meaning it was not caught and fixed automatically within the first minute or so after the update went live.

This document is for whoever is responsible for operating the site day to day, or anyone they have explicitly authorized to act on their behalf — including an AI agent operating under that same authorization.

## Before you start

Confirm all of the following:

- A new version of the site was released recently, and the problem you are seeing began after that release, not before it.
- The automatic safety check described below has already had its chance to fix this on its own and either did not fix it, or clearly did not run — for example, several minutes have passed since the release and the site is still misbehaving, and no "rolled back" entry has appeared on the project's "Releases" page for this incident.
- You know, or can find out from the "Releases" page, the exact version number of the last release that was working correctly — for example, "v1.4.0". You will need to type this in later.
- You (or someone you can ask directly) have an account on GitHub — the website where the project's code and its automated checks live — with permission to start this project's automated procedures. Without this, you cannot complete the steps below yourself.

## How automatic rollback works (you do not need to do this part)

Every time a new version is released, the system checks its own health within roughly a minute of going live. If that check fails, the system automatically puts the previous, known-good version back by itself — nobody needs to press a button. If this already happened, you will notice the site is back to normal, and a new entry will have appeared on the project's "Releases" page — a page listing every version ever put live, each with its own record of what happened — marked as a rollback. If that is what you are seeing, you are already done and do not need to follow the steps below.

The steps below are for the other situation: a problem noticed later, after that automatic check already passed, so the system has no reason to act on its own.

## Steps

1. **Find the version number to return to.** Open the project's "Releases" page on GitHub and look through the list of past versions for the most recent one that was known to be working correctly, before the current problem started. Write down its exact version number (for example, "v1.4.0").
   *Result:* You have one specific, exact version number written down.

2. **Open the Actions tab.** On the project's page on GitHub, click the tab labeled "Actions" near the top of the page.
   *Result:* A page opens showing a list of the project's automated procedures (technically called "workflows"), including one named "Release".

3. **Open the "Release" workflow.** Click "Release" in the list on the left.
   *Result:* The page changes to show the run history for this specific procedure, and a button labeled "Run workflow" appears.

4. **Start the rollback run.** Click "Run workflow". A small form appears asking for the version to act on — enter the exact version number you wrote down in step 1, then confirm to start the run.
   *Result:* A new run appears at the top of the run list, labeled with the version you entered, showing an in-progress status (a yellow or orange indicator). If another release or rollback happens to be running against the live site at that exact moment, yours simply waits its turn automatically and starts right after — this is normal and needs no action from you, since the system never runs two of these against the live site at once.

5. **Wait for the run to finish.** Watch the run's status on the Actions page. Do not start a second run while this one is in progress.
   *Result:* The status indicator changes to either a green checkmark (succeeded) or a red cross (failed). If you see red, go to "If a step does not work" below.

6. **Confirm the site is healthy again.** Once the run shows a green checkmark, open the live site and check that it behaves normally. Then check the project's "Releases" page again.
   *Result:* The site works normally, and a new entry has appeared on the "Releases" page recording this rollback — naming the version that is now live, who or what started it, the exact time, and that it succeeded.

## You are done when

The "Releases" page shows a new, successful entry for the rollback naming the version you returned to, and you have personally confirmed the live site is behaving normally again.

## If a step does not work

- **You are not sure which version was the last good one.** Look further down the "Releases" page for the release just before the one that introduced the problem. If you are still unsure, ask the developer who made the most recent release rather than guessing.
- **You do not see a "Run workflow" button, or you cannot open the "Actions" tab at all.** This almost always means your GitHub account does not have permission to run this procedure. Contact whoever administers the project's GitHub account, and ask them to either grant you access or run this procedure on your behalf, giving them the version number you identified in step 1.
- **The run finishes with a red cross (failed).** Open the run and look at which named step inside it failed, and read its message.
  - If the step that failed is one that inspects the project's own code (for example, one that runs its automated code checks) — this would be unusual for a rollback, since it is redeploying a version that already worked before. Contact the developer, quote the exact failing step's name and its message, and wait for their guidance rather than trying again yourself.
  - If the step that failed is a setup step (for example, one about fetching the code, setting up the software environment, or logging in to a registry) and its message mentions something like a network problem, a timeout, or a service that never became ready — this usually means a temporary hiccup rather than a real problem. Use GitHub's own "Re-run failed jobs" button once to try again. If it fails the same way a second time in a row, stop retrying and report it as a real outage instead.
- **The run finishes successfully (green checkmark), but the site still does not seem healthy.** This should not normally happen, because deploying the previous version goes through the exact same automatic health check as any other release. If it does happen anyway, do not start another rollback yourself — the system's own safety net is already handling one more automatic attempt, and if that also fails, it stops and holds the site in a maintenance state and notifies whoever is responsible. Wait for that notification, or contact the development team directly, instead of repeating this procedure yourself.
