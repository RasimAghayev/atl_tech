# syntax=docker/dockerfile:1
#
# Multi-stage build (CLAUDE.md / PROMPT.md Docker standard):
#   base -> composer_bin -> deps -> test   (docker build --target test fails the build if tests fail)
#                        \-> deps-prod -> runtime
#
# `docker build --target test .` is the Phase 5/11 verification gate; `runtime` is what
# docker-compose actually runs. Dev-only tooling (build-essential, git, phpunit, etc.)
# never reaches the runtime image.

ARG PHP_VERSION=8.5

# ---------------------------------------------------------------------------
# base: PHP + the extensions the application actually uses, nothing else.
# (mysqli/pdo_mysql were removed — the app is PostgreSQL-only; config/database.php
# already guards every mysql-specific option with extension_loaded('pdo_mysql'),
# proving the extension was never required. `sockets` was added: php-amqplib ^3.0
# requires ext-sockets and the image previously lacked it — a latent bug that
# would only surface the first time RabbitMQPublisher actually ran.)
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm AS base

# Extensions are installed ONE PER `docker-php-ext-install` call (cheap insurance
# against the helper script's known aggregate-install-modules flakiness when batching
# many extensions in one call).
#
# Three real bugs were found and fixed empirically here (each reproduced in
# isolation with throwaway `docker run php:8.5-fpm` containers before landing
# in this Dockerfile):
#
# 1. `pdo` is NOT installed explicitly. It ships compiled in by default on the
#    official php images; `docker-php-ext-install pdo` produces no loadable
#    module (nothing under ext/pdo/modules/) — only the *driver* (pdo_pgsql)
#    needs installing.
# 2. `docker-php-ext-configure <ext>` registers that extension as
#    "configured-as-shared" in the shared /usr/src/php build tree *before* it
#    is actually built. Every docker-php-ext-install call's final aggregate
#    `make install-modules` step then tries to collect modules for EVERY
#    currently-configured-as-shared extension, not just the one just
#    installed — so any docker-php-ext-install call that runs after a
#    docker-php-ext-configure for an extension not yet installed fails with
#    "cp: cannot stat 'modules/*'" (it tries to collect the not-yet-built
#    module). Fix: docker-php-ext-configure gd is immediately followed by
#    docker-php-ext-install gd, with nothing else in between. The previous
#    `docker-php-ext-configure pgsql --with-pgsql=...` line is removed
#    entirely — it configured a *different*, never-installed extension
#    ("pgsql", not "pdo_pgsql") purely as vestigial dead configuration, which
#    poisoned every install call the same way; libpq-dev alone is sufficient
#    for pdo_pgsql to autodetect libpq without it.
# 3. `opcache` is NOT installed explicitly either — same root cause as (1),
#    confirmed by reproduction: a bare, untouched `php:8.5-fpm` container
#    already prints "with Zend OPcache v8.5.13" in `php -v`, already reports
#    `opcache.enable => On` in `php -i`, and has no `opcache.so` under
#    `/usr/local/lib/php/extensions/*/` and no conf.d ini for it — it is
#    compiled into this image's core by default on PHP 8.5, exactly like pdo.
#    Manually running phpize/configure/make for ext/opcache in isolation
#    (bypassing docker-php-ext-install) does report "Build complete" but
#    leaves `modules/` empty — there is no separate shared module to collect,
#    so the explicit install call's aggregate `make install-modules` step
#    fails with the same "cp: cannot stat 'modules/*'" every time, regardless
#    of ordering. Fix: don't install it; `server/php/custom.ini` already sets
#    `opcache.enable`/tuning directives, which apply to the built-in opcache
#    exactly the same as they would to an installed one.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libpq-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd \
    && docker-php-ext-install -j"$(nproc)" mbstring \
    && docker-php-ext-install -j"$(nproc)" exif \
    && docker-php-ext-install -j"$(nproc)" pcntl \
    && docker-php-ext-install -j"$(nproc)" bcmath \
    && docker-php-ext-install -j"$(nproc)" zip \
    && docker-php-ext-install -j"$(nproc)" sockets \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY ./server/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html/be

# ---------------------------------------------------------------------------
# composer_bin: official Composer binary (no curl | php installer script)
# ---------------------------------------------------------------------------
FROM composer:2 AS composer_bin

