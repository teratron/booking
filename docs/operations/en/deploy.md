# Deploying a New Release to Production

This guide explains how to ship a new, finished, already-approved batch of
changes to the live tourism portal website ("production" — the real website
that visitors and object owners actually use, as opposed to a test copy).
Follow it every time a release needs to go out — including the very first
time the website is ever put onto its production server, as long as a
developer has already set up that server and its access credentials once
beforehand (that one-time setup is a separate job for a developer and is not
covered here; once it is done, every release after it, including the first
one, follows exactly the steps below). Use this guide yourself if you are
the person responsible for releases, or hand it to an AI assistant you are
directing to carry one out on your behalf.

## Before you start

- A developer has already set up the production server and every set of
  access credentials it needs, at least once. If you are not sure this has
  happened yet (for example, this would be the very first release ever),
  stop and confirm with a developer before continuing.
- You (or whoever is doing this with you) can sign in to GitHub — the
  website where this project's code and its automated checks live — and can
  reach this project's page there.
- You know who the designated reviewer is: the one person who must click a
  button on GitHub to approve every release before it reaches production. If
  that person is you, you know it; if it is someone else, you know how to
  reach them.
- You know the real web address of the live site, so you can visit it at
  the end. If you are not sure, confirm it with a developer rather than
  guessing.
- If this release includes a database change that cannot be safely undone
  just by putting the previous version back, a human being (never an
  automated assistant) has already decided this and has the exact wording
  ready to explain why. Writing that declaration is always a person's
  decision — never something an automated assistant does on its own,
  because the same person or system should never both request a risky
  change and be the one who signs off on it.

## Steps

### 1. Decide the next version number, and whether this release must be declared irreversible

At the root of the project's code sits a file named `CHANGELOG.md`. Near the
top of it is a section titled "Unreleased" — a running list of everything
that has been finished since the last release but has not shipped yet. Open
it and read that list.

The project's version numbers have three parts separated by dots, for
example `1.4.0` — read as "MAJOR.MINOR.PATCH". Looking at the "Unreleased"
list, increase the first number if something in it would break how the site
already works for existing owners or visitors; increase the second number
if it only adds new things without breaking anything; increase the third
number if it is only small fixes. The tag you create in the next step will
read `v` followed by this number, for example `v1.4.0`.

While you are reading the list, also decide whether anything in it changes
the database in a way that cannot be safely undone by simply putting the
previous version of the site back (for example, permanently deleting a
column of stored information rather than just adding one). If so, this
release must be explicitly marked as irreversible in the next step, with a
plain-language reason — and that reason must be written by a human, never
by an automated assistant.

**Observable result:** you can point to the exact list of changes under
"Unreleased" and state the next version number out loud, plus a clear yes
or no on whether this release needs an irreversibility declaration.

### 2. Create and push the release tag

A "tag" is a label attached to one exact, specific point in the project's
history, marking it as the version to release. There are two ways to create
and push one — either is fine for a routine release with nothing
irreversible in it:

- **On GitHub itself:** open the project's "Releases" page and use the
  button labelled "Draft a new release". Type the new version (for example
  `v1.4.0`) as the tag, make sure the target is the `master` branch (the
  single line of code that production is always built from), and publish
  it.
