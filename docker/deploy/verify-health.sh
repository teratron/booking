#!/bin/sh
set -eu

# Polls the application's own built-in health route (bootstrap/app.php's
# `health: '/up'`) until it answers healthy or a budget expires. Run against
# the host's own nginx directly (http://localhost/up) rather than the public
# domain — the self-hosted runner already sits on the production host, and
# checking there is unaffected by DNS, CDN, or firewall configuration that
# has nothing to do with whether the release itself is healthy.
#
# Usage: verify-health.sh [max-attempts] [interval-seconds]
# Defaults: 10 attempts, 5 seconds apart — a 50-second budget. l2-release-
# pipeline.md deliberately leaves the exact budget to the implementation;
# this one is generous enough to absorb PHP-FPM's own warm-up without
# masking a genuinely unhealthy release for a full minute.

MAX_ATTEMPTS="${1:-10}"
INTERVAL="${2:-5}"
URL="http://localhost/up"

attempt=1
while [ "$attempt" -le "$MAX_ATTEMPTS" ]; do
    # curl's own -w already prints "000" on a connection failure (nothing to
    # connect to yet, or the container still starting) — the `|| true`
    # neutralizes curl's own non-zero exit so `set -e` does not abort the
    # script on that expected, retryable failure; it does not affect what
    # gets captured, since curl already wrote "000" to stdout before exiting.
    status="$(curl -s -o /dev/null -w '%{http_code}' "$URL" || true)"

    if [ "$status" = "200" ]; then
        # The health route says the application booted; this says the panels
        # can actually render. Filament's CSS/JS is gitignored and
        # republished into the image at build time (docker/app/Dockerfile) —
        # an image missing it serves both panels unstyled and dead, which
        # `/up` alone cannot see.
        asset_status="$(curl -s -o /dev/null -w '%{http_code}' 'http://localhost/css/filament/filament/app.css' || true)"

        if [ "$asset_status" != "200" ]; then
            echo "==> /up healthy but Filament panel assets missing (app.css -> ${asset_status}) — release is unhealthy"
            exit 1
        fi

        echo "==> Healthy after ${attempt} attempt(s) (${URL} -> ${status}, panel assets -> ${asset_status})"
        exit 0
    fi

    echo "==> Attempt ${attempt}/${MAX_ATTEMPTS}: ${URL} -> ${status}, not yet healthy"
    attempt=$((attempt + 1))
    [ "$attempt" -le "$MAX_ATTEMPTS" ] && sleep "$INTERVAL"
done

echo "==> Health budget exhausted (${MAX_ATTEMPTS} attempts, ${INTERVAL}s apart) — still unhealthy"
exit 1
