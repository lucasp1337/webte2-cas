# syntax=docker/dockerfile:1.7

# --- base: shared between web and cli ---
FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache \
      git zip unzip libpng-dev oniguruma-dev libxml2-dev icu-dev libzip-dev \
      mysql-client autoconf g++ make linux-headers \
 && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl opcache zip \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del autoconf g++ make linux-headers \
 && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache nodejs npm

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Source is volume-mounted in dev (see docker-compose.yml); production image will COPY in a later phase.

# --- web target: PHP-FPM, served behind nginx ---
FROM base AS web
EXPOSE 9000
CMD ["php-fpm"]

# --- cli target: Horizon (PID 1) + scheduler ---
FROM base AS cli
COPY docker/php/cli-entrypoint.sh /usr/local/bin/cli-entrypoint.sh
RUN chmod +x /usr/local/bin/cli-entrypoint.sh
CMD ["cli-entrypoint.sh"]
