# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# 1) Frontend assets (Vite + Tailwind) → public/build
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
RUN npm run build

# ---------------------------------------------------------------------------
# 2) App image: PHP-FPM + nginx + scheduler, managed by supervisord
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS app

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pdo_mysql zip intl bcmath opcache pcntl \
    && apk add --no-cache nginx supervisor gettext \
    && mkdir -p /run/nginx

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Dependencies first so they cache between code changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --no-progress

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && rm -f public/hot \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-iposa.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-iposa.conf
COPY docker/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start.sh /usr/local/bin/start
RUN sed -i 's/\r$//' /usr/local/bin/start /var/www/html/docker/boot.sh \
    && chmod +x /usr/local/bin/start /var/www/html/docker/boot.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=10000

EXPOSE 10000

CMD ["start"]
