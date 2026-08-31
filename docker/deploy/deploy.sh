#!/bin/sh
set -eu

# Applies one release to the production host. Runs on the self-hosted runner
# installed on that host — see docs/release/pipeline.md for why a
# self-hosted runner is the mechanism that lets the host "pull" with no
# inbound access to it ever required: the runner polls GitHub outbound,
# exactly like a deploy script polling for its own work would, so there is
# no separate "runner reaching into the host network" to secure in the
# first place.
#
# Step order is specified, not incidental:
#   1. Pin the digest        — reversible; nothing has changed yet.
#   2. Enter maintenance mode — before anything touches the live release.
#   3. Run migrations         — the one irreversible step.
#   4. Restart app/worker/scheduler/pulse, then nginx last — so the first
#      request nginx forwards after restarting reaches an application that
#      has already started.
#   5. Warm caches            — against the app service specifically; worker,
#      scheduler, and pulse serve no HTTP requests, so a cold
#      bootstrap/cache on their own containers costs nothing a visitor feels.
#   6. Regenerate the sitemap — after migrations, so its queries see the
#      deployed schema; before leaving maintenance, so the first crawler
#      request is not served the previous release's (or, on a first deploy,
#      an empty) sitemap while it waits for the hourly schedule.
#   7. Leave maintenance mode.
#
# Usage: deploy.sh <image-digest>
# <image-digest> is the build job's own "sha256:..." output — never
# re-resolved here, so this script can only ever deploy what the pipeline
# actually built and the environment's reviewers actually approved.

IMAGE_DIGEST="${1:?Usage: deploy.sh <image-digest>}"
export IMAGE_DIGEST

# Explicit, stable across every checkout path a self-hosted runner might use
# — docker compose's default project name is the containing directory's own
# basename, which a fresh per-run checkout does not keep constant. Without
# this, each deploy would silently start a *new* set of containers and
# volumes alongside the previous release's, rather than replacing it.
COMPOSE="docker compose -p booking-production -f docker-compose.production.yml"

echo "==> Deploying ${IMAGE_DIGEST}"

# Idempotent — a no-op on every deploy after the first. Ensures postgres and
# redis exist before anything that depends on them, including on a genuinely
# first deployment where no container has ever run on this host before.
$COMPOSE up -d postgres redis

echo "==> Pulling the release image"
$COMPOSE pull app worker scheduler pulse

# No running `app` service yet means this is the first deployment ever —
# nothing is serving traffic to protect, so maintenance mode is skipped
# rather than failing on a container that does not exist to `exec` into.
FIRST_DEPLOY=false
if ! $COMPOSE ps --status running app --format '{{.Name}}' 2>/dev/null | grep -q .; then
    FIRST_DEPLOY=true
fi

if [ "$FIRST_DEPLOY" = false ]; then
    echo "==> Entering maintenance mode"
    $COMPOSE exec -T app php artisan down \
        --secret="${MAINTENANCE_BYPASS_SECRET:?MAINTENANCE_BYPASS_SECRET is required}" \
        --render="errors::503"
else
    echo "==> First deployment on this host — no live release to protect, skipping maintenance mode"
fi

echo "==> Running migrations"
$COMPOSE run --rm app php artisan migrate --force

echo "==> Restarting app, worker, scheduler, pulse"
$COMPOSE up -d app worker scheduler pulse

echo "==> Warming caches"
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan event:cache
$COMPOSE exec -T app php artisan view:cache

echo "==> Regenerating sitemap artefacts"
$COMPOSE exec -T app php artisan sitemap:generate

echo "==> Restarting nginx (last, so it never fronts a not-yet-started application)"
$COMPOSE up -d nginx

if [ "$FIRST_DEPLOY" = false ]; then
    echo "==> Leaving maintenance mode"
    $COMPOSE exec -T app php artisan up
fi

echo "==> Deploy finished: ${IMAGE_DIGEST}"
