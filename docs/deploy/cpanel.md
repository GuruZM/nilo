# Deploying Nilo to cPanel shared hosting

Push to `main` → `tests` workflow passes → `deploy` workflow builds vendor +
Vite assets on the runner, rsyncs a release over SSH, migrates, caches, and
atomically swaps `current`. No Node or Composer needed on the server.

## Server layout

```
~/nilo/
  releases/20261005120000-abc1234/   # last 5 kept
  shared/.env
  shared/storage/                    # symlinked into every release
  current -> releases/<latest>       # domain document root = current/public
```

## One-time setup

1. **PHP** — cPanel › MultiPHP Manager: set the domain to PHP 8.3+. Find the CLI
   binary that matches (often `/opt/cpanel/ea-php83/root/usr/bin/php` or
   `/usr/local/bin/php`) — `php -v` in Terminal must print 8.3+.
2. **Database** — cPanel › MySQL Databases: create DB + user, grant ALL.
3. **SSH key** — on your machine: `ssh-keygen -t ed25519 -f nilo_deploy -N ""`.
   cPanel › SSH Access › Import Key › paste `nilo_deploy.pub` › Authorize.
4. **Bootstrap** —
   ```
   scp -P <port> -r deploy <user>@<host>:~/nilo-setup
   ssh -p <port> <user>@<host> 'bash ~/nilo-setup/server-setup.sh ~/nilo <php-bin>'
   ```
   Then edit `~/nilo/shared/.env` (APP_URL, DB_*, MAIL_*, OAuth, DPO).
   The script also lists any missing PHP extensions.
5. **Document root** —
   - Addon/sub-domain: Domains › Manage › document root `nilo/current/public`.
   - Primary domain (stuck on `public_html`):
     `mv ~/public_html ~/public_html.bak && ln -s ~/nilo/current/public ~/public_html`
6. **Cron** — cPanel › Cron Jobs, every minute:
   `cd ~/nilo/current && <php-bin> artisan schedule:run >> /dev/null 2>&1`
   This runs exchange-rate sync, DPO reconcile and drains the mail queue.
7. **SSL** — cPanel › SSL/TLS Status › Run AutoSSL.
8. **GitHub** — repo › Settings › Environments › `production`:

   | Kind     | Name              | Value                                  |
   |----------|-------------------|----------------------------------------|
   | secret   | `SSH_HOST`        | server hostname/IP                     |
   | secret   | `SSH_PORT`        | usually 22 (some hosts use 21098 etc.) |
   | secret   | `SSH_USER`        | cPanel username                        |
   | secret   | `SSH_PRIVATE_KEY` | contents of `nilo_deploy`              |
   | variable | `DEPLOY_PATH`     | `/home/<user>/nilo`                    |
   | variable | `PHP_BIN`         | CLI binary from step 1                 |
   | variable | `PHP_VERSION`     | e.g. `8.3` (build PHP = server PHP)    |
   | variable | `APP_URL`         | `https://…` for the smoke test         |
   | variable | `APP_NAME`        | baked into the Vite build              |

## First deploy

Actions › deploy › Run workflow. Then seed once over SSH:
`cd ~/nilo/current && <php-bin> artisan db:seed --force` (currencies, roles,
super admin — check which seeders are prod-safe first).

## Rollback

```
cd ~/nilo && ls -1t releases
ln -sfn releases/<previous> current.next && mv -Tf current.next current
```
Migrations are not reversed — roll those back by hand if needed.

## Shared-hosting trade-offs

- **SSR off** (`INERTIA_SSR_ENABLED=false`) — no long-running Node process.
- **Queue via cron** — mails can lag up to ~1 min.
- **Symlink + OPcache** — if a deploy seems not to take, the host's realpath
  cache can lag up to ~2 min; MultiPHP INI › `opcache.revalidate_freq` helps.
