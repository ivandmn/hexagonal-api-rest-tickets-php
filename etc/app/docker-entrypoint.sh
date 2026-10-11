#!/bin/sh
set -e

log() {
    echo "[entrypoint] $1"
}

if [ "$1" = 'frankenphp' ]; then
    # Dev only: the prod image ships its vendors and has no Composer.
    if [ "$APP_ENV" != 'prod' ] && [ ! -f vendor/autoload_runtime.php ] && command -v composer >/dev/null; then
        log "vendor/ not found, running composer install..."
        composer install --prefer-dist --no-progress --no-interaction
    fi

    if [ "${RUN_MIGRATIONS:-false}" = 'true' ]; then
        log "Waiting for the database..."
        ATTEMPTS_LEFT=${DB_WAIT_ATTEMPTS:-30}
        until bin/console dbal:run-sql -q "SELECT 1" >/dev/null 2>&1; do
            ATTEMPTS_LEFT=$((ATTEMPTS_LEFT - 1))
            if [ "$ATTEMPTS_LEFT" -le 0 ]; then
                log "The database is not responding:"
                bin/console dbal:run-sql "SELECT 1"
                exit 1
            fi
            sleep 2
        done

        # The migrations table lives in the "tickets" schema, so it has to exist before migrating.
        bin/console dbal:run-sql -q "CREATE SCHEMA IF NOT EXISTS tickets"

        log "Running migrations..."
        bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing --allow-no-migration
    fi
fi

exec docker-php-entrypoint "$@"
