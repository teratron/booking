#!/bin/sh
set -eu

# Writes the release record — created at the production-line transition, not
# only after a successful deploy, so a failed deploy is recorded too. No
# second change log: the body is assembled from the CHANGELOG.md section
# this project already maintains in Keep a Changelog format. Runs on
# `record`, `if: always()`, so it executes regardless of which earlier job
# failed.
#
# Usage: record-release.sh <tag> <digest> <deploy-result> <verify-result> <rollback-result> <declaration> [reverted-to-digest]
#   <tag>              the version tag this run released, e.g. v1.2.3
#   <digest>           the build job's own "sha256:..." output
#   <deploy-result>/<verify-result>/<rollback-result>
#                      each one of GitHub Actions' own job.result values —
#                      "success", "failure", or "skipped" (rollback is
#                      "skipped" whenever verify itself passed)
#   <declaration>      this release's own irreversibility declaration text
#                      (the tag annotation — empty for a reversible release)
#   [reverted-to-digest]
#                      the rollback job's own output — which digest it
#                      redeployed. Only meaningful when <rollback-result> is
#                      not "skipped"; this script never reads it from the
#                      host's own state file, since this job may run on a
#                      different runner that shares no filesystem with it.

TAG="${1:?tag required}"
DIGEST="${2:?digest required}"
DEPLOY_RESULT="${3:?deploy result required}"
VERIFY_RESULT="${4:?verify result required}"
ROLLBACK_RESULT="${5:?rollback result required}"
DECLARATION="${6:-}"
REVERTED_TO_DIGEST="${7:-}"

VERSION="${TAG#v}"
ACTOR="${GITHUB_ACTOR:-unknown}"
TIMESTAMP="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"

# --- Outcome, in the order these can actually happen ------------------------
if [ "$DEPLOY_RESULT" != "success" ]; then
    OUTCOME="Failed — deploy itself did not complete. No traffic was ever cut over to this release."
elif [ "$VERIFY_RESULT" = "success" ]; then
    OUTCOME="Deployed and healthy."
elif [ "$ROLLBACK_RESULT" = "success" ]; then
    OUTCOME="Rolled back automatically — this release failed its own health check; the previous known-good release was redeployed and confirmed healthy."
else
    OUTCOME="ESCALATED — this release failed its own health check, and the automatic rollback that followed did not restore health either. The application remains in maintenance mode. Human investigation required: see docs/operations/en/read-a-failed-pipeline.md and docs/operations/en/restore.md."
fi

# --- CHANGELOG.md section for this version -----------------------------------
# Keep a Changelog heading shape: "## [1.2.3] - 2026-08-21" (brackets and the
# date are both optional as far as this extraction cares — only the version
# number inside the heading line has to match).
CHANGELOG_SECTION="$(awk -v version="$VERSION" '
    /^## / {
        if (found) exit
        if (index($0, version) > 0) { found = 1; next }
        next
    }
    found { print }
' CHANGELOG.md)"

if [ -z "$CHANGELOG_SECTION" ]; then
    CHANGELOG_SECTION="(No CHANGELOG.md section found for ${VERSION} — the section heading must read \`## [${VERSION}]\` before this release is tagged.)"
fi

BODY="$(cat <<BODY_EOF
${CHANGELOG_SECTION}

---

**Outcome:** ${OUTCOME}

**Digest:** \`${DIGEST}\`
**Triggered by:** ${ACTOR}
**Recorded:** ${TIMESTAMP}
**Irreversibility declaration:** ${DECLARATION:-none — this release is reversible by a plain rollback}
BODY_EOF
)"

if gh release view "$TAG" >/dev/null 2>&1; then
    echo "==> Updating existing release record for ${TAG}"
    gh release edit "$TAG" --notes "$BODY"
else
    echo "==> Creating release record for ${TAG}"
    # --verify-tag: this script only ever attaches a record to a tag the
    # push trigger already proved exists — it must never be the one thing
    # that creates a tag, which `gh release create` otherwise does silently
    # for a tag name it does not recognize.
    gh release create "$TAG" --title "$TAG" --notes "$BODY" --verify-tag
fi

# --- Reversal note on the release this run reverted back to ------------------
# Best-effort: scans recent release bodies for the one already annotated
# with the digest rollback.sh actually redeployed. Not finding it is not a
# failure of this job — the reversal is already fully recorded on this
# release's own entry above; this second annotation is a convenience for
# whoever is reading the *older* release later, not the only record of what
# happened.
if [ "$VERIFY_RESULT" = "failure" ] && [ "$ROLLBACK_RESULT" != "skipped" ] && [ -n "$REVERTED_TO_DIGEST" ]; then
    REVERTED_TO_TAG="$(gh release list --limit 20 --json tagName --jq '.[].tagName' | while read -r candidate; do
        if gh release view "$candidate" --json body --jq '.body' 2>/dev/null | grep -qF "$REVERTED_TO_DIGEST"; then
            echo "$candidate"
            break
        fi
    done)"

    if [ -n "$REVERTED_TO_TAG" ]; then
        echo "==> Annotating ${REVERTED_TO_TAG} as reinstated by this rollback"
        EXISTING_NOTES="$(gh release view "$REVERTED_TO_TAG" --json body --jq '.body')"
        gh release edit "$REVERTED_TO_TAG" --notes "$(printf '%s\n\n---\n\n**Reinstated:** %s, by an automatic rollback from %s (%s).' "$EXISTING_NOTES" "$TIMESTAMP" "$TAG" "$DIGEST")"
    else
        echo "==> Could not identify which prior release ${REVERTED_TO_DIGEST} belongs to — skipping the reciprocal annotation. ${TAG}'s own record above already states the full outcome."
    fi
fi

echo "==> Release record complete for ${TAG}: ${OUTCOME}"
