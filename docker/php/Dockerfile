# syntax=docker/dockerfile:1.7

# --- base: shared between web and cli ---
# Using bookworm (Debian). php:8.5-fpm-bookworm is the stable base image.
# opcache is bundled in the base image; only docker-php-ext-enable is needed.
FROM php:8.5-fpm-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
      git zip unzip libpng-dev libonig-dev libxml2-dev libicu-dev libzip-dev \
      default-mysql-client \
      curl \
 && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# The project is bind-mounted at /var/www/html and owned by the host user,
# not the container user. Mark it as a safe directory so git (invoked by
# composer, artisan, etc.) doesn't refuse with "detected dubious ownership".
RUN git config --system --add safe.directory /var/www/html

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get install -y --no-install-recommends nodejs \
 && rm -rf /var/lib/apt/lists/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Chromium for Browsershot (GenerateApiDocsPdfJob in the cli queue worker).
# Installed in the base stage so both web and cli targets carry the binary;
# only the cli worker actually invokes it.
#
# Package groups:
#   chromium              — the headless browser Browsershot drives
#   fonts-liberation      — Browsershot's recommended Latin fonts; avoids
#                           glyph-fallback boxes in generated PDFs
#   libnss3 libfreetype6  — native libs Chromium links at runtime on Debian
#   libharfbuzz0b         — text-shaping library required by Chromium
#   ca-certificates       — TLS root certs so Chromium can fetch same-origin
#                           resources served over HTTPS inside the container
#   libatk1.0-0 libatk-bridge2.0-0 libcups2 libxkbcommon0 libxcomposite1
#   libxdamage1 libxfixes3 libxrandr2 libgbm1 libxshmfence1 libdrm2
#   libpangocairo-1.0-0   — additional Chromium runtime deps absent from
#                           the bookworm slim base layer
RUN apt-get update && apt-get install -y --no-install-recommends \
      chromium \
      fonts-liberation \
      libnss3 \
      libfreetype6 \
      libharfbuzz0b \
      ca-certificates \
      libatk1.0-0 \
      libatk-bridge2.0-0 \
      libcups2 \
      libxkbcommon0 \
      libxcomposite1 \
      libxdamage1 \
      libxfixes3 \
      libxrandr2 \
      libgbm1 \
      libxshmfence1 \
      libdrm2 \
      libpangocairo-1.0-0 \
 && rm -rf /var/lib/apt/lists/*

ENV BROWSERSHOT_CHROME_PATH=/usr/bin/chromium

WORKDIR /var/www/html

# Source is volume-mounted in dev (see docker-compose.yml); a production image would COPY it in.

# --- web target: PHP-FPM, served behind nginx ---
FROM base AS web
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build
EXPOSE 9000
CMD ["php-fpm"]

# --- cli target: Horizon (PID 1) + scheduler ---
FROM base AS cli
COPY docker/php/cli-entrypoint.sh /usr/local/bin/cli-entrypoint.sh
RUN chmod +x /usr/local/bin/cli-entrypoint.sh
CMD ["cli-entrypoint.sh"]
