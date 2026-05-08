# Phase 01 — Infrastructure (web/cli split, Redis, Horizon)

**Duration**: 1.5–2 d
**Tier**: senior
**Required reading**: `CLAUDE.md`, `docs/ARCHITECTURE.md` §§ 2–3

## Goal

`docker compose up -d` brings the entire stack to a working state with both `web` and `cli` Laravel containers running, Redis backing cache/queue/session, Horizon dashboard reachable, and CI enforcing all quality gates on every PR.

## Definition of Done

- [ ] `docker compose up -d` starts: `nginx`, `web`, `cli`, `mysql`, `redis`, `octave-bridge` (skeleton: returns 200 on `/health`)
- [ ] Laravel 13 installed with Inertia v2 + React 19 + TypeScript strict + Tailwind 4
- [ ] Redis configured as cache, queue, and session driver
- [ ] Horizon installed; `/horizon` reachable in dev (gate enabled in Phase 10)
- [ ] `cli` container runs `php artisan horizon` as PID 1; restarts on failure
- [ ] `cli` container's scheduler picks up `routes/console.php` (test with a noop scheduled command)
- [ ] First migration runs (`users`, `sessions`, `cache`, `jobs` tables present)
- [ ] `composer qa` and `npm run qa` both green locally
- [ ] GitHub Actions runs all quality gates on every PR; required for merge
- [ ] Welcome page reachable at `http://localhost`

## Prerequisites

Phase 00 complete.

## Tasks

### 1.1 Docker Compose skeleton

`docker-compose.yml`:

```yaml
services:
  nginx:
    image: nginx:1.27-alpine
    ports: ["80:80"]
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./public:/var/www/html/public:ro
    depends_on: [web]

  web:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      target: web
    volumes:
      - .:/var/www/html
    environment:
      CONTAINER_ROLE: web
    depends_on: [mysql, redis, octave-bridge]

  cli:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      target: cli
    volumes:
      - .:/var/www/html
    environment:
      CONTAINER_ROLE: cli
    depends_on: [mysql, redis, octave-bridge]
    restart: unless-stopped

  mysql:
    image: mysql:9.0
    environment:
      MYSQL_DATABASE: webte2
      MYSQL_USER: webte2
      MYSQL_PASSWORD: changeme
      MYSQL_ROOT_PASSWORD: changeme
    volumes: [mysql_data:/var/lib/mysql]

  redis:
    image: redis:7-alpine
    volumes: [redis_data:/data]
    command: ["redis-server", "--appendonly", "yes"]

  octave-bridge:
    build: ./docker/octave-bridge
    volumes:
      - octave_sessions:/var/octave/sessions
    environment:
      OCTAVE_BIN: /usr/bin/octave
      SESSION_DIR: /var/octave/sessions
      SLOWDOWN_MS: 500

volumes:
  mysql_data:
  redis_data:
  octave_sessions:
```

### 1.2 PHP image with web + cli targets

`docker/php/Dockerfile` — multi-stage so `web` and `cli` differ only in `CMD`:

```dockerfile
FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache \
      git zip unzip libpng-dev oniguruma-dev libxml2-dev icu-dev \
      mysql-client autoconf g++ make linux-headers \
 && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl opcache \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del autoconf g++ make linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache nodejs npm

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# --- web target: PHP-FPM ---
FROM base AS web
CMD ["php-fpm"]

# --- cli target: Horizon + scheduler via runner script ---
FROM base AS cli
COPY docker/php/cli-entrypoint.sh /usr/local/bin/cli-entrypoint.sh
RUN chmod +x /usr/local/bin/cli-entrypoint.sh
CMD ["cli-entrypoint.sh"]
```

`docker/php/cli-entrypoint.sh`:

```bash
#!/bin/sh
set -e
# Run scheduler in background, Horizon in foreground (PID 1)
php artisan schedule:work &
exec php artisan horizon
```

### 1.3 nginx config

`docker/nginx/default.conf` — standard Laravel + FPM upstream pointing to `web:9000`. `client_max_body_size 16M`.

### 1.4 Octave bridge skeleton

`docker/octave-bridge/Dockerfile`:

```dockerfile
FROM python:3.13-slim
RUN apt-get update && apt-get install -y --no-install-recommends octave \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY pyproject.toml uv.lock /app/
RUN pip install uv && uv sync --frozen --no-dev

COPY src /app/src
RUN mkdir -p /var/octave/sessions && chown -R nobody:nogroup /var/octave

USER nobody
EXPOSE 8001
CMD ["uv", "run", "python", "-m", "src.main"]
```

`docker/octave-bridge/src/main.py` — minimal aiohttp app serving `GET /health` returning `{"status": "ok"}`. Real `/exec` lands in Phase 02.

### 1.5 Laravel install

```bash
docker compose exec web composer create-project laravel/laravel:^13 . --prefer-dist
docker compose exec web php artisan key:generate
docker compose exec web php artisan migrate
```

Composer dependencies:

```bash
docker compose exec web composer require \
  inertiajs/inertia-laravel \
  spatie/laravel-data \
  spatie/browsershot \
  dedoc/scramble \
  laravel/horizon \
  laravel/sanctum \
  geoip2/geoip2 \
  predis/predis

docker compose exec web composer require --dev \
  laravel/pint \
  larastan/larastan \
  pestphp/pest pestphp/pest-plugin-laravel \
  laravel/telescope
```

