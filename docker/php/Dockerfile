# syntax=docker/dockerfile:1.7

# --- base: shared between web and cli ---
# Using bookworm (Debian). php:8.5-fpm-bookworm is the stable fallback per phase-01 Risks.
# opcache is bundled in the base image; only docker-php-ext-enable is needed.
FROM php:8.5-fpm-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
      git zip unzip libpng-dev libonig-dev libxml2-dev libicu-dev libzip-dev \
      default-mysql-client \
 && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends \
      nodejs npm \
 && rm -rf /var/lib/apt/lists/*

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