# ---------------------------------------------------------------------------
# deps: full install including dev requirements — this is what `test` runs
# against. BuildKit cache mount keeps repeat builds fast without baking the
# package cache into any image layer.
# ---------------------------------------------------------------------------
FROM base AS deps
USER root
RUN apt-get update && apt-get install -y --no-install-recommends build-essential git unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY --from=composer_bin /usr/bin/composer /usr/local/bin/composer
COPY src/be/composer.json src/be/composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache,sharing=locked \
    composer install --no-interaction --no-scripts --no-autoloader
COPY src/be/ ./
RUN composer dump-autoload --optimize

# ---------------------------------------------------------------------------
# test: the build-time quality gate. Runs the real suite against sqlite
# in-memory (fast, no external services) — production-parity Postgres
# integration coverage runs separately via `make test-integration`
# (docs/BASELINE.md documents why: sqlite can't exercise the Postgres-only
# `FOR UPDATE SKIP LOCKED` outbox-claim path).
# ---------------------------------------------------------------------------
# `php artisan key:generate --force` was tried here first but fails: by default
# it writes the generated key back into a `.env` file, and this stage (like the
# rest of this Docker-first setup) has no `.env` file at all — config is 100%
# environment variables — so it errors with "file_get_contents(.env): Failed to
# open stream". Fix: set APP_KEY directly as a fixed value, the same way every
# other test-only setting below is a literal ENV rather than something
# generated/written at build/run time. This is NOT a real secret: it is a
# throwaway key with no production meaning, scoped to this `test` stage only —
# `runtime` (what docker-compose ships) is built from `deps-prod`, never from
# `deps`/`test`, so this value never reaches a running container.
FROM deps AS test
ENV APP_ENV=testing \
    APP_KEY=base64:yogsD2XPDnVaOYlitK+f1RR7jj3T08ps8YOivKw1XBs= \
    DB_CONNECTION=sqlite \
    DB_DATABASE=:memory: \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync \
    MAIL_MAILER=array
RUN vendor/bin/phpunit --testsuite=Unit,Feature

# ---------------------------------------------------------------------------
# deps-prod: production-only dependencies (no dev requirements, optimized
# autoloader). Built independently of `deps` so a dev-only package can never
# leak into the runtime image via a shared layer.
# ---------------------------------------------------------------------------
FROM base AS deps-prod
USER root
RUN apt-get update && apt-get install -y --no-install-recommends unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY --from=composer_bin /usr/bin/composer /usr/local/bin/composer
COPY src/be/composer.json src/be/composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache,sharing=locked \
    composer install --no-interaction --no-dev --no-scripts --no-autoloader --optimize-autoloader
COPY src/be/ ./
# tests/ and phpunit.xml are present in the build context (.dockerignore no
# longer strips tests/ — the `deps`/`test` stage needs it) but have no place
# in the production image `runtime` is built from; strip them here instead.
RUN rm -rf tests phpunit.xml \
    && composer dump-autoload --no-dev --optimize \
    && composer clear-cache

# ---------------------------------------------------------------------------
# runtime: what docker-compose actually runs. NOT built on top of `test` —
# its only ancestor is `base` -> `deps-prod`, so BuildKit does NOT execute
# the `test` stage as a side effect of `--target runtime` (a `deps-prod`
# layer cache hit would otherwise let a broken `test` stage go unnoticed).
# `test` passing is a separate, mandatory gate run explicitly as its own
# step (`make verify` / CI) BEFORE `runtime` is built or shipped — see
# docs/BASELINE.md / .github/workflows. Never treat a green `runtime` build
# alone as proof the suite passed.
# ---------------------------------------------------------------------------
FROM base AS runtime

COPY --from=deps-prod /var/www/html/be /var/www/html/be

RUN mkdir -p /var/log/php \
    && chown -R www-data:www-data /var/www/html/be/storage /var/www/html/be/bootstrap/cache /var/log/php \
    && chmod -R 775 /var/www/html/be/storage /var/www/html/be/bootstrap/cache \
    && chmod 755 /var/log/php

USER www-data

# php-fpm -t only validates config syntax, not that the pool is actually
# accepting connections; probing the FastCGI TCP port is a real liveness check.
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r "exit(@fsockopen('127.0.0.1', 9000) ? 0 : 1);" || exit 1

EXPOSE 9000

CMD ["php-fpm"]
