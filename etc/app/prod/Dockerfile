# ---- base: runtime shared by both stages (FrankenPHP + PHP extensions, no build tools) ----
FROM dunglas/frankenphp:1.13.0-php8.5 AS base

WORKDIR /web

ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"
# Behind Traefik (Dokploy): plain HTTP on :80 for any Host (TLS ends at Traefik), and trust its
# X-Forwarded-* headers. Both can be overridden from the Dokploy environment.
ENV SERVER_NAME=:80
ENV TRUSTED_PROXIES=private_ranges

# curl: HEALTHCHECK. libcap2-bin is only needed for setcap (bind :80 as www-data), then removed.
RUN apt-get update && apt-get install -y --no-install-recommends curl libcap2-bin \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && apt-get purge -y --auto-remove libcap2-bin \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    openssl \
    curl \
    json \
    amqp \
    apcu \
    exif \
    gd \
    intl \
    pcntl \
    pdo \
    pdo_pgsql \
    mbstring \
    redis \
    zip \
    opcache

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY etc/app/prod/php/php.ini $PHP_INI_DIR/app.conf.d/10-app.ini

# ---- build: vendors, optimized autoloader, compiled .env and warm cache (Composer stays here) ----
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
ENV FRANKENPHP_WORKER_CONFIG="num 4"

COPY etc/caddy/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=755 etc/app/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

COPY --from=build --chown=www-data:www-data /web /web

RUN chown -R www-data:www-data /config/caddy /data/caddy

USER www-data

# Hits the app itself (Caddy + worker + Symfony), not just the Caddy admin API.
HEALTHCHECK --start-period=60s --interval=30s --timeout=5s CMD curl -fsS -o /dev/null http://localhost/health || exit 1

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
