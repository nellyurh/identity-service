# syntax=docker/dockerfile:1
# Multi-stage build (ADR-023): deps resolved with a full toolchain, runtime is slim and
# runs as a non-root user. Image is deployed by digest, never by tag, in CD.

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.3-cli-bookworm AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql intl opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove && rm -rf /var/lib/apt/lists/*

RUN groupadd -g 10001 app && useradd -u 10001 -g app -s /usr/sbin/nologin app
WORKDIR /app
COPY --chown=app:app . .
COPY --from=vendor --chown=app:app /app/vendor ./vendor

ENV APP_ENV=production
USER app
EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=3s --retries=3 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1:8080/healthz') ? 0 : 1);"

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]
