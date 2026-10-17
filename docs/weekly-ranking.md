# Weekly competition implementation and deployment

Scemory now awards and ranks competition points by business weeks starting Saturday at 00:00 and ending at the next Saturday at 00:00, exclusively. The displayed final day is Friday. The canonical identifier is the Saturday date, never an ISO week number. For example, `2026-10-17` covers October 17–23; the next competition starts October 24.

## Time and accounting

`COMPETITION_TIMEZONE` defaults to `Asia/Riyadh`, because the existing application only establishes UTC storage, not a ranking business timezone. `WeeklyCompetitionPeriod` owns all period calculations, including DST and cross-year dates. Keep this timezone consistent across web processes, workers, and scheduler hosts; changing it after competitions begin requires a deliberate data migration.

MySQL connections explicitly use `+00:00`. Award history and finalization timestamps are UTC; API instants include an offset. `week_start` and `week_end` are business calendar dates, with `week_end` representing Friday. API `ends_at` is the exclusive Saturday boundary in UTC. Daily aggregation retains the application's existing UTC calendar policy; it does not define competition weeks.

`WeeklyPointService` keeps the existing active `point_rules`, eligibility checks, lifetime points, daily totals, history, and unique user/action/reference deduplication. Transactions lock the user, atomically increment weekly totals, and retry deadlocks. Awards take a shared period lock; archiving takes an exclusive period lock. After a lock wait, awards recheck the period and capture one timestamp for attribution. A retry across the weekly boundary cannot award an existing activity again. An internal caller using the deprecated `MonthlyPointService` delegates to weekly accounting.

The existing ordinal tie policy remains: points descending, then user ID ascending. Each user receives a distinct position. Only positive weekly totals qualify; everyone else has zero points and a null rank. Homepage, full leaderboard, profile, and timeline use `WeeklyLeaderboardService`.

## Additive migration

`database/migrations/2026_10_17_000001_create_weekly_competition_tables.php` adds:

- `user_weekly_points`: unique `(user_id, week_start)` and ranking index `(week_start, points, user_id)`.
- `weekly_leaderboards`: immutable finalized points and rank; unique user/week and week/rank. Historical positions survive account deletion; removed accounts expose no profile details.
- `competition_weeks`: canonical dates, timezone, and UTC finalization marker, including empty weeks. The migration records the deployment week so later empty weeks can be caught up.

Existing monthly tables, daily totals, point history, and lifetime totals are preserved. Monthly aggregates are not converted to weekly totals. No backfill was implemented or run: completeness of historical award timestamps has not been established, and historical reconstruction requires separate approval. The deployment week initially contains only awards made after deployment.

## APIs and compatibility

Public leaderboard routes retain the existing throttle policy (60 requests per minute). Profile and timeline retain their existing authentication.

| Route | Response |
| --- | --- |
| `GET /api/leaderboard/weekly-preview` | Current period and top ten `users` |
| `GET /api/leaderboard/weekly?per_page=25&page=1` | Same ordering, paginated; maximum page size 100 |
| `GET /api/leaderboard/weekly-history` | Paginated finalized weeks, newest first |
| `GET /api/leaderboard/weekly-history?week_start=2026-10-17` | Paginated finalized results for that Saturday |
| `GET /api/v1/users/profile` | Current user's `data.weekly_statistics`, alongside existing lifetime fields |
| `GET /api/v1/users/timeline` | Identical `data.weekly_statistics`, independent of timeline date/timezone filters |

Current leaderboard responses contain `week_start`, `week_end`, `timezone`, `ends_at`, and `users`. User entries preserve the project's `id`, `name`, `avatar`, `points`, `rank`, and `badge` shape, and add explicit `weekly_points` and `weekly_rank` aliases. Full results also include `current_page`, `last_page`, and `total`. Private user fields are not serialized. Current results use `Cache-Control: no-store`; ranking queries are indexed live reads and do not use leaderboard or profile caches. Existing profile-cache invalidation on awards remains intact.

Statistics contain `weekly_points`, `weekly_rank`, and the same period metadata. Repository consumers, including shared mobile frontend code, now consume these fields. The previous `monthly_statistics` profile/timeline field is replaced; independently maintained clients must update alongside deployment. No external integration consuming that field was found in the repository.

The old `/api/leaderboard/monthly-preview` endpoint temporarily remains a **read-only legacy endpoint**, preserving its original response and current-calendar-month selection. It emits `Deprecation: true` and a `Link` to its weekly successor. It never labels weekly points as monthly points; its retained monthly aggregates are frozen. There is no second active monthly scoring process. Remove the endpoint and deprecated service adapter after external consumers have migrated; no removal date is imposed without client-owner coordination. Existing monthly archive models and records remain accessible to application code.

History accepts strict `Y-m-d` Saturday identifiers; invalid dates return 422, and current/unfinalized/unknown weeks return 404. Only finalized periods are listed. History entries are never overwritten on retry. Empty finalized weeks return an empty `users` list. There is no new archive UI.

