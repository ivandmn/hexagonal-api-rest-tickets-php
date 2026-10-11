FROM dunglas/frankenphp:1.13.0-php8.5

WORKDIR /web

ARG USER
ARG USER_ID
ARG GROUP_ID
ARG USER_NAME
ARG USER_EMAIL

ENV FRANKENPHP_WORKER_CONFIG=watch

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

RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    bash-completion \
    less \
    make \
    micro \
    openssh-client \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    @composer \
    amqp \
    apcu \
    exif \
    gd \
    intl \
    pcntl \
    pdo_pgsql \
    redis \
    zip \
    xdebug

COPY etc/app/dev/php/php.ini $PHP_INI_DIR/app.conf.d/10-app.ini

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY etc/caddy/Caddyfile /etc/frankenphp/Caddyfile

HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1

COPY --chmod=755 etc/app/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

RUN getent group ${GROUP_ID} || groupadd -g ${GROUP_ID} ${USER}
RUN useradd --no-log-init -m -s /bin/bash -u ${USER_ID} -g ${GROUP_ID} ${USER}
RUN chown -R ${USER_ID}:${GROUP_ID} /home/${USER} /web /config/caddy /data/caddy

COPY --chown=${USER_ID}:${GROUP_ID} etc/bash/.bashrc /home/${USER}/.bashrc
COPY --chown=${USER_ID}:${GROUP_ID} etc/bash/.bash_aliases /home/${USER}/.bash_aliases
COPY --chown=${USER_ID}:${GROUP_ID} etc/bash/.gitconfig /home/${USER}/.gitconfig

USER ${USER}

RUN git config --global --add safe.directory /web \
    && if [ -n "${USER_NAME}" ]; then git config --global user.name "${USER_NAME}"; fi \
    && if [ -n "${USER_EMAIL}" ]; then git config --global user.email "${USER_EMAIL}"; fi