- **Using git commands** (typically done by a developer, or by an
  automation agent acting on your behalf, on a computer that already has a
  working copy of the project's code): create the tag and push it to
  GitHub.

If this release **does** need to be marked irreversible, use the git-command
method and attach the human-written reason directly to the tag as its own
message, as a line reading exactly `Irreversible:` followed by the reason —
not the separate notes box on the GitHub release page, which is a different
piece of text that the safety check in the next step does not read. Confirm
with a developer if you are not sure the wording landed in the right place.

Either way, make sure the tag is created from the current, up-to-date
`master` branch — not from an older commit or an unmerged piece of work.

If another release is already running when you push your tag, yours does
not start immediately — it waits its turn and starts automatically once the
one ahead of it finishes. This is expected behaviour, not a problem.

**Observable result:** the new tag (for example `v1.4.0`) appears on the
project's "Releases" or "Tags" page on GitHub, and — if this release is
irreversible — its message contains the "Irreversible:" line a human wrote.

### 3. Watch the migration safety check run

Pushing the tag automatically starts an automated sequence of checks and
actions called the "Release" pipeline. On GitHub, open the "Actions" tab and
find the run named "Release" matching your tag at the top of the list. Its
first step is named "scan-migrations" — it automatically inspects any
database changes in this release and refuses to continue if it finds one
that cannot be safely undone and was not declared irreversible in step 2.

**Observable result:** "scan-migrations" finishes with a green checkmark. If
this release was correctly declared irreversible in step 2, it passes for
that reason; otherwise it passes because nothing irreversible was found.

### 4. Watch the build step run

Next, a step named "build" packages the new version of the site into a
ready-to-run unit called a container image, and records its own unique
fingerprint (a "digest") so every later step can be certain it is handling
the exact same thing.

**Observable result:** "build" finishes with a green checkmark.

### 5. Wait for the reviewer's approval

Before anything touches the live site, the pipeline pauses and waits for
the designated reviewer (see "Before you start") to approve it. On the run's
page, the "deploy" step shows as waiting, with a button the reviewer uses
to approve or reject it. This approval must always come from a person — an
automated assistant must never click it on its own, even if it technically
could.

**Observable result:** the reviewer approves, and the "deploy" step changes
from waiting to running.

### 6. Watch the deploy step run through the brief maintenance window

Once approved, the "deploy" step updates the live server, in this order:
first it switches the site into a short maintenance mode; while it is in
that mode, whoever is running the release can preview the update using a
special link before anyone else sees it, while ordinary visitors keep
seeing a temporary maintenance notice instead of the normal site; any
necessary database changes are applied; every internal part of the
application restarts, in a safe order (the internal application components
first, and the part that visitors' browsers connect to directly last);
internal caches are refreshed; and finally the maintenance notice is
switched off and the site returns to normal for everyone. This whole window
is brief.

**Observable result:** the "deploy" step finishes with a green checkmark,
and the maintenance notice a visitor would have briefly seen is gone.

### 7. Confirm the automatic health check passes

A step named "verify" automatically checks that the freshly updated site is
working correctly, on its own, without anyone needing to visit it by hand.

**Observable result:** "verify" finishes with a green checkmark.

### 8. Confirm the official release record is created

A final step named "record" creates an official entry on the project's
"Releases" page either way — whether the release succeeded or had to be
undone — naming exactly what was deployed, its unique fingerprint, who or
what triggered it, the exact time, and the outcome.

**Observable result:** a new entry for your version appears on the
"Releases" page, showing a successful outcome.

### 9. Visit the live site yourself

Open the real web address of the live site in a browser and look at it
directly, the way a visitor would.

**Observable result:** the site loads normally, showing current content —
not a maintenance notice — confirming the release is genuinely live.

## You are done when

The "Releases" page on GitHub shows a new entry for your version marked as
successful, and the live site loads normally, showing today's content, when
you visit it directly yourself.

## If a step does not work

When any automated step shows red (failed) on GitHub, there are two very
different reasons, and telling them apart matters before you do anything
else:

- **The change itself is the problem.** A step that actually inspects the
  project's own work fails — for example "scan-migrations" or a step
  described as a quality check — and its own output names a specific file,
  rule, or check that failed. In this case, tell a developer, quoting the
  exact step name and its output, and wait for a corrected version before
  trying again. Do not attempt to fix this yourself.
- **The setup around the change is the problem.** A step that is only
  preparing the environment fails — for example a step about checking out
  the code, setting up a tool, or logging in to a registry — and its output
  mentions something like a network timeout or a connection failure. In
  this case, simply use GitHub's own "Re-run failed jobs" button; this kind
  of hiccup is usually temporary. If the exact same run fails the exact
  same way a second time in a row, stop retrying and report it as a real
  outage to a developer instead.

With that in mind:

- **Step 1 is unclear** (the "Unreleased" list is empty, confusing, or you
  cannot tell whether something is irreversible): stop and ask a developer
  before choosing a version number or pushing a tag.
- **Step 2 fails** (you do not have permission to push a tag, or you are
  not sure the irreversibility wording landed correctly): ask a developer,
  or the automation agent acting for you, to create and push the tag
  instead, and confirm the wording with them first if this release is
  irreversible.
- **Step 3 ("scan-migrations") fails:** use the red/failed distinction
  above. Most often this means a database change was found that was not
  declared irreversible — tell a developer, and either the change is
  reworked to be safely reversible, or a human writes the "Irreversible:"
  declaration on a new tag. Never try to work around this check yourself.
- **Step 4 ("build") fails:** use the red/failed distinction above. One
  specific message worth recognising: if it says the tag could not be
  found on the `master` branch, the tag was created from the wrong point in
  the project's history — do not edit or delete the tag; ask a developer to
  create a fresh tag from the current `master` branch instead.
- **Step 5 (nobody approves):** contact the designated reviewer directly.
  Nothing proceeds until a person approves it — do not look for a way
  around this, and never let an automated assistant approve it on your
  behalf.
- **Step 6 ("deploy") fails partway through the maintenance window:** use
  the red/failed distinction above for the underlying cause. Do not attempt
  to fix the live server by hand yourself in the meantime — that turns one
  incident into two.
- **Step 7 ("verify") finds the site unhealthy:** a step named "rollback"
  then runs automatically, putting the previous, known-good version back
  and checking health once more — nobody needs to do anything for this
  first attempt. If that second check also passes, the site is healthy
  again but your new release did not go live; treat it the same as a
  failed step 3 or 4 above and involve a developer before trying again. If
  that second check also fails, the pipeline stops on its own and
  deliberately leaves the site in its maintenance page rather than
  guessing further, and notifies whoever is responsible. At that point,
  stop entirely — do not push another tag or try anything else — and wait
  for a person to take over.
- **Step 8 (no entry appears, or it shows a failed outcome):** do not
  assume the release succeeded just because earlier steps looked fine;
  treat a missing or failed entry as a real problem and tell a developer.
- **Step 9 (the live site does not look right despite everything above
  succeeding):** contact a developer immediately rather than trying to fix
  it yourself — it may simply be your browser showing an old, cached copy
  of the page, or it may be a genuine problem worth investigating properly.