```bash
docker compose exec web php artisan horizon:install
docker compose exec web php artisan telescope:install
docker compose exec web php artisan migrate
```

### 1.6 Redis configuration

`config/cache.php` default → `redis`.
`config/session.php` driver → `redis`.
`config/queue.php` default → `redis`.

`config/database.php` → ensure `redis` connection configured for cache/queue/session.

`config/horizon.php` — set `environments` for local, configure one supervisor with `default` queue.

### 1.7 Frontend scaffold

```bash
docker compose exec web npm install
docker compose exec web npm install -D \
  @inertiajs/react react@19 react-dom@19 \
  typescript @types/react @types/react-dom \
  tailwindcss@4 \
  eslint @typescript-eslint/parser @typescript-eslint/eslint-plugin \
  prettier eslint-config-prettier \
  vitest @testing-library/react @testing-library/jest-dom jsdom

docker compose exec web npm install \
  react-hook-form zod @hookform/resolvers \
  chart.js react-chartjs-2 \
  konva react-konva \
  @uiw/react-codemirror @codemirror/legacy-modes \
  swagger-ui-react \
  clsx tailwind-merge
```

`tsconfig.json` strict per `CLAUDE.md §7`.

### 1.8 Quality config files

- `pint.json` — `laravel` preset + extras from `CLAUDE.md §4`
- `phpstan.neon` — level max + larastan + Inertia/Eloquent magic compatibility
- `eslint.config.js` — flat config, max-warnings=0
- `.prettierrc`
- `vitest.config.ts` — jsdom env

### 1.9 Smoke test the cli container

Add a temporary scheduled command in `routes/console.php`:

```php
Schedule::call(function () {
    Log::info('cli scheduler heartbeat', ['at' => now()]);
})->everyMinute();
```

After `docker compose up -d`, `docker compose logs cli` should show heartbeat entries every minute. Remove before merging.

### 1.10 CI

`.github/workflows/ci.yml`:

```yaml
name: CI
on: [pull_request, push]

jobs:
  php-quality:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:9.0
        env: { MYSQL_ROOT_PASSWORD: root, MYSQL_DATABASE: webte2_test }
        ports: ["3306:3306"]
        options: --health-cmd "mysqladmin ping" --health-interval 10s
      redis:
        image: redis:7-alpine
        ports: ["6379:6379"]
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.5', extensions: pdo_mysql, mbstring, intl, redis }
      - run: composer install --prefer-dist --no-interaction
      - run: vendor/bin/pint --test
      - run: vendor/bin/phpstan analyse --no-progress
      - run: vendor/bin/pest --parallel

  js-quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '22' }
      - run: npm ci
      - run: npx tsc --noEmit
      - run: npx eslint . --max-warnings=0
      - run: npx prettier --check .
      - run: npx vitest run

  python-quality:
    runs-on: ubuntu-latest
    defaults: { run: { working-directory: docker/octave-bridge } }
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-python@v5
        with: { python-version: '3.13' }
      - run: pip install uv && uv sync
      - run: uv run ruff check .
      - run: uv run ruff format --check .
      - run: uv run mypy --strict .
      - run: uv run pytest
```

Mark all three jobs as required in branch protection.

## Quality gates

- [ ] `docker compose up -d` succeeds on a clean machine
- [ ] `curl http://localhost/` returns the welcome page
- [ ] `docker compose exec cli php artisan horizon:status` returns `running`
- [ ] `docker compose exec web php artisan tinker --execute='Cache::put("k","v"); echo Cache::get("k");'` prints `v` (Redis works)
- [ ] CI green on the first PR

## Risks

| Risk | Mitigation |
|---|---|
| `php:8.5-fpm-alpine` not yet on Docker Hub | Fallback `php:8.5-fpm-bookworm` (apt-based) — adjust the Dockerfile |
| Horizon perms in alpine | Run as `www-data`; ensure `/var/www/html/storage` writable |
| `cli` and `web` both write to the same volume in dev | Expected; Laravel's file locks handle it |
| Redis data loss on restart | `appendonly yes` enabled; acceptable for dev |

## Hand-off to next phase

After this phase, phases 02, 03, 04 can run in parallel.

- Phase 02 (Octave bridge) needs: `octave-bridge` container builds; volume mounted; HTTP reachable from `web`.
- Phase 03 (auth/logging/events) needs: queue working; observers register-able; Sanctum installed.
- Phase 04 (frontend foundation) needs: Inertia + React + Tailwind scaffold.

## Agent brief (copy-paste)

> Read `CLAUDE.md` and `docs/ARCHITECTURE.md` §§ 2–3 and this phase markdown.
>
> Set up the docker-compose stack with web + cli + nginx + mysql + redis + octave-bridge per the phase doc. Use the multi-stage PHP Dockerfile so web and cli share the same image with different CMDs. cli's entrypoint runs `php artisan schedule:work` in background and `php artisan horizon` in the foreground.
>
> Install Laravel 13 with the listed composer/npm dependencies. Configure Redis as cache + queue + session driver. Install Horizon and Telescope.
>
> Configure pint, phpstan (level max + larastan), eslint flat config, prettier, vitest. Set up the GitHub Actions workflow with three jobs (php, js, python) and require them in branch protection.
>
> Smoke test: heartbeat scheduled command should appear in cli logs; Redis cache round-trips a value via tinker; horizon dashboard loads at /horizon (gate not yet enabled).
>
> Run all quality gates locally before opening the PR. Open one PR labelled `phase:01`.
