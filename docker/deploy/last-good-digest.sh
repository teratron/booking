#!/bin/sh
set -eu

# The one piece of state a self-hosted runner's own ephemeral per-job
# checkout cannot hold: which digest rollback.sh should redeploy if the
# release just verified turns out unhealthy. Lives outside the checkout, in
# the runner service account's own home directory, so it survives between
# workflow runs the same way the running containers themselves do.
#
# Usage:
#   last-good-digest.sh record <digest>   # after verify confirms health
#   last-good-digest.sh read              # before rollback; empty + exit 1
#                                          # on a genuinely first release,
#                                          # which has no prior digest to
#                                          # roll back to at all

STATE_FILE="$HOME/.booking-deploy/last-good-digest"

case "${1:-}" in
    record)
        DIGEST="${2:?Usage: last-good-digest.sh record <digest>}"
        mkdir -p "$(dirname "$STATE_FILE")"
        echo "$DIGEST" > "$STATE_FILE"
        echo "==> Recorded ${DIGEST} as the last known-good release"
        ;;
    read)
        if [ ! -f "$STATE_FILE" ]; then
            echo "::error::No last-known-good digest recorded yet — this is the first release on this host, so there is nothing to roll back to." >&2
            exit 1
        fi
        cat "$STATE_FILE"
        ;;
    *)
        echo "Usage: last-good-digest.sh {record <digest>|read}" >&2
        exit 1
        ;;
esac
