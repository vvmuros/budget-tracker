#!/bin/sh
set -e

php artisan migrate --force

# Runs scheduled commands (e.g. exchange-rate:fetch) once a minute in the
# background — no external cron-ping or host-level cron needed, self-hosted
# so the container can just run its own scheduler continuously.
php artisan schedule:work &

exec php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