## Rollover and scheduling

Current results switch immediately according to time, independently of archiving. The frontend uses server period metadata, refreshes at `ends_at`, and rechecks after regaining focus. The preview retains ten desktop entries and five mobile entries; the full weekly page uses the existing component's styling with pagination. Weekly strings, states, badges, and accessibility labels are translated for all 13 supported languages. Subscription and timeline date-filter terminology remains unchanged.

`leaderboard:archive-weekly` runs Saturday at `COMPETITION_ARCHIVE_TIME` (default `00:05`) in the competition timezone with Laravel's overlap prevention. It finalizes all pending completed weeks and gaps, including empty weeks, in separate transactions. Database period locks protect manual and concurrent runs as well. Retries skip finalized weeks, and no aggregates or lifetime points are reset or deleted. The old monthly command is unscheduled and now reports that it is retired without changing records.

The host must actually run Laravel's scheduler, for example through its existing cron invoking `php artisan schedule:run` every minute. Scheduling a command in code does not start that host process.

## Deployment

No migration was applied to the application's real MySQL database: the normal application/database containers were stopped and no project `.env` was present. The existing SQLite snapshot was inspected read-only; its point schema matches the migrations.

1. Back up the existing database through the normal deployment process and verify application database connectivity and migration status.
2. Configure the competition timezone and archive time consistently. Deploy frontend/backend together because profile/timeline statistics fields change.
3. Run the additive weekly migration with the existing Compose app service, after reviewing pending migrations. For an otherwise current database:

   ```sh
   docker compose exec app php artisan migrate --force --path=database/migrations/2026_10_17_000001_create_weekly_competition_tables.php
   ```

4. Build/deploy frontend assets, refresh the normal configuration/route caches, and restart long-lived queue workers through the existing deployment process.
5. Confirm `php artisan schedule:list` shows `5 0 * * 6` for the weekly archive and that the host scheduler runs. A delayed `php artisan leaderboard:archive-weekly` safely catches up completed weeks.

No commits or pushes were made. The pre-existing `runs.txt` changes were retained. Existing Docker/MySQL volumes and `.env` were not modified. An initial Compose test run inherited `DB_DATABASE=scene_memory` and wrote the tracked SQLite snapshot; that file was restored byte-for-byte from its initially clean Git version. PHPUnit now forcibly sets both environment and server database variables to SQLite `:memory:` and clears `DB_URL`, with explicit regression assertions; subsequent checks verify the tracked snapshot is unchanged.

## Validation commands and results

Commands ran inside disposable containers using the existing Compose images. Composer dependencies and npm dependencies were installed from their lockfiles without updating those files.

```sh
docker compose run --rm --no-deps --interactive=false -T --entrypoint php app vendor/bin/phpunit \
  tests/Unit/WeeklyCompetitionPeriodTest.php tests/Feature/Points/PointServiceTest.php \
  tests/Feature/Points/WeeklyRolloverTest.php tests/Feature/Api/WeeklyUserStatisticsTest.php \
  tests/Feature/Api/Leaderboard tests/Feature/Api/ProfileTimelineApiTest.php
docker compose run --rm --no-deps --interactive=false -T node npm test -- --reporter=dot
docker compose run --rm --no-deps --interactive=false -T node npm run build
docker compose run --rm --no-deps --interactive=false -T --entrypoint php app artisan route:list --path=leaderboard
docker compose run --rm --no-deps --interactive=false -T --entrypoint php app artisan schedule:list
```

- Focused PHP tests: **30 passed, 198 assertions**, including timeline API tests, Friday/Saturday precision, UTC conversion, DST, cross-year dates, weekly reset, retained lifetime points/history, retry deduplication, rollback, tie ordering, pagination, profile/timeline consistency, missed/empty weeks, immutable archives, deleted-account history, scheduler configuration, validation, and cache isolation. The final run deliberately injected an external `DB_URL` to verify that test configuration still selected in-memory SQLite.
- Frontend: **63 passed across 13 files**, including rendered locales, error/retry, empty state, rollover timing, and full leaderboard pagination.
- Production Vite build: **passed**; existing Sass deprecation, Browserslist-age, and bundle-size warnings remain.
- Route registration and Saturday scheduler listing: **passed**. PHP formatting and Git whitespace checks passed.
- Separate real MySQL concurrency run: **2 passed, 57 assertions**, using independent forked connections for simultaneous distinct/duplicate awards and concurrent archivers with new-week awards. The temporary MySQL instance used `tmpfs`, with no application volume attached. Required user/point/weekly migrations succeeded against MySQL.

The full PHP suite was also run, first without a project key (authentication-related tests consequently failed), then with a temporary process-only test `APP_KEY`. The final run reports **213 tests, 1498 assertions: 205 passed, six errors, two skipped**. The errors are in `EventAiTagsDispatchTest`: `ImageAnalysisService.php:311` scales a fixture to a zero width. These paths were not changed. The two opt-in MySQL tests skip in the normal SQLite suite and pass in the separate MySQL run.

