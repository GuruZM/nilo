#!/usr/bin/env bash
# One-shot cPanel bootstrap, run from your Mac in the repo root.
#
#   SSH_HOST=server.host.com SSH_USER=cpuser APP_URL=https://nilo.example.com \
#     [SSH_PORT=22] [DOCROOT_DIR=nilo] [APP_DIR=nilo-app] bash deploy/bootstrap-from-mac.sh
#
# DOCROOT_DIR is the folder (relative to the cPanel home) the subdomain already
# serves. The app itself lives in APP_DIR, outside the web root, and
# DOCROOT_DIR becomes a symlink to APP_DIR/current/public — so .env, releases
# and storage are never web-reachable and the subdomain config is untouched.
#
# Uses your existing SSH access (key or password) to: authorise a dedicated
# deploy key, lay out the app dir, create the PostgreSQL DB, fill shared/.env, add
# the scheduler cron, link the docroot, and (if the gh CLI is logged in) load
# the GitHub `production` environment secrets/variables.
set -euo pipefail

: "${SSH_HOST:?set SSH_HOST}" "${SSH_USER:?set SSH_USER}" "${APP_URL:?set APP_URL}"
SSH_PORT="${SSH_PORT:-22}"
DOCROOT_DIR="${DOCROOT_DIR:-nilo}"
APP_DIR="${APP_DIR:-nilo-app}"
[ "$DOCROOT_DIR" != "$APP_DIR" ] || { echo "APP_DIR must differ from DOCROOT_DIR (it would expose .env)" >&2; exit 1; }
REPO="${REPO:-GuruZM/nilo}"
KEY="$HOME/.ssh/nilo_deploy"
APP_URL="${APP_URL%/}"

CM="$HOME/.ssh/cm-nilo-%r@%h:%p"
SSH=(ssh -p "$SSH_PORT" -o ControlMaster=auto -o ControlPath="$CM" -o ControlPersist=10m "$SSH_USER@$SSH_HOST")
SCP=(scp -P "$SSH_PORT" -o ControlPath="$CM")

step() { printf '\n\033[1m==> %s\033[0m\n' "$*"; }

step "Deploy key"
[ -f "$KEY" ] || ssh-keygen -q -t ed25519 -N "" -C "nilo-deploy@github-actions" -f "$KEY"
"${SSH[@]}" 'mkdir -p ~/.ssh && chmod 700 ~/.ssh && touch ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys'
PUB=$(cat "$KEY.pub")
"${SSH[@]}" "grep -qxF '$PUB' ~/.ssh/authorized_keys || echo '$PUB' >> ~/.ssh/authorized_keys"

step "Inspect server"
REMOTE_HOME=$("${SSH[@]}" 'echo $HOME')
APP_ROOT="$REMOTE_HOME/$APP_DIR"
DOCROOT_PATH="$REMOTE_HOME/$DOCROOT_DIR"
PHP_BIN=$("${SSH[@]}" 'for p in /opt/cpanel/ea-php84/root/usr/bin/php /opt/alt/php84/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php /opt/alt/php83/usr/bin/php /usr/local/bin/php php; do command -v "$p" >/dev/null 2>&1 && "$p" -r "exit(PHP_VERSION_ID >= 80200 ? 0 : 1);" 2>/dev/null && { echo "$p"; exit; }; done; echo NONE')
[ "$PHP_BIN" != NONE ] || { echo "No PHP >= 8.2 CLI found. Enable 8.3+ in MultiPHP Manager / Select PHP Version and rerun." >&2; exit 1; }
PHP_VERSION=$("${SSH[@]}" "$PHP_BIN -r 'echo PHP_MAJOR_VERSION.\".\".PHP_MINOR_VERSION;'")
echo "home=$REMOTE_HOME  php=$PHP_BIN ($PHP_VERSION)"

step "Layout + .env"
"${SSH[@]}" 'rm -rf ~/nilo-setup && mkdir -p ~/nilo-setup'
"${SCP[@]}" deploy/server-setup.sh deploy/activate-release.sh deploy/.env.production.example "$SSH_USER@$SSH_HOST:nilo-setup/"
"${SSH[@]}" "bash ~/nilo-setup/server-setup.sh '$APP_ROOT' '$PHP_BIN'"

step "PostgreSQL database"
if "${SSH[@]}" "grep -q '^DB_PASSWORD=.\+' '$APP_ROOT/shared/.env'"; then
  echo "DB_PASSWORD already set in shared/.env — skipping DB creation."
