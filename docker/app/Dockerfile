# syntax=docker/dockerfile:1

# Application runtime for the tourism portal.
#
# One image serves three roles — web (PHP-FPM), queue worker (Horizon), and the
# CLI used for Composer, Artisan, and the test suite. Keeping them on one image
# is what makes "the worker is a deployment mode, not a second deployable" true:
# a worker that drifts from the web runtime fails in ways no test reproduces.

FROM php:8.5-fpm AS base

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
# pcov      : coverage driver for `composer test:coverage` — an order of
#             magnitude faster than Xdebug because it only instruments files
#             actually covered, which matters once the seeded-volume benchmark
#             suite runs in the same pipeline as the coverage gate.
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
        opcache \
        pcov

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

# Composer runs as root inside the container; the bind mount on the developer's
# machine owns the files, so no user remapping is needed locally.
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
