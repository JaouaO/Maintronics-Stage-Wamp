#!/usr/bin/env bash
set -e

export APP_ENV=${APP_ENV:-production}
export APP_DEBUG=${APP_DEBUG:-false}
export LOG_CHANNEL=stderr
export LOG_STDERR_FORMATTER="Monolog\\Formatter\\LineFormatter"
export TZ=${TZ:-Europe/Paris}
export NIXPACKS_PHP_ROOT_DIR="/app/public"

if [ -z "$APP_KEY" ]; then
  echo "[boot] ERREUR: APP_KEY manquante (Variables Railway)."
  exit 1
fi

echo "[boot] artisan caches"
php artisan package:discover --ansi || true
php artisan config:cache --ansi || true
php artisan route:cache  --ansi || true
php artisan view:cache   --ansi || true
php artisan event:cache  --ansi || true

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache || true

php-fpm -y /assets/php-fpm.conf &
node /assets/scripts/prestart.mjs /assets/nginx.template.conf /nginx.conf
nginx -c /nginx.conf
