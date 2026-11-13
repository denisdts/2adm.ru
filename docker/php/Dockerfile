# syntax=docker/dockerfile:1.7
# -----------------------------------------------------------------------------
# Monitoring service: PHP 8.5 (fpm) + nginx, listening on ${APP_PORT} (7060).
# -----------------------------------------------------------------------------
ARG PHP_IMAGE=php:8.5-fpm-alpine
FROM ${PHP_IMAGE}

ENV TZ=UTC \
    WWW_DIR=/var/www/html \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache

# --- system packages ---------------------------------------------------------
RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        gettext \
        tzdata \
        icu-libs \
        libzip \
        oniguruma \
        libintl \
        libcurl \
        zlib \
        libxml2 \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        curl-dev \
        libxml2-dev \
        linux-headers \
    && docker-php-ext-configure intl --enable-intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        mbstring \
        intl \
        opcache \
        zip \
        bcmath \
        pcntl \
        sockets \
        curl \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

# --- composer ----------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- application code --------------------------------------------------------
WORKDIR ${WWW_DIR}
COPY www/ ${WWW_DIR}/

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader \
    && mkdir -p ${WWW_DIR}/var/cache ${WWW_DIR}/var/log \
    && chown -R www-data:www-data ${WWW_DIR}/var

# --- configuration -----------------------------------------------------------
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/templates/ /etc/nginx/templates/
COPY docker/php/conf.d/zz-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/templates/ /usr/local/etc/php/templates/
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/log/nginx /var/lib/nginx/tmp /etc/nginx/conf.d \
    && rm -f /etc/nginx/conf.d/default.conf

EXPOSE 7060

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
