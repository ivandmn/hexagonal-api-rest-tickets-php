FROM dunglas/frankenphp:1.13.0-php8.5

WORKDIR /web

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

RUN apt-get update && apt-get install -y --no-install-recommends \
    acl \
    curl \
    file \
    gettext-base \
    git \
    libcap2-bin \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    @composer \
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

COPY etc/caddy/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=755 etc/app/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

# Hits the app itself (Caddy + worker + Symfony), not just the Caddy admin API.
HEALTHCHECK --start-period=60s --interval=30s --timeout=5s CMD curl -fsS -o /dev/null http://localhost/health || exit 1

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV FRANKENPHP_WORKER_CONFIG="num 4"
# Behind Traefik (Dokploy): plain HTTP on :80 for any Host (TLS ends at Traefik), and trust its
# X-Forwarded-* headers. Both can be overridden from the Dokploy environment.
ENV SERVER_NAME=:80
ENV TRUSTED_PROXIES=private_ranges

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY etc/app/prod/php/php.ini $PHP_INI_DIR/app.conf.d/10-app.ini

COPY composer.* symfony.* ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY . ./

RUN mkdir -p var/cache var/log \
    && echo 'APP_ENV=prod' > .env \
    && composer dump-autoload --classmap-authoritative --no-dev \
    && composer dump-env prod \
    && APP_SECRET=build DEFAULT_URI=http://localhost DATABASE_URL="postgresql://build:build@localhost:5432/build?serverVersion=18&charset=utf8" \
        composer run-script --no-dev post-install-cmd \
    && chmod +x bin/console \
    && chown -R www-data:www-data var /config/caddy /data/caddy

USER www-data
