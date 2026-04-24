# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:php8.4 AS php-base

RUN install-php-extensions \
    gd \
    intl \
    opcache \
    pdo_mysql \
    redis \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM oven/bun:1 AS frontend

WORKDIR /app

COPY package.json bun.lock ./
RUN bun install --frozen-lockfile

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN bun run build

FROM php-base AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts

FROM php-base AS app

WORKDIR /app

ENV APP_ENV=production

COPY --from=vendor /app /app
COPY --from=frontend /app/public/build /app/public/build
COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile
COPY docker/frankenphp/entrypoint.sh /usr/local/bin/frankenphp-entrypoint

RUN php artisan package:discover --ansi \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/frankenphp-entrypoint

EXPOSE 80 443 443/udp

ENTRYPOINT ["frankenphp-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
