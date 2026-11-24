# BookHub API — multi-stage production image.
#
# Stage 1 (php-base): install shared PHP extensions once.
# Stage 2 (build): install Composer dependencies and optimize autoloader.
# Stage 3 (runtime): Apache on port 8000, non-root www-data, no Composer/git.
#
# Pinned versions (verified to exist on Docker Hub, 2026-11-24):
#   php:8.2.36-apache, composer:2.8.4

# ---------- Stage 1: shared PHP extensions ----------
FROM php:8.2.36-apache AS php-base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo_mysql mbstring exif pcntl bcmath zip \
    && rm -rf /var/lib/apt/lists/*

# ---------- Stage 2: build ----------
FROM php-base AS build

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8.4 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Install dependencies first (better layer caching), then the app code.
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction \
        --no-scripts --optimize-autoloader

COPY . ./
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi || true

# ---------- Stage 3: runtime ----------
FROM php-base AS runtime

# curl is required by the container healthcheck (GET /api/health).
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Apache must listen on 8000 (not the default 80) so the compose healthcheck
# (curl http://localhost:8000/api/health) and Nginx (proxy_pass http://api:8000)
# reach a real HTTP server.
RUN sed -i 's/^Listen 80$/Listen 8000/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8000>/' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/public#' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/bookhub.conf \
    && a2enconf bookhub

WORKDIR /var/www

COPY --from=build --chown=www-data:www-data /var/www /var/www
COPY --chown=www-data:www-data docker-entrypoint.sh /usr/local/bin/bookhub-entrypoint.sh

# Apache runtime dirs must be writable by www-data (the container runs non-root).
RUN mkdir -p /var/run/apache2 /var/log/apache2 /var/lock/apache2 \
    && chown -R www-data:www-data /var/run/apache2 /var/log/apache2 /var/lock/apache2 \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/bookhub-entrypoint.sh

USER www-data

EXPOSE 8000

ENTRYPOINT ["bookhub-entrypoint.sh"]
CMD ["apache2-foreground"]
