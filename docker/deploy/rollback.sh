#!/bin/sh
set -eu

# Redeploys the last release confirmed healthy — no migration, no data
# touched, "fast, mechanical, and safe to trigger automatically" per
# l1-release-operations.md §5.3. Not a smaller version of deploy.sh:
# rolling back a release that changed the schema is precisely the case this
# script must never be reached for — the destructive-migration scan
# (App\Services\Release\DestructiveMigrationScanner) already refused any
# such release before it was ever built, so every digest this script is
# ever asked to redeploy is safe to redeploy without touching the database.
#
# Usage: rollback.sh <image-digest>
# <image-digest> is the last-known-good digest deploy-record.sh recorded
# after a previous release's own verify step passed — never re-derived here.

IMAGE_DIGEST="${1:?Usage: rollback.sh <image-digest>}"
export IMAGE_DIGEST

COMPOSE="docker compose -p booking-production -f docker-compose.production.yml"

echo "==> Rolling back to ${IMAGE_DIGEST}"

$COMPOSE pull app worker scheduler pulse
$COMPOSE up -d app worker scheduler pulse

echo "==> Warming caches"
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan event:cache
$COMPOSE exec -T app php artisan view:cache

echo "==> Restarting nginx"
$COMPOSE up -d nginx

echo "==> Rollback finished: ${IMAGE_DIGEST}"