A clean complete MySQL migration attempt encountered the existing `2026_02_22_105431_create_media_requests_table.php` foreign key to absent `events_images` (the earlier migration creates `events_imges`). This blocks a from-scratch MySQL deployment independently of weekly ranking. It was not changed; the focused MySQL check applied the required user/point/weekly migrations directly to the isolated test database.

To rerun the isolated MySQL migration and concurrency checks from WSL, use `bash tests/run-weekly-mysql-concurrency.sh`. It starts a temporary MySQL server on the existing Compose network, applies only required migrations, verifies the UTC session timezone, runs the tests, and stops only that temporary server. Set `WEEKLY_TEST_NETWORK` if your Compose network has a different name.

Alternatively, use a disposable MySQL database whose name begins `scemory_weekly_test`, apply the required migrations using ordinary `migrate --path=...` commands, and supply its connection environment. Then run with `APP_ENV=testing`, `CACHE_STORE=array`, and `WEEKLY_MYSQL_CONCURRENCY=1`:

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Feature/Points/WeeklyConcurrencyTest.php
```

The concurrency suite never resets a database. Use a new disposable database for each run; its committed fixtures intentionally remain in that disposable database until the test instance is stopped.

## Changed-file manifest

Monthly Vue components and ranking tests listed as removed are replaced by their Weekly counterparts. The manifest excludes the user’s pre-existing changes to runs.txt.

| File | Change |
| --- | --- |
| `.env.example` | Modified |
| `app/Console/Commands/ArchiveMonthlyLeaderboard.php` | Modified |
| `app/Console/Commands/ArchiveWeeklyLeaderboard.php` | Added |
| `app/Http/Controllers/api/auth/AuthController.php` | Modified |
| `app/Http/Controllers/api/home/LeaderboardController.php` | Modified |
| `app/Http/Resources/WeeklyLeaderboardResource.php` | Added |
| `app/Models/User.php` | Modified |
| `app/Models/UserWeeklyPoint.php` | Added |
| `app/Models/WeeklyLeaderboard.php` | Added |
| `app/Services/MonthlyLeaderboardService.php` | Modified |
| `app/Services/MonthlyPointService.php` | Modified |
| `app/Services/PointService.php` | Modified |
| `app/Services/ProfileService.php` | Modified |
| `app/Services/WeeklyCompetitionPeriod.php` | Added |
| `app/Services/WeeklyLeaderboardService.php` | Added |
| `app/Services/WeeklyPointService.php` | Added |
| `config/competition.php` | Added |
| `config/database.php` | Modified |
| `database/migrations/2026_10_17_000001_create_weekly_competition_tables.php` | Added |
| `docs/weekly-ranking.md` | Added |
| `phpunit.xml` | Modified |
| `resources/js/app.js` | Modified |
| `resources/js/components/profile/MonthlyStatsCard.vue` | Replaced/renamed |
| `resources/js/components/profile/WeeklyStatsCard.vue` | Added |
| `resources/js/i18n/weeklyCompetition.js` | Added |
| `resources/js/router/index.js` | Modified |
| `resources/js/services/leaderboardService.js` | Modified |
| `resources/js/views/home/WeeklyLeaderboard.vue` | Added |
| `resources/js/views/home/components/MonthlyLeaderboardPreview.vue` | Replaced/renamed |
| `resources/js/views/home/components/WeeklyLeaderboardPreview.vue` | Added |
| `resources/js/views/home/home.vue` | Modified |
| `resources/js/views/home/profile.vue` | Modified |
| `resources/js/views/home/profileTimeline.vue` | Modified |
| `routes/api.php` | Modified |
| `routes/console.php` | Modified |
| `tests/Feature/Api/Leaderboard/MonthlyLeaderboardPreviewTest.php` | Replaced/renamed |
| `tests/Feature/Api/Leaderboard/WeeklyLeaderboardPreviewTest.php` | Added |
| `tests/Feature/Api/MonthlyUserStatisticsTest.php` | Replaced/renamed |
| `tests/Feature/Api/WeeklyUserStatisticsTest.php` | Added |
| `tests/Feature/Points/PointServiceTest.php` | Modified |
| `tests/Feature/Points/WeeklyConcurrencyTest.php` | Added |
| `tests/Feature/Points/WeeklyRolloverTest.php` | Added |
| `tests/Frontend/monthlyLeaderboardPreview.test.js` | Replaced/renamed |
| `tests/Frontend/monthlyProfileStats.test.js` | Replaced/renamed |
| `tests/Frontend/weeklyCompetitionRendering.test.js` | Added |
| `tests/Frontend/weeklyLeaderboardPreview.test.js` | Added |
| `tests/Frontend/weeklyProfileStats.test.js` | Added |
| `tests/Unit/WeeklyCompetitionPeriodTest.php` | Added |
| `tests/run-weekly-mysql-concurrency.sh` | Added |
