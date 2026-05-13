#!/usr/bin/env bash
#
# Production deploy script for node30.webte.fei.stuba.sk.
#
# Run ON THE SERVER (not on your laptop). Idempotent — safe to re-run for
# updates; the first run does first-time setup, later runs just `git pull`
# and re-apply migrations / asset builds.
#
# Usage:
#   sudo bash deploy/deploy.sh                   # first run, on the server
#   cd /var/www/webte2-cas && sudo bash deploy/deploy.sh    # subsequent updates
#
# Required: bash, git, docker, docker compose v2, nginx, certbot (with the
# nginx plugin). The school-provided server image already has all of these.

set -euo pipefail

# ---------------------------------------------------------------------------
# Config — edit if your server layout differs
# ---------------------------------------------------------------------------
REPO_URL="https://github.com/lucasp1337/webte2-cas.git"
APP_DIR="/var/www/webte2-cas"
DOMAIN="node30.webte.fei.stuba.sk"
NGINX_SITE_NAME="${DOMAIN}"
LETSENCRYPT_EMAIL=""   # optional: certbot will prompt if blank

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
log()  { printf "\033[1;34m[deploy]\033[0m %s\n" "$*"; }
warn() { printf "\033[1;33m[deploy]\033[0m %s\n" "$*" >&2; }
fail() { printf "\033[1;31m[deploy]\033[0m %s\n" "$*" >&2; exit 1; }

require_cmd() {
    command -v "$1" >/dev/null 2>&1 || fail "Required command not found: $1"
}

require_cmd git
require_cmd docker
require_cmd nginx
docker compose version >/dev/null 2>&1 || fail "docker compose v2 plugin is not installed."

if [[ "$(id -u)" -ne 0 ]]; then
    fail "Run with sudo (this script writes /etc/nginx and uses certbot)."
fi

# ---------------------------------------------------------------------------
# Step 1: clone or pull the repo
# ---------------------------------------------------------------------------
if [[ -d "${APP_DIR}/.git" ]]; then
    log "Repo already present at ${APP_DIR}; pulling latest main."
    git -C "${APP_DIR}" fetch --prune origin
    git -C "${APP_DIR}" checkout main
    git -C "${APP_DIR}" pull --ff-only origin main
else
    log "Cloning ${REPO_URL} into ${APP_DIR}."
    mkdir -p "$(dirname "${APP_DIR}")"
    git clone --branch main "${REPO_URL}" "${APP_DIR}"
fi

cd "${APP_DIR}"

# ---------------------------------------------------------------------------
# Step 2: bootstrap .env from the production template
# ---------------------------------------------------------------------------
if [[ ! -f .env ]]; then
    log "No .env found; copying deploy/env.production -> .env"
    cp deploy/env.production .env
    chmod 600 .env
    warn ""
    warn "============================================================"
    warn "  ACTION REQUIRED"
    warn "  Edit ${APP_DIR}/.env now and replace every CHANGE_ME_*"
    warn "  placeholder with a strong random secret. Then re-run"
    warn "  this script."
    warn "============================================================"
    warn ""
    exit 1
fi

if grep -q "CHANGE_ME_" .env; then
    fail ".env still contains CHANGE_ME_* placeholders. Fill them, then re-run."
fi

# ---------------------------------------------------------------------------
# Step 3: build the images
# ---------------------------------------------------------------------------
# GEOLITE_LICENSE_KEY in .env is read by compose; pass it through to BuildKit
# so the geolite database is baked into the web/cli image.
log "Building docker images (this can take a few minutes on first run)."
GEOLITE_LICENSE_KEY="$(grep -E '^GEOLITE_LICENSE_KEY=' .env | cut -d= -f2- | tr -d '"')" \
    DOCKER_BUILDKIT=1 \
    docker compose build --pull

# ---------------------------------------------------------------------------
# Step 4: bring the stack up
# ---------------------------------------------------------------------------
log "Starting the docker compose stack."
docker compose up -d

log "Waiting for mysql + redis to report healthy."
deadline=$(( $(date +%s) + 120 ))
while :; do
    mysql_state=$(docker compose ps --format json mysql 2>/dev/null | grep -o '"Health":"[^"]*"' | head -n1 | cut -d'"' -f4 || true)
    redis_state=$(docker compose ps --format json redis 2>/dev/null | grep -o '"Health":"[^"]*"' | head -n1 | cut -d'"' -f4 || true)
    if [[ "${mysql_state}" == "healthy" && "${redis_state}" == "healthy" ]]; then
        log "mysql + redis are healthy."
        break
    fi
    if [[ $(date +%s) -ge ${deadline} ]]; then
        warn "Database/cache did not become healthy within 120s. Inspect:"
        warn "  docker compose ps"
        warn "  docker compose logs mysql redis"
        fail "Aborting."
    fi
    sleep 3
done

# ---------------------------------------------------------------------------
# Step 5: Laravel post-deploy tasks
# ---------------------------------------------------------------------------
APP_KEY_LINE="$(grep -E '^APP_KEY=' .env | cut -d= -f2- || true)"
if [[ -z "${APP_KEY_LINE}" || "${APP_KEY_LINE}" == "base64:" ]]; then
    log "Generating APP_KEY."
    docker compose exec -T web php artisan key:generate --force
fi

log "Running database migrations."
docker compose exec -T web php artisan migrate --force

log "Linking storage to public/."
docker compose exec -T web php artisan storage:link || warn "storage:link skipped (already exists)."

log "Optimising config / route / view / event caches."
docker compose exec -T web php artisan optimize

# ---------------------------------------------------------------------------
# Step 6: host nginx + certbot
# ---------------------------------------------------------------------------
SITE_AVAILABLE="/etc/nginx/sites-available/${NGINX_SITE_NAME}"
SITE_ENABLED="/etc/nginx/sites-enabled/${NGINX_SITE_NAME}"

if [[ ! -f "${SITE_AVAILABLE}" ]]; then
    log "Installing host nginx config at ${SITE_AVAILABLE}."
    install -m 0644 deploy/nginx-node30.conf "${SITE_AVAILABLE}"
else
    log "Host nginx config already exists; not overwriting (edit by hand if needed)."
fi

if [[ ! -L "${SITE_ENABLED}" ]]; then
    ln -s "${SITE_AVAILABLE}" "${SITE_ENABLED}"
fi

log "Testing nginx config."
nginx -t

log "Reloading nginx."
systemctl reload nginx

# certbot is only required on the very first deploy. It is safe to re-run
# (it detects existing certs and renews if close to expiry).
if command -v certbot >/dev/null 2>&1; then
    if [[ ! -d "/etc/letsencrypt/live/${DOMAIN}" ]]; then
        log "Requesting Let's Encrypt cert for ${DOMAIN} via certbot --nginx."
        if [[ -n "${LETSENCRYPT_EMAIL}" ]]; then
            certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${LETSENCRYPT_EMAIL}" --redirect
        else
            certbot --nginx -d "${DOMAIN}" --redirect
        fi
    else
        log "Cert for ${DOMAIN} already present; skipping certbot issuance."
    fi
else
    warn "certbot not installed; skipping TLS issuance. Install certbot + python3-certbot-nginx and re-run."
fi

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------
log "Deploy complete. Open https://${DOMAIN}/ in a browser."
log ""
log "Useful commands:"
log "  docker compose ps                          # container status"
log "  docker compose logs -f web cli             # tail app logs"
log "  docker compose exec web php artisan tinker # repl"
log "  docker compose exec web php artisan cas:create-api-key production"
