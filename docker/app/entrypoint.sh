#!/bin/sh
set -e

# Laravel writes compiled views, framework cache, sessions, and logs at runtime,
# and the FPM pool runs as www-data. On a bind mount the project files arrive
# owned by the host user, so those directories are unwritable until ownership is
# corrected — which surfaces as a bare HTTP 500 with no log entry, because the
# failure is the logger itself being unable to open its file.
#
# Scoped to the two runtime-writable trees. A recursive chown over vendor/ would
# cost seconds on every container start and buy nothing.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec "$@"
