# syntax=docker/dockerfile:1

# One Dockerfile for every environment, so dev and prod run the same FrankenPHP, PHP and extensions:
#
#   base ─┬─ dev     (docker-compose.override.yml): + Composer, Xdebug, shell tools, your UID
#         ├─ build   vendors, autoloader, compiled .env, warm cache (Composer stays here)
#         └─ prod    (CI → GHCR): base + the app from `build`, nothing else
#
# Bump FrankenPHP/PHP here only.
ARG FRANKENPHP_VERSION=1.13.0
ARG PHP_VERSION=8.5

# ---- base: runtime shared by every stage ----
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION} AS base

WORKDIR /web

ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"
# Behind Traefik (Dokploy): plain HTTP on :80 for any Host (TLS ends at Traefik), and trust its
# X-Forwarded-* headers. Both can be overridden from the environment.
ENV SERVER_NAME=:80
ENV TRUSTED_PROXIES=private_ranges

# curl: HEALTHCHECK. libcap2-bin is only needed for setcap (bind :80 without root), then removed.
RUN apt-get update && apt-get install -y --no-install-recommends curl libcap2-bin \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && apt-get purge -y --auto-remove libcap2-bin \
    && rm -rf /var/lib/apt/lists/*

# Only what the app uses; ctype, iconv, mbstring, openssl, pdo... are already built into the image.
RUN install-php-extensions \
    intl \
    pdo_pgsql \
    opcache

COPY etc/caddy/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=755 etc/app/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

# Hits the app itself (Caddy + worker + Symfony), not just the Caddy admin API.
HEALTHCHECK --start-period=60s --interval=30s --timeout=5s CMD curl -fsS -o /dev/null http://localhost/health || exit 1

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

# ---- dev: the code is mounted, Composer and Xdebug available, runs as your host user ----
FROM base AS dev

ARG USER=app
ARG USER_ID=1000
ARG GROUP_ID=1000
ARG USER_NAME
ARG USER_EMAIL

ENV FRANKENPHP_WORKER_CONFIG=watch
ENV COMPOSER_MEMORY_LIMIT=-1

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    bash \
    bash-completion \
    less \
    make \
    micro \
    openssh-client \
    unzip \
    bat \
    jq \
    lnav \
    libxml2-utils \
    && ln -s /usr/bin/batcat /usr/local/bin/bat \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    @composer \
    xdebug

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY etc/app/dev/php/php.ini $PHP_INI_DIR/app.conf.d/10-app.ini

RUN (getent group ${GROUP_ID} || groupadd -g ${GROUP_ID} ${USER}) \
    && useradd --no-log-init -m -s /bin/bash -u ${USER_ID} -g ${GROUP_ID} ${USER} \
    && chown -R ${USER_ID}:${GROUP_ID} /web /config/caddy /data/caddy

COPY --chown=${USER_ID}:${GROUP_ID} etc/bash/.bashrc etc/bash/.bash_aliases etc/bash/.gitconfig /home/${USER}/

USER ${USER}

RUN git config --global --add safe.directory /web \
    && if [ -n "${USER_NAME}" ]; then git config --global user.name "${USER_NAME}"; fi \
    && if [ -n "${USER_EMAIL}" ]; then git config --global user.email "${USER_EMAIL}"; fi

# ---- build: vendors, optimized autoloader, compiled .env and warm cache ----
FROM base AS build

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1
ENV APP_ENV=prod
ENV APP_DEBUG=0

RUN install-php-extensions @composer

COPY composer.* symfony.* ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY . ./

# Placeholder values only to warm the cache: the real ones come from the runtime environment.
RUN mkdir -p var/cache var/log \
    && echo 'APP_ENV=prod' > .env \
    && composer dump-autoload --classmap-authoritative --no-dev \
    && composer dump-env prod \
    && APP_SECRET=build DEFAULT_URI=http://localhost DATABASE_URL="postgresql://build:build@localhost:5432/build?serverVersion=18&charset=utf8" \
        composer run-script --no-dev post-install-cmd

# ---- prod: the image that runs ----
FROM base AS prod

ENV APP_ENV=prod
ENV APP_DEBUG=0
# Workers live as long as the container: recycle each one every N requests so a leak can't grow
# forever (Symfony Runtime reads it). 4 workers × memory_limit 256M stays under the 2g limit.
ENV FRANKENPHP_NUM_WORKERS=4
ENV FRANKENPHP_LOOP_MAX=500

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY etc/app/prod/php/php.ini $PHP_INI_DIR/app.conf.d/10-app.ini

COPY --from=build --chown=www-data:www-data /web /web

# `file` comes with the FrankenPHP base image and is only needed to build extensions.
RUN apt-get purge -y --auto-remove file \
    && chown -R www-data:www-data /config/caddy /data/caddy

USER www-data
