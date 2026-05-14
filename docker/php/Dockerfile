# syntax=docker/dockerfile:1.7

# --- base: shared between web and cli ---
# Using bookworm (Debian). php:8.5-fpm-bookworm is the stable fallback per phase-01 Risks.
# opcache is bundled in the base image; only docker-php-ext-enable is needed.
FROM php:8.5-fpm-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
      git zip unzip libpng-dev libonig-dev libxml2-dev libicu-dev libzip-dev \
      default-mysql-client \
      curl \
 && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

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

# ---------------------------------------------------------------------------
# GeoLite2-City database bake-in (best-effort, build-time only)
#
# The MaxMind license key is passed as a BuildKit secret so it never appears
# in image history or the final image's environment.  The file is placed at
# /var/geolite/GeoLite2-City.mmdb, matching GEOLITE_DB_PATH in .env.example.
#
# Build with the secret:
#   DOCKER_BUILDKIT=1 docker compose build \
#     --secret id=geolite_license_key,env=GEOLITE_LICENSE_KEY web cli
#
# Build without a key (secret file absent or empty) — step is skipped silently
# and the geo service degrades to returning "unknown" for all IPs until the
# RefreshGeolocationDatabaseJob downloads the database at its next scheduled run.
# ---------------------------------------------------------------------------
RUN --mount=type=secret,id=geolite_license_key \
    if [ -s /run/secrets/geolite_license_key ]; then \
        KEY=$(cat /run/secrets/geolite_license_key) && \
        mkdir -p /var/geolite && \
        curl -sSL \
            "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key=${KEY}&suffix=tar.gz" \
            -o /tmp/geolite.tar.gz && \
        tar -xzf /tmp/geolite.tar.gz -C /tmp && \
        find /tmp -name 'GeoLite2-City.mmdb' -exec mv {} /var/geolite/GeoLite2-City.mmdb \; && \
        rm -rf /tmp/geolite.tar.gz /tmp/GeoLite2-City_* && \
        chown -R www-data:www-data /var/geolite ; \
    else \
        echo "GeoLite2 secret not provided; skipping mmdb bake-in (geo service will degrade to unknown)" ; \
    fi

WORKDIR /var/www/html

# Source is volume-mounted in dev (see docker-compose.yml); production image will COPY in a later phase.

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