else
  PREFIX=$("${SSH[@]}" "uapi --output=json Postgresql get_restrictions" | sed -n 's/.*"prefix":"\([^"]*\)".*/\1/p')
  DB="${PREFIX}nilo"
  DB_PASS=$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 28)
  "${SSH[@]}" "uapi --output=json Postgresql create_database name='$DB' | grep -q '\"status\":1' || echo 'create_database: may already exist'"
  "${SSH[@]}" "uapi --output=json Postgresql create_user name='$DB' password='$DB_PASS' | grep -q '\"status\":1' || uapi --output=json Postgresql set_password user='$DB' password='$DB_PASS' | grep -q '\"status\":1'"
  "${SSH[@]}" "uapi --output=json Postgresql grant_all_privileges user='$DB' database='$DB' | grep -q '\"status\":1'"
  "${SSH[@]}" "sed -i \
    -e 's|^DB_DATABASE=.*|DB_DATABASE=$DB|' \
    -e 's|^DB_USERNAME=.*|DB_USERNAME=$DB|' \
    -e 's|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|' \
    '$APP_ROOT/shared/.env'"
  echo "Created database + user $DB"
fi
"${SSH[@]}" "sed -i -e 's|^APP_URL=.*|APP_URL=$APP_URL|' '$APP_ROOT/shared/.env'"

step "Scheduler cron"
CRON="* * * * * cd $APP_ROOT/current && $PHP_BIN artisan schedule:run >> /dev/null 2>&1"
"${SSH[@]}" "(crontab -l 2>/dev/null | grep -v '$APP_DIR/current && ' ; echo '$CRON') | crontab -"
"${SSH[@]}" "crontab -l | grep '$APP_DIR'"

step "Document root: ~/$DOCROOT_DIR -> $APP_DIR/current/public"
"${SSH[@]}" "set -e
  D='$DOCROOT_PATH'; T='$APP_ROOT/current/public'
  if [ -L \"\$D\" ]; then
    ln -sfn \"\$T\" \"\$D\"; echo 'docroot symlink updated'
  else
    if [ -e \"\$D\" ]; then
      echo 'Existing contents of '\"\$D\"':'; ls -A \"\$D\" | sed 's/^/  /'
      mv \"\$D\" \"\$D.bak-\$(date +%Y%m%d%H%M%S)\"; echo '(moved to a .bak folder)'
    fi
    ln -s \"\$T\" \"\$D\"; echo 'docroot is now a symlink'
  fi
  ls -ld \"\$D\""

step "Deploy key login check"
ssh -p "$SSH_PORT" -i "$KEY" -o IdentitiesOnly=yes -o BatchMode=yes "$SSH_USER@$SSH_HOST" true && echo "deploy key OK"

step "GitHub production environment"
if command -v gh >/dev/null && gh auth status >/dev/null 2>&1; then
  gh api -X PUT "repos/$REPO/environments/production" >/dev/null
  gh secret set SSH_HOST --env production --repo "$REPO" --body "$SSH_HOST"
  gh secret set SSH_PORT --env production --repo "$REPO" --body "$SSH_PORT"
  gh secret set SSH_USER --env production --repo "$REPO" --body "$SSH_USER"
  gh secret set SSH_PRIVATE_KEY --env production --repo "$REPO" < "$KEY"
  gh variable set DEPLOY_PATH --env production --repo "$REPO" --body "$APP_ROOT"
  gh variable set PHP_BIN --env production --repo "$REPO" --body "$PHP_BIN"
  gh variable set PHP_VERSION --env production --repo "$REPO" --body "$PHP_VERSION"
  gh variable set APP_URL --env production --repo "$REPO" --body "$APP_URL"
  gh variable set APP_NAME --env production --repo "$REPO" --body "Nilo"
  echo "GitHub environment 'production' configured."
else
  cat <<MSG
gh CLI not logged in — add these in GitHub › Settings › Environments › production:
  secrets:   SSH_HOST=$SSH_HOST  SSH_PORT=$SSH_PORT  SSH_USER=$SSH_USER
             SSH_PRIVATE_KEY=(contents of $KEY — run: pbcopy < $KEY)
  variables: DEPLOY_PATH=$APP_ROOT  PHP_BIN=$PHP_BIN  PHP_VERSION=$PHP_VERSION
             APP_URL=$APP_URL  APP_NAME=Nilo
MSG
fi

ssh -o ControlPath="$CM" -O exit "$SSH_USER@$SSH_HOST" 2>/dev/null || true
step "Done. Fill MAIL_*, GOOGLE_*, DPO_* in $APP_ROOT/shared/.env, then: git push origin main"
