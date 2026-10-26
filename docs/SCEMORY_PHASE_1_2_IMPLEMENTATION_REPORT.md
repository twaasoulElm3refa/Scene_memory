# Scemory — Phase 1 + 2 implementation report

Local implementation only. Nothing was committed, pushed, merged or deployed, and no
production server, database, Redis, Apache/CWP, PayPal setting or reverse proxy was
accessed. The PayPal webhook routes, their signature checks and the
`alimconsultancy.com` bridge are untouched.

Based on `docs/SCEMORY_PRODUCTION_CAPACITY_AUDIT.md`. Every number below was
measured locally (PHP 8.3, SQLite for the test suite, MariaDB 10.4 for the race and
index checks, synthetic data). **None of them is a production capacity claim**:
capacity needs a staging load test (audit §9).

---

## 1. Security issues fixed

### Purchased and original media

| Issue | Fix |
|---|---|
| Downloads and purchased originals were sent as `Cache-Control: no-store, public` (Symfony's `BinaryFileResponse` adds `public`), so shared caches could keep a paid file | New `PrivateFileResponse`: `private, no-store`, `nosniff`. Used by `/download/{media}`, the signed purchased-original route and the moderation review route |
| Verified copies of protected legacy originals stayed in the webroot (`public/storage`) after `media:security-plan --apply` | New `media:legacy-quarantine` command. **Dry run by default.** It checks that references still match the manifest, that the private copy's SHA-256 matches, and that no other public media shares the path. Then it moves the public copy (copy, verify, delete) to the private `legacy-quarantine` disk and writes a manifest. `--rollback [--references]` restores files and references with hash checks. Not run on any real data |
| Previews were `no-store` even for public content, and never revalidated | `MediaFileResponse`: published media gets `public, max-age=3600` plus `Last-Modified` (304 support). Unpublished, signed or owner views stay `private, no-store`. The global middleware still forces `private, no-store` on every authenticated API response |

Signed purchased-original links are covered by tests: they cannot be retargeted to
another user, they expire, and they are refused for an inactive buyer.

### Refunds, reversals and disputes

| Issue | Fix |
|---|---|
| A refund, reversal or dispute only flagged the payment. The credited money stayed withdrawable | New `financial_holds` table. Holds are placed under the payment lock, with wallets locked in ascending id order: a top-up holds the deposit; a purchase holds each seller's credits from that payment; plan payments are flagged for review only. Holds keep money out of **withdrawals** and never rewrite balances or ledger history |
| Duplicate and cross-endpoint deliveries | Holds are unique per payment, wallet, reason and provider reference. Repeats return the existing hold, and partial refunds accumulate but are capped at what the payment credited |
| Dispute outcome | `RESOLVED_SELLER_FAVOUR` releases that dispute's holds. Any other outcome stays held for an administrator |
| Late `PENDING` or `DENIED` events could downgrade a completed payment | `PayPalServices::applyProviderState` re-reads the payment under a lock and never changes `completed` (nor `failed` for PENDING). Used by both purchase and wallet webhooks |
| Requesters chose their own withdrawal fee | The fee is now `WALLET_WITHDRAWAL_FEE_FIXED_MINOR` + `WALLET_WITHDRAWAL_FEE_BPS` (default 0/0, the same result as the form's default "0"). Only administrators can set a fee explicitly. Malformed fee input is still rejected with 422 |
| Available balance ignored disputed money | `assertWithdrawable` subtracts active holds, using a **locking read** so a hold committed while the request waited for the wallet lock is never missed. This was found while writing the MySQL tests |
| Ledger duplicate key | `WalletLedgerService` inserts first and treats a unique-key violation as "already posted": the balance moves once and there are no gap-lock deadlocks |
| Order wallets locked in varying order | `PaymentFinalizer` locks seller and buyer wallets in ascending id order before posting |
| Admin review | `GET /api/v1/admin/financial-holds`, `POST /api/v1/admin/financial-holds/{id}/release` (a note is required; logged; `private, no-store`) |

### Cache reliability and private data

- `Cache::tags()` on a store without tag support threw, which turned 26 controllers
  into HTTP 500s. Now (`TagSafeRepository`) tagged reads are computed uncached, and
  tagged writes and flushes do nothing, so stale data is never served. One warning
  is logged per store.
- `GET /up` is now a readiness check: it returns 500 if the database or cache cannot
  serve. With `CACHE_REQUIRE_TAGS=true` it also returns 500 without tag support. The
  messages are generic.
- Key-context bugs fixed: country pages and category details were cached without
  the locale (the first visitor's language was served to everyone). Comment pages
  now normalise the page number. `/events/daily` now has the date in its key.
- No per-user data is cached under shared keys. The single-event and comment caches
  already attach the viewer's reactions after a cache hit; that was verified and left
  unchanged.

### Supply chain (frontend)

- The mobile shell loaded `maplibre-gl@latest` JS and CSS (unpinned) and an old RTL
  plugin from CDNs, duplicating the bundled, pinned copy. Both are removed.
- The unused Alpine.js v2 CDN script is removed from web and mobile; nothing uses
  `x-*` or `Alpine`.

## 2. Performance improvements

1. **Media N+1 removed.** Serialising an image lazily loaded its event (once per
   image, also on cache hits). Now events hand themselves to their loaded media
   (`MediaParents::adopt`), and media collections load missing events in one query.
   The `events` relation is hidden: it was already stripped from the output.
2. **`firstImage` bounded.** `hasOne` without a limit eager-loaded every image of
   every listed event. It now uses `oldestOfMany()`: the same image (lowest id), one
   row per event.
3. **Profile timeline.** The per-event cover lookups are gone, and each section's
   count and latest date come from one aggregate query.
4. **Indexes** (additive, idempotent, reversible): `comments(event_id, created_at)`
   and `users(country)`.
5. **Thumbnails.** A queued, idempotent `GenerateMediaThumbnailJob` (also dispatched
   after each image upload) and a `media:thumbnails` backfill build a WebP (JPEG if
   WebP is unavailable) of at most 1280 px. It is made from exactly the file the
   preview endpoint serves, so the watermark is kept and a protected original is
   never used. Files go on the private media disk; no S3 is needed.
   - `GET /api/v1/media/{id}/thumbnail` has the preview's access rules. It serves
     the preview until the thumbnail exists.
   - Media JSON gains an additive `thumbnail_url`. Discovery cards already preferred
     `thumbnail_url` (falling back to the preview on error), so they use it with no
     frontend change.
6. **Scoped cache invalidation.** A comment, reply or reaction now flushes only its
   event's comment pages, not every event's. Cart and wishlist changes flush only
   that user, not every user's cart.
7. **Homepage video.** A web encode at the same 1920×1080, 30 fps picture, without
   the never-audible audio track, with the index first. It loads only near the
   viewport, pauses off screen and in hidden tabs, and shows a first-frame poster.
   `autoplay`, `muted`, `loop`, `playsinline` and the layout are unchanged.
8. **Homepage photos.** AVIF and WebP at 960, 1600 and 2624 px through
   `<picture>`/`srcset`, with the PNG kept as the fallback, plus `loading="lazy"` and
   `decoding="async"`. The existing CSS still targets the `<img>`.
9. **Locales.** English (the fallback) stays in the main bundle. The other 12
   languages are separate chunks, loaded before the first render and before any
   navigation that needs them, so there is no flash of untranslated text. All 13 stay
   registered, so the language menu and hreflang links are unchanged. Language
   switching was already a full reload.
10. **Docker image.** OPcache is enabled, and the compose worker has
    `restart: unless-stopped` and `--max-time=3600`.

## 3. Exact modified files

**Modified (39):**

- Docker: `Dockerfile`, `docker-compose.yml`
- Controllers:
  - `app/Http/Controllers/api/admin/EventAdminController.php`
  - `app/Http/Controllers/api/auth/DownloadController.php`
  - `app/Http/Controllers/api/home/{CartController, CategoryController, CommentController, CommentInteractionController, CommentReplyController, CountriesController, EventController, MediaDeliveryController, WhisListController}.php`
- Resources: `app/Http/Resources/ProfileTimeline/{TimelineEventResource, TimelineEventSummaryResource}.php`
- Jobs: `app/Jobs/ProcessEventImageJob.php`
- Models: `app/Models/{Events, EventsImges}.php`
- Providers: `app/Providers/AppServiceProvider.php`
- Repositories: `app/Repositories/Eloquent/Auth/ProfileRepository.php`, `app/Repositories/Eloquent/Events/EventRepository.php`
- Services: `app/Services/{MediaAccessService, PayPalReversalReview, PayPalServices, PayPalWalletServices, PaymentFinalizer, WalletLedgerService, WithdrawalReservationService}.php`
- Config: `config/{cache, filesystems, media_security, wallet}.php`
- Frontend:
  - `mobile/index.html`
  - `resources/js/app.js`
  - `resources/js/views/home/components/{ScemoryExperienceTabs, WeeklyLeaderboardPreview}.vue`
  - `resources/views/index.blade.php`
- Routes: `routes/api.php`
- Tests: `tests/Feature/Api/Payments/PayPalWebhookTest.php`. One expectation changed
  on purpose: a wallet refund now answers `ok` and places a hold instead of
  `ignored`; the balance is still not undone.

**New:**

- Commands: `app/Console/Commands/{BackfillMediaThumbnails, ProductionReadinessCheck, QuarantineLegacyMedia}.php`
- Controllers: `app/Http/Controllers/api/admin/FinancialHoldController.php`
- Jobs: `app/Jobs/GenerateMediaThumbnailJob.php`
- Models: `app/Models/FinancialHold.php`, `app/Models/Collections/MediaCollection.php`
- Services: `app/Services/MediaThumbnailService.php`
- Support:
  - `app/Support/{CommentCache, MediaFileResponse, MediaParents, PrivateFileResponse}.php`
  - `app/Support/Cache/{TagSafeCacheManager, TagSafeRepository}.php`
  - `app/Support/Health/ApplicationReadiness.php`
- Migrations: `database/migrations/2026_10_27_000001_create_financial_holds_table.php`, `2026_10_27_000002_add_read_path_indexes.php`
- Deployment and config: `deploy/` (README runbook, backup script, PHP/FPM/MySQL/Redis/Supervisor/cron templates), `docker/php/opcache.ini`, `.env.production.example`
- Scripts: `scripts/optimize-home-images.php`, `scripts/video/{transcode-windows.ps1, faststart.py, encode-ffmpeg.sh}`
- Assets:
  - `public/video/Final_Scemory-web.mp4` (26.6 MB)
  - `public/video/Final_Scemory-poster.jpg` (141 KB)
  - `public/images/optimized/*` (18 files, 2.1 MB)
- Tests:
  - `tests/Feature/Api/Payments/{PaymentReversalHoldsTest, FinancialConcurrencyTest}.php`
  - `tests/Feature/Cache/CacheReliabilityTest.php`
  - `tests/Feature/Media/MediaThumbnailTest.php`
  - `tests/Feature/Performance/MediaQueryCountTest.php`
  - `tests/Feature/Security/LegacyMediaQuarantineTest.php`
  - `tests/Frontend/homeMediaLoading.test.js`
  - `tests/Support/financial-concurrency-worker.php`
  - `tests/run-financial-mysql-concurrency.sh` (set the executable bit on Linux)

The originals `public/video/Final_Scemory.mp4` and `public/images/photo*.png` are
unchanged; the video's hash matches HEAD.

## 4. Migrations and backfills

| Item | Type | Data impact | Verified |
|---|---|---|---|
| `create_financial_holds_table` | New table | None | Up, down and up again on SQLite and MariaDB 10.4 |
| `add_read_path_indexes` | 2 non-unique indexes, skipped if present | None | Idempotent re-run. Rollback restores the foreign-key index MySQL drops once a composite index covers it (rollback initially failed with error 1553, which this fixes). Up/down/up twice on MariaDB |
| `media:thumbnails` | Backfill, `--dry-run`, `--queue`, `--limit`, `--from-id` | Writes new files only | Tests: counts, re-run skips |
| `media:legacy-quarantine` | Moves public copies, dry run by default | Reversible; manifests and hashes | Tests; **not run on real data** |

No column type changes: VARCHAR `is_active`/`start_date` and the coordinates are
untouched (§11). No unique indexes were added, because existing duplicates could
make them fail. A read-only duplicate check is recommended first.

## 5. Redis, PHP and MySQL checklist

`php artisan scemory:production-check [--json]` reports (read-only, no secrets):
environment, debug, key, URL, cache store, round trip, tags, Redis client, database
version, isolation level, lock wait and connections, required tables, failed jobs,
active holds, queue, session, mail, media root outside `public/`, quarantine disk,
ffmpeg/ffprobe, PHP version, extensions, OPcache and limits. It exits 1 on any FAIL.
PayPal checks remain in `payments:diagnose-webhooks`.

| Area | Recommendation (templates in `deploy/`) |
|---|---|
| Redis | `CACHE_STORE=redis`, queue and session on Redis, `maxmemory-policy noeviction` (or a separate cache instance with `allkeys-lru`), AOF on, password, never `FLUSHALL` |
| PHP | OPcache on (`validate_timestamps=0` and reload on deploy), 100M upload/post, 300 s execution, matching proxy timeout, `expose_php=Off` |
| PHP-FPM | Size `pm.max_children` from measured worker RSS; `pm.max_requests=1000`, slow log 5 s |
| MySQL | Buffer pool sized to RAM, `innodb_flush_log_at_trx_commit=1` (payments), REPEATABLE-READ, lock wait 15 s, print deadlocks, slow log 0.5 s |
| Workers | At least 2 Supervisor workers; `REDIS_QUEUE_RETRY_AFTER` (1260) must stay above the video timeout (1200) |

## 6. Before and after measurements

| What | Before | After | How |
|---|---|---|---|
| Single event, 5 / 50 / 200 images, cache miss | 15 / 60 / 210 queries | 9 / 9 / 9 | Probe, SQLite, Redis-like serialising cache |
| Same, cache hit | 9 / 54 / 204 | 3 / 3 / 3 | Same |
| Home list (8 events × 40 images), image rows loaded | 320 | 8 | Same |
| Profile timeline | 62 queries (19 per-item lookups) | 36 (0) | Probe |
| JSON output of events list, trending, single, daily (guest, owner, admin, stranger; 20 responses) | — | Identical apart from the new `thumbnail_url` key | A/B snapshot against the HEAD models |
| Comment page query (100k comments) | 0.864 ms median, filesort | 0.154 ms | MariaDB 10.4, 300 runs |
| Users per country (20k users) | 7.06 ms, full scan | 0.54 ms | Same |
| Homepage video | 88.66 MB, 19.2 Mbps + AAC, `moov` at end, loaded on every visit | 26.63 MB (−70%), 5.8 Mbps, no audio, `moov` first, loaded near the viewport | Aligned-frame PSNR against the original: 43.6–49.7 dB (mean 45.4) at 7 timestamps. The encode starts one frame (33 ms) later; this is invisible in a muted loop |
| Tab photos (each) | 6.2 / 7.4 / 7.1 MB PNG | Full-width AVIF 151 / 220 / 187 KB (WebP 191 / 265 / 237); 1600 px AVIF 71–84 KB | PSNR 39.7–43.1 dB |
| Card image (2624×1632 photo) | Preview JPEG 219–249 KB | Thumbnail WebP 51–60 KB (≈24%) | About 0.5 s per image with GD |
| Main JS bundle | 2,185.95 kB (712.33 kB gzip) | 1,111.09 kB (372.76 kB gzip) + one locale chunk (27–32 kB gzip) | Vite build |

## 7. Tests

| Suite | Baseline | After |
|---|---|---|
| PHPUnit (full) | 504: 468 pass, 26 fail/error, 10 skipped | **544: 503 pass, the same 26 fail/error, 15 skipped** |
| Vitest (full) | 272: 265 pass, 7 fail | **278: 271 pass, the same 7 fail** |
| `npm run build` | OK | OK |
| MySQL race tests (`FinancialConcurrencyTest`, proc_open workers, local MariaDB scratch DB) | — | 5/5 pass on 5 runs. A mutation (balance check removed) is caught |

All failures are pre-existing and environment-related, the identical set before and
after:

- `EventCreationRequirementsTest` (12) and `UploadProcessingTest` (2): no ffmpeg
  locally.
- `PayPalWebhookVerifierTest` (8): OpenSSL CSR on Windows.
- `MediaProtectionTest` (3): Windows symlinks.
- `GoogleWebAuthTest` (1).
- Vitest: `footerRtlRendering` (3) and `policies` (4).

The 5 new skips are the opt-in MySQL race tests (`FINANCIAL_MYSQL_CONCURRENCY=1`,
database name must start with `scemory_concurrency_test`). The existing plan, gift
and weekly race tests stay skipped as before (pcntl/Docker).

New coverage:

- Media: legacy quarantine and rollback; signed and expiring private originals.
- Holds: refund, duplicate and cross-endpoint delivery, partial refunds, seller
  proportional holds, dispute outcomes, plan payments review-only, stale
  PENDING/DENIED.
- Withdrawals: server fee, admin-only hold API, ledger idempotency.
- Cache: the no-500 fallback, `/up` readiness, per-event, per-user and per-locale
  scoping, the production-check command not printing secrets.
- Thumbnails: watermark source, no upscaling, protected originals, caching headers
  and 304, JSON links, backfill.
- Query counts: constant in media count and timeline size.
- Frontend: video attributes, lazy loading and pausing; `<picture>` sources in all
  13 locales.

**Visual verification limitation:** no browser screenshots were taken. The local
preview server could not run against this machine's environment (no `.env`), and
screenshot automation was dropped on request. Appearance was protected instead by:

- jsdom tests on the rendered DOM: attributes, classes, the existing CSS selectors
  still matching;
- reading the stylesheets: the `<picture>` wrapper is unstyled and the `<img>` is
  still absolutely positioned against `.experience-media`;
- byte-identical API output;
- objective image and video PSNR.

A manual look at the homepage (LTR and RTL, desktop and phone) before deploying is
recommended.

## 8. Risks and blockers

- **Holds block withdrawals only.** Held money can still be spent inside the platform
  (wallet purchases, gifts). The hold and admin review flag it, but nothing stops
  the spending.
- **Plan subscription refunds are only flagged**, because plan revenue is paid out
  per redemption and no wallet credit comes straight from the payment.
- **Withdrawal form:** the UI still shows a free "Fee" field (UI unchanged on
  purpose). For requesters its value is now ignored, so a positive self-entered fee
  becomes the server fee (0 by default).
- **Public caching of previews and thumbnails:** after a takedown, viewers or caches
  that already fetched the file may keep it for up to `MEDIA_PUBLIC_MAX_AGE` (default
  3600 s; set 0 to disable). The endpoint itself rechecks publication on every
  request.
- **Tag fallback:** if production really runs the file or database store,
  tagged pages stop failing but are uncached until Redis is configured. The CPU and
  database load will be visible.
- **Lazy locales:** if a locale chunk fails to download, English text is shown
  (logged).
- **Mobile app:** the `mobile/index.html` changes and the new assets need a mobile
  rebuild and release.
- **Video encode:** made with Windows' Media Foundation encoder. The bundled ffmpeg
  found on this machine (inside Free Download Manager) is unsigned, so it was not
  used. `encode-ffmpeg.sh` is the Linux path.
- **Docker MySQL runner:** `tests/run-financial-mysql-concurrency.sh` is not executed
  here (no Docker). It was validated through the equivalent local MariaDB run.
- **Production unknowns** (from the audit): the cache store, worker setup, PHP-FPM
  pool and MySQL version are unverified.

## 9. Safe deployment sequence

Full commands are in `deploy/README.md`.

1. **Read-only checks:** `scemory:production-check`, `payments:diagnose-webhooks`,
   save the webhook route list.
2. **Backup:** `deploy/backup/scemory-backup.sh` (consistent dump plus
   `storage/app`), then copy it off the server.
3. **Deploy code and assets** (no new Composer dependencies), then
   `php artisan migrate --force` for the two additive migrations. No maintenance mode
   is needed.
4. **Reload:** cache config/routes/views if already used, reload PHP-FPM,
   `queue:restart`.
5. **Verify:** `/up`, `production-check`, and that the webhook route list is
   identical. Watch the next PayPal webhook reach `processed`.
6. **Follow-ups**, one at a time and each with sign-off:
   - thumbnail backfill (`--dry-run` then `--queue`);
   - Redis for the cache, then `CACHE_REQUIRE_TAGS=true`;
   - the legacy media quarantine;
   - PHP, MySQL and Redis tuning from the templates;
   - Supervisor workers;
   - a withdrawal fee, only if the business approves one.

## 10. Rollback

- **Code:** redeploy the previous release, reload PHP-FPM, `queue:restart`. The old
  code ignores the new table, indexes and files.
- **Indexes:** `migrate:rollback --step=1` drops only the two new indexes and
  restores the original foreign-key index.
- **Holds table:** keep it. If it must be removed, export it first: rollback deletes
  the hold records.
- **Quarantine:** `media:legacy-quarantine <quarantine manifest> --rollback --references`.
- **Assets:** the original video and PNGs are still in place.
- **Cache:** restore the previous `CACHE_STORE`.

## 11. Not completed, and why

| Item | Why |
|---|---|
| Changing column types (`is_active`, `start_date` VARCHAR; coordinates) and the composite listing indexes that depend on them | Needs proof of production data compatibility (`SELECT is_active, COUNT(*) … GROUP BY 1`) and a live data migration. Not allowed without that |
| Unique indexes (`carts.user_id`, `user_credit_grants.purchase_id`, likes) | Existing duplicates would fail the migration; run a read-only duplicate check first |
| Event-tag flush per processed image (audit K2) | Listings legitimately change with each image. Debouncing through the single shared queue could leave lists stale for hours behind video jobs. Revisit once workers are split |
| Duplicate Bootstrap JS (bundle + ESM), Tailwind loaded twice, duplicate bootstrap-icons CDN | Removing either copy can change interaction or icon rendering, which can't be checked without visual testing |
| Font Awesome and toastr CDNs | In use (`window.toastr` and `fa-*` in 4 views). Note: toastr's CDN build expects jQuery, which is not loaded; this is pre-existing and was not changed |
| Video posters and thumbnails for user videos | Needs ffmpeg in production; images only for now |
| Gift wallet lock-order alignment | Gift flows have their own concurrency suite (pcntl/Docker), which can't run here; left unchanged rather than altered unverified |
| Shared throttle key (`throttle:N,1` per user/IP across routes) | Changing limits changes behaviour for clients; needs product input |
| Production load test and capacity numbers | Requires staging and permission (audit §9) |
| Browser screenshot comparisons | See §7 |

## Readiness verdict

**Ready for a staged deployment, not yet verified in production.**

- The Phase 1 security and financial fixes are implemented and covered by tests,
  including the MySQL race tests. The migrations are additive and reversible.
- The Phase 2 optimizations are measured and output-preserving.
- No new test failures.

Before production:

1. Take a backup.
2. Run `scemory:production-check` and fix any FAIL, above all a cache store without
   tag support.
3. Look at the homepage visually.
4. Do the quarantine and Redis steps separately, with sign-off.

Make capacity claims only after a staging load test.
