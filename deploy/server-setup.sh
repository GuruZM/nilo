#!/usr/bin/env bash
# One-time bootstrap on the cPanel account (run from cPanel > Terminal).
#
#   bash server-setup.sh [app-root] [php-binary]
#
# Creates the release layout, the shared storage tree and a production .env
# with a fresh APP_KEY. Edit <app-root>/shared/.env afterwards.
set -euo pipefail

APP_ROOT="${1:-$HOME/nilo-app}"
PHP="${2:-php}"

mkdir -p "$APP_ROOT"/{releases,shared}
mkdir -p "$APP_ROOT"/shared/storage/{app/public,app/private,logs}
mkdir -p "$APP_ROOT"/shared/storage/framework/{cache/data,sessions,views}
chmod -R 775 "$APP_ROOT/shared/storage"

ENV_FILE="$APP_ROOT/shared/.env"
if [ ! -f "$ENV_FILE" ]; then
  if [ -f "$(dirname "$0")/.env.production.example" ]; then
    cp "$(dirname "$0")/.env.production.example" "$ENV_FILE"
  else
    touch "$ENV_FILE"
  fi
  KEY=$("$PHP" -r 'echo "base64:".base64_encode(random_bytes(32));')
  if grep -q '^APP_KEY=' "$ENV_FILE"; then
    sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" "$ENV_FILE"
  else
    echo "APP_KEY=$KEY" >> "$ENV_FILE"
  fi
  chmod 600 "$ENV_FILE"
  echo "Created $ENV_FILE — fill in DB, mail and app URL before the first deploy."
fi

"$PHP" -v | head -1
for ext in bcmath ctype curl dom fileinfo iconv mbstring openssl pdo_mysql tokenizer xml gd zip intl; do
  "$PHP" -m | grep -qi "^$ext$" && echo "  ok  $ext" || echo "  MISSING  $ext  (enable in Select PHP Version / MultiPHP INI)"
done

echo
echo "Next:"
echo "  1. Make the subdomain's folder a symlink: ln -s $APP_ROOT/current/public ~/nilo  (back up the old folder first)"
echo "  2. Add the cron:  * * * * * cd $APP_ROOT/current && $PHP artisan schedule:run >> /dev/null 2>&1"
