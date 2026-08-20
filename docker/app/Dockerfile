# syntax=docker/dockerfile:1

# Application runtime for the tourism portal.
#
# One image serves three roles — web (PHP-FPM), queue worker (Horizon), and the
# CLI used for Composer, Artisan, and the test suite. Keeping them on one image
# is what makes "the worker is a deployment mode, not a second deployable" true:
# a worker that drifts from the web runtime fails in ways no test reproduces.
#
# Two families of target share this file:
#   - `base`       — the development runtime docker-compose.yml builds
#                    (`target: base`). Source arrives via bind mount; the full
#                    dev toolchain (Composer, Node/pnpm, git, pcov) lives here.
#   - `production` — the release artefact `.github/workflows/release.yml`
#                    builds and pushes. Source is copied in and dependencies
#                    are installed at build time; no dev toolchain ships.
# Both derive from `runtime`, which holds exactly the PHP extensions and
# system packages every role needs regardless of environment.

FROM php:8.5-fpm AS runtime

# The extension installer resolves system packages and build flags per
# extension. Hand-rolling docker-php-ext-install for intl, imagick, and redis is
# where these Dockerfiles usually go wrong.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

# intl      : required by Filament; also drives locale-aware formatting
# pdo_pgsql : PostgreSQL driver (pgsql for the raw functions PostGIS work uses)
# redis     : cache, session store, and the queue broker
# imagick   : Media Library conversions — thumbnails, card, gallery derivatives
# pcntl     : Horizon needs process signals to supervise workers
# opcache   : not optional in any environment where response time is a budget
RUN install-php-extensions \
        intl \
        pdo_pgsql \
        pgsql \
        redis \
        imagick \
        gd \
        zip \
        bcmath \
        exif \
        pcntl \
        opcache

# postgresql-client-18 provides pg_dump, which the scheduled database backup
# shells out to (no PHP driver performs a full-database dump on its own).
# pg_dump refuses to dump from a server *newer* than itself, and the
# database server is Postgres 18 (docker-compose.yml's postgis image) — the
# Debian base image's own default postgresql-client package resolves to an
# older client, so the PGDG apt repository is added to install a client
# matching the server's own major version instead.
RUN . /etc/os-release \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt ${VERSION_CODENAME}-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client-18 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# `base` — development runtime. docker-compose.yml's app/worker/scheduler/pulse
# services build this target explicitly; source arrives through the bind mount
# declared there, never through COPY.
# ---------------------------------------------------------------------------
FROM runtime AS base

# pcov: coverage driver for `composer test:coverage` — an order of magnitude
# faster than Xdebug because it only instruments files actually covered,
# which matters once the seeded-volume benchmark suite runs in the same
# pipeline as the coverage gate. Development-only: the production target
# below never installs it.
RUN install-php-extensions pcov

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Node is present so the Vite build can run in the same image as the PHP
# toolchain; the asset pipeline is part of the application, not a separate
# deployable. git is required at runtime, not just at image-build time — the
# JS quality gate (fallow audit/review) diffs against a base branch, and
# Composer's own VCS-based installs fall back to it too.
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y --no-install-recommends nodejs git \
    && npm install -g pnpm@latest \
    && rm -rf /var/lib/apt/lists/*

# Composer runs as root inside the container; the bind mount on the developer's
# machine owns the files, so no user remapping is needed locally.
ENV COMPOSER_ALLOW_SUPERUSER=1

# ---------------------------------------------------------------------------
# `vendor` — installs PHP dependencies against the committed lockfile, isolated
# from the asset build so a Composer failure and a pnpm failure are never
# conflated. Never shipped directly; `production` below copies its output.
# ---------------------------------------------------------------------------
FROM runtime AS vendor

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Dependencies first, from the lockfile alone, so this layer caches across
# builds that only change application code.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

# --optimize builds the classmap once at build time rather than on every
# request; --no-dev keeps Pest, Larastan, and the rest of require-dev out of
# the autoloader entirely — not merely unused, but not resolvable.
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && composer run-script post-autoload-dump --no-interaction

# ---------------------------------------------------------------------------
# `assets` — the Vite build, isolated from the PHP toolchain entirely: this
# stage never runs PHP, and `production` copies only its compiled output.
# ---------------------------------------------------------------------------
FROM node:24-alpine AS assets

WORKDIR /var/www/html

RUN corepack enable

# pnpm-workspace.yaml carries the project's `minimumReleaseAgeExclude`
# policy (pnpm's own supply-chain protection: a package resolves only once
# it has been published for a minimum age, unless explicitly excused here).
# Omitting it from this COPY still installs successfully most days, then
# fails intermittently whenever a pinned dependency's latest matching
# release is recent enough to trip the default policy — confirmed live
# against vite@8.2.2 while first building this stage.
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml .npmrc ./
RUN pnpm install --frozen-lockfile

COPY . .

# The one legitimate build-time secret: the client-side map tile credential
# is public by construction once shipped, so
# it is supplied here as a scoped build argument rather than baked in as a
# runtime `.env` value the asset build cannot read anyway. Left empty by
# default so a plain `docker build` (no secret available) still succeeds; the
# release workflow supplies the real value.
ARG MAP_TILE_KEY=""
ENV VITE_MAP_TILE_KEY=${MAP_TILE_KEY}

RUN pnpm build

# ---------------------------------------------------------------------------
# `production` — the release artefact. No Composer, no Node/pnpm, no pcov, no
# git, no test suite: shipped assets and shipped code come from `vendor` and
# `assets` above, never rebuilt here.
# ---------------------------------------------------------------------------
FROM runtime AS production

COPY --from=vendor /var/www/html /var/www/html
COPY --from=assets /var/www/html/public/build /var/www/html/public/build

# The FPM pool (php-fpm's own default www.conf) runs as www-data; these are
# the two trees Laravel writes to at runtime (compiled views, framework
# cache, sessions, logs, the bootstrap cache). Scoped rather than a blanket
# chown over the whole tree, matching entrypoint.sh's own reasoning for the
# bind-mounted development case.
RUN chown -R www-data:www-data storage bootstrap/cache
