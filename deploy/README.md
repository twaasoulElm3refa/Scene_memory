# Scemory deployment runbook (Phase 1 + 2)

Nothing in this directory is applied automatically. Every step below is run by an
operator on the server, in order, and each one can be stopped and rolled back.

**Do not change** Apache/CWP virtual hosts, SSL, domains, PayPal credentials, PayPal
webhook settings, or the reverse-proxy bridge:

- `alimconsultancy.com/__scemory_checkout_webhook`
- `alimconsultancy.com/__scemory_wallet_webhook`

This release does not touch the webhook routes (`/api/v1/paypal/webhook`,
`/api/v1/wallet/webhook`), their signature checks, or their URLs.

## Files

| File | What it is |
|---|---|
| `backup/scemory-backup.sh` | Consistent `mysqldump --single-transaction` plus a `storage/app` archive and `.env` copy, with checksums |
| `php/scemory-production.ini` | OPcache, upload/time limits and safety settings for the web PHP |
| `php-fpm/scemory-pool.conf` | Pool sizing template; size `pm.max_children` from measured worker memory |
| `mysql/scemory.cnf` | InnoDB, locking and slow-log settings |
| `redis/scemory-redis.conf` | `noeviction` (queues and locks must never be evicted), AOF persistence |
| `supervisor/scemory-worker.conf` | Two queue workers with `--max-time` recycling |
| `cron/scemory.cron` | The Laravel scheduler entry |
| `../.env.production.example` | The production `.env` values this release relies on |

## 1. Before the deployment (read-only)

```bash
cd /home/scemory/public_html
git rev-parse HEAD > ~/release-before.txt            # or note the current release folder
php artisan scemory:production-check                 # new command; only reads
php artisan payments:diagnose-webhooks               # existing; prints no secrets
php artisan route:list --path=webhook > ~/webhook-routes-before.txt
BACKUP_DIR=~/backups APP_DIR=$PWD DB_NAME=<DB_DATABASE> bash deploy/backup/scemory-backup.sh
```

Copy the backup off the server before continuing.

## 2. Deploy

No maintenance mode is needed: both migrations are additive. (If you do use
`php artisan down`, PayPal retries webhooks that receive a 503.)

1. Upload the release (code, `public/build`, `public/video/Final_Scemory-web.mp4`,
   `public/video/Final_Scemory-poster.jpg`, `public/images/optimized/*`). Keep `.env`
   and `storage/` as they are.
2. `composer install --no-dev --optimize-autoloader` (no dependency changes in this release).
3. `php artisan migrate --force` runs two migrations:
   - `create_financial_holds_table`, a new table only;
   - `add_read_path_indexes`, which adds `comments(event_id, created_at)` and
     `users(country)`. InnoDB builds these online; existing indexes are not
     touched, and re-running skips indexes that already exist.
4. If the server already caches configuration: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
5. Reload PHP-FPM (or Apache with mod_php) so OPcache picks up the new code.
6. `php artisan queue:restart`, so workers load the new `GenerateMediaThumbnailJob`.

## 3. Verify

```bash
curl -fsS https://scemory.com/up                       # 200 = database and cache reachable
php artisan scemory:production-check                   # every FAIL needs attention
php artisan route:list --path=webhook | diff - ~/webhook-routes-before.txt   # must be identical
php artisan payments:diagnose-webhooks
```

- After the next real PayPal webhook arrives, check that it was processed:
  `select event_type, status, created_at from paypal_webhook_events order by id desc limit 5;`
- Open the homepage once. The leaderboard video loads only after scrolling near it,
  and the tab photos are served as AVIF/WebP (check the browser's Network panel).

## 4. Follow-up steps (each optional and reversible)

| Step | Command | Note |
|---|---|---|
| Thumbnails for existing images | `php artisan media:thumbnails --dry-run`, then `php artisan media:thumbnails --queue` (or `--limit=500` batches off-peak) | Idempotent; until a thumbnail exists the preview is served |
| Move verified legacy public originals out of the webroot | Existing planner first: `php artisan media:security-plan` (inventory), then `--copy --apply --acknowledge-edge-blocking` (copies to the private disk, switches references). Then `php artisan media:legacy-quarantine <manifest basename>` (dry run) and the same with `--execute` | Needs a fresh backup and business sign-off. Purchased originals keep working through the signed download route. `--rollback --references` on the quarantine manifest restores |
| Redis for the cache | Install Redis (see `redis/`), set `CACHE_STORE=redis`, then run `scemory:production-check` | Switching store starts with an empty cache. Never `FLUSHALL` |
| Fail `/up` without tag support | `CACHE_REQUIRE_TAGS=true` | Only once `cache.tags` reports OK |
| Server-side withdrawal fee | `WALLET_WITHDRAWAL_FEE_FIXED_MINOR`, `WALLET_WITHDRAWAL_FEE_BPS` | Default 0/0 (no fee). Set only with business approval |
| PHP, MySQL, Redis, workers | Templates in this directory | Merge into the existing files and change one component at a time |

## 5. Rollback

- **Code:** redeploy the previous release, reload PHP-FPM, `php artisan queue:restart`.
  The previous code ignores the new table, indexes, thumbnails and optimized assets.
- **Indexes:** `php artisan migrate:rollback --step=1` (when it was the last batch)
  drops only the two new indexes and restores the foreign-key index MySQL had replaced.
- **Financial holds table:** keep it. Rolling it back deletes the hold records; export
  the table first (`mysqldump <db> financial_holds`) if it really must go.
- **Media quarantine:** `php artisan media:legacy-quarantine <quarantine-manifest> --rollback --references`
  copies the files back, checks their hashes, and restores the database references.
- **Video and images:** the original `public/video/Final_Scemory.mp4` and the
  `public/images/photo*.png` files are unchanged, and the old code references them.
- **Cache:** set `CACHE_STORE` back to its previous value.
