# Reading a Failed Pipeline Run

This guide is for anyone who has been told that a release "failed" or who sees a red warning mark next to a recent release attempt on GitHub — the website where the project's code and its automated checks live — and needs to work out what to do next. It walks you through telling apart two very different situations: (A) the change that was being released genuinely has a problem in it, or (B) nothing is wrong with the change itself and one of the automated helper machines that runs the checks simply had a temporary hiccup. The two situations need completely different responses, and guessing wrong wastes everyone's time, so this guide shows you exactly what to look at to tell them apart.

This guide only describes how to *read and understand* a failure — it does not walk you through fixing code, approving a release, or restoring anything. If it points you toward the developer, that is the correct outcome, not an unfinished step.

## Before you start

- You have a GitHub account with permission to open this project's page and its "Actions" tab (the page that lists every automated run of the project's checks). If you are not sure you have this, ask a developer to confirm or to grant it.
- You know which run you are looking at — usually because someone told you a release failed, or because you can see a red warning mark next to a recent attempt yourself.
- You have a way to reach the developer responsible for the project (chat, email, or phone) — this guide will sometimes ask you to send them a short message.

## Steps

1. **Open the failed run.**
   Action: Sign in to GitHub and open the project's repository page. Near the top of the page, click the tab labeled "Actions". This tab lists every automated run of the project's checks, each with a status mark next to it. Find the run you are interested in — it is marked with a red circle containing a white cross (X) — and click its title to open it.
   Observable result: A page opens titled "Release" (the name of this automated pipeline), showing a list of named steps arranged from top to bottom, in the order they ran. Each step shows either a green checkmark (it succeeded) or a red cross (it failed).

2. **Find the first failed step.**
   Action: Starting from the top of the list, scroll down until you reach the first step that shows a red cross.
   Observable result: You have identified exactly one step — the first one to fail. Any steps listed below it either did not run or were skipped, because the pipeline stops once a step fails; they tell you nothing useful about the cause.

3. **Read that step's name and decide what kind of step it is.**
   Action: Read the exact text written next to the red cross.
   Observable result: You can place the step into one of two groups.
   - Group A — it genuinely inspects the project's own work. Its name refers to an actual check of the project's content, for example "Run the PHP quality gate" or "Scan for destructive migrations".
   - Group B — it only prepares the working environment, before the project's own work was ever really touched, for example "Checkout code", "Setup PHP", "Setup pnpm", "Set up Docker Buildx", or "Log in to the container registry".

4. **Open the failed step and read its output.**
   Action: Click on the failed step's name to expand it. A block of text appears — this is the step's "output", a detailed log of everything it did — usually ending in red-highlighted text describing the failure.
   Observable result: You can read the last few lines of that red text, which describe what actually went wrong.

5. **Match what you read to one of the two cases.**
   Action: Compare the output against these two patterns.
   - Case A ("the change itself is bad"): the output names a specific file, test, or rule that failed — for example, it says a particular test did not pass, a particular coding-style rule was broken, or a database change was flagged as unsafe.
   - Case B ("the runner is broken"): the output mentions something outside the project's own content — words like a network timeout, "connection refused", a tool or package that failed to download, or a supporting service (such as a database) that never became ready.
   An extra clue for Case B: if you happen to know the exact same version of the project passed a run very recently, and nothing in the project has changed since, that also points to Case B.
   Observable result: You can state, in one sentence, whether this looks like Case A or Case B.

6. **Act according to the case.**
   Action:
   - If Case A: send the developer responsible for the change a short message quoting the exact name of the failed step and the relevant lines of its output. Wait for them to publish a corrected version before the release is tried again. Do not click anything else on this run.
   - If Case B: click the button labeled "Re-run failed jobs" on the run's page. This tells GitHub to try the same failed steps again without changing anything else.
   Observable result:
   - For Case A: your message is sent, and there is nothing further to do on this run — you are waiting on the developer.
   - For Case B: the page shows the steps running again, ending either in all-green checkmarks (it passed this time) or a new red cross.

7. **If you re-ran the pipeline, check whether it passed.**
   Action: Wait for the re-run to finish, then look at whether every step now shows a green checkmark.
   Observable result:
   - If everything is now green, the hiccup is resolved and the release can proceed on its own; there is nothing further for you to do.
   - If the same step fails again with a similar message, do not click "Re-run failed jobs" a second time. Instead, treat it as Case A: send the developer a message quoting the failed step's name and output, and mention that "Re-run failed jobs" was already tried once and failed the same way — this makes a real outage more likely than a passing hiccup.

## You are done when

You have correctly identified whether the failure was Case A or Case B, and you have taken the matching action — either you have sent the developer the failing step's name and output and are waiting for a corrected version, or you re-ran the pipeline once and every step now shows a green checkmark.

## If a step does not work

- **You cannot find any red cross anywhere in the list.** You may be looking at a run that actually succeeded, or at an older run. Go back to the "Actions" tab, check the date and time and the status mark at the top of the run's summary, and open the run that actually matches the failure you were told about.
- **You cannot tell from the step's name alone whether it belongs to Group A or Group B.** Open its output (step 4) — the content almost always makes it clear. If it still does not, treat it as Case A and hand it to a developer; never guess, and never simply keep clicking "Re-run failed jobs" hoping the problem goes away on its own.
- **The "Re-run failed jobs" button is missing or greyed out.** You most likely do not have permission to use it. Ask a developer either to click it for you or to grant you access to the repository's "Actions" tab.
- **The re-run fails in the same way a second time in a row.** Stop. Do not click "Re-run failed jobs" a third time. Report it to the developer as a likely real outage, quoting the output from both attempts.
- **You do not have access to open the "Actions" tab at all.** Ask a developer for access, or ask them to open the run themselves and read you the failed step's name and its output over chat or phone, so that you can still make the Case A / Case B decision described in this guide together with them.
