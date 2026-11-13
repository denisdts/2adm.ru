#!/bin/sh
# -----------------------------------------------------------------------------
# Container entrypoint.
#   1. render nginx / php configuration from templates
#   2. wait for mysql, redis and clickhouse
#   3. install composer dependencies (dev convenience)
#   4. run database migrations
#   5. start supervisor (php-fpm + nginx)
# -----------------------------------------------------------------------------
set -eu

log() {
    printf '[entrypoint] %s\n' "$*"
}

WWW_DIR="${WWW_DIR:-/var/www/html}"
APP_PORT="${APP_PORT:-7060}"
APP_ENV="${APP_ENV:-local}"
PHP_MEMORY_LIMIT="${PHP_MEMORY_LIMIT:-256M}"
PHP_MAX_EXECUTION_TIME="${PHP_MAX_EXECUTION_TIME:-60}"
PHP_OPCACHE_VALIDATE_TIMESTAMPS="${PHP_OPCACHE_VALIDATE_TIMESTAMPS:-1}"

# --- 1. render templates -----------------------------------------------------
render_templates() {
    if [ -f /etc/nginx/templates/site.conf.template ]; then
        envsubst '${APP_PORT} ${WWW_DIR}' \
            < /etc/nginx/templates/site.conf.template \
            > /etc/nginx/conf.d/default.conf
        log "nginx config rendered (listen ${APP_PORT})"
    fi

    if [ -f /usr/local/etc/php/templates/99-app.ini.template ]; then
        envsubst '${PHP_MEMORY_LIMIT} ${PHP_MAX_EXECUTION_TIME} ${PHP_OPCACHE_VALIDATE_TIMESTAMPS} ${APP_ENV}' \
            < /usr/local/etc/php/templates/99-app.ini.template \
            > /usr/local/etc/php/conf.d/99-app.ini
        log "php ini rendered (env=${APP_ENV})"
    fi
}

# --- 2. wait for dependencies ------------------------------------------------
wait_for_tcp() {
    host="$1"
    port="$2"
    timeout="$3"
    label="$4"

    if [ -z "$host" ]; then
        return 0
    fi

    log "waiting for ${label} ..."
    if php -r '
        $host = $argv[1];
        $port = (int) $argv[2];
        $timeout = (float) $argv[3];
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $errno = 0;
            $errstr = "";
            $sock = @fsockopen($host, $port, $errno, $errstr, 1.0);
            if ($sock !== false) {
                fclose($sock);
                exit(0);
            }
            usleep(300000);
        }
        exit(1);
    ' "$host" "$port" "$timeout"; then
        log "${label} is up"
    else
        log "WARNING: ${label} is not reachable after ${timeout}s, starting anyway"
    fi
}

# --- 3. composer dependencies ------------------------------------------------
install_dependencies() {
    if [ ! -f "${WWW_DIR}/composer.json" ]; then
        return 0
    fi

    needs_install=1
    if [ -f "${WWW_DIR}/vendor/autoload.php" ]; then
        if [ -f "${WWW_DIR}/composer.lock" ]; then
            if [ "${WWW_DIR}/composer.lock" -nt "${WWW_DIR}/vendor/autoload.php" ]; then
                needs_install=1
            else
                needs_install=0
            fi
        elif [ "${WWW_DIR}/composer.json" -nt "${WWW_DIR}/vendor/autoload.php" ]; then
            needs_install=1
        else
            needs_install=0
        fi
    fi

    if [ "$needs_install" = "1" ]; then
        log "installing composer dependencies ..."
        if (cd "${WWW_DIR}" && composer install \
                --no-dev \
                --no-interaction \
                --no-progress \
                --no-scripts \
                --optimize-autoloader); then
            log "composer dependencies installed"
        else
            log "WARNING: composer install failed, continuing"
        fi
    fi
}

# --- 4. migrations -----------------------------------------------------------
run_migrations() {
    log "running migrations ..."
    if (cd "${WWW_DIR}" && php bin/console.php migrate); then
        log "migrations applied"
        return 0
    fi

    log "ERROR: migrations failed; set MIGRATE_ON_START=false if the schema is managed externally"
    exit 1
}

# --- main --------------------------------------------------------------------
if [ "${APP_ENV}" = "production" ]; then
    if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "dev-only-change-me" ]; then
        log "ERROR: APP_KEY must be set to a strong unique value in production"
        log "       generate one with: openssl rand -hex 32"
        exit 1
    fi
fi

render_templates

mkdir -p "${WWW_DIR}/var/cache" "${WWW_DIR}/var/log" /var/log/nginx /var/lib/nginx/tmp
chown -R www-data:www-data "${WWW_DIR}/var" 2>/dev/null || true

if [ "${WAIT_FOR_SERVICES:-true}" = "true" ]; then
    wait_timeout="${WAIT_TIMEOUT:-60}"
    wait_for_tcp "${DB_HOST:-}" "${DB_PORT:-3306}" "$wait_timeout" "mysql ${DB_HOST:-}"
    wait_for_tcp "${REDIS_HOST:-}" "${REDIS_PORT:-6379}" "$wait_timeout" "redis ${REDIS_HOST:-}"
    wait_for_tcp "${CLICKHOUSE_HOST:-}" "${CLICKHOUSE_PORT:-8123}" "$wait_timeout" "clickhouse ${CLICKHOUSE_HOST:-}"
fi

if [ "${AUTO_COMPOSER_INSTALL:-false}" = "true" ]; then
    install_dependencies
fi

if [ "${MIGRATE_ON_START:-true}" = "true" ]; then
    run_migrations
fi

log "starting php-fpm and nginx on port ${APP_PORT}"
exec "$@"
