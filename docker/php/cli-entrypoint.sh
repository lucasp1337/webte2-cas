#!/bin/sh
set -eu

# Scheduler in background, Horizon in foreground (PID 1).
# `exec` so SIGTERM reaches Horizon for graceful shutdown of in-flight jobs.
php artisan schedule:work &
exec php artisan horizon
