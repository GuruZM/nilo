#!/usr/bin/env bash
# Runs on the cPanel host for every deploy. Wires an uploaded release to the
# shared .env and storage, migrates, warms caches, then swaps `current` over.
#
#   activate-release.sh <app-root> <release-name> [php-binary]
set -euo pipefail

APP_ROOT="$1"
RELEASE="$2"
PHP="${3:-php}"
KEEP=5

RELEASE_DIR="$APP_ROOT/releases/$RELEASE"
SHARED="$APP_ROOT/shared"

cd "$RELEASE_DIR"

[ -f "$SHARED/.env" ] || { echo "Missing $SHARED/.env — run deploy/server-setup.sh first" >&2; exit 1; }

rm -rf storage
ln -s "$SHARED/storage" storage
ln -sfn "$SHARED/.env" .env
mkdir -p bootstrap/cache

"$PHP" artisan package:discover --ansi
"$PHP" artisan storage:link --force
"$PHP" artisan migrate --force
"$PHP" artisan optimize

# Atomic swap: build the new link beside the old one, then rename over it.
ln -sfn "releases/$RELEASE" "$APP_ROOT/current.next"
mv -Tf "$APP_ROOT/current.next" "$APP_ROOT/current"

cd "$APP_ROOT/current"
"$PHP" artisan queue:restart

# Keep the last $KEEP releases for quick rollback.
cd "$APP_ROOT/releases"
ls -1t | tail -n +$((KEEP + 1)) | xargs -r rm -rf

echo "Live: $RELEASE"
