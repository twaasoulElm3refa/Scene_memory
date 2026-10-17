#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

# Test data lives only in RAM. No application database or volume is attached.
network="${WEEKLY_TEST_NETWORK:-scene_memory_laravel}"
docker network inspect "$network" >/dev/null
mysql_container=$(docker run --rm -d --network "$network" --tmpfs /var/lib/mysql \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_DATABASE=scemory_weekly_test mysql:8.0)
trap 'docker stop "$mysql_container" >/dev/null' EXIT

ready=false
for attempt in {1..45}; do
    if docker exec "$mysql_container" mysqladmin --protocol=TCP --host=127.0.0.1 ping --silent >/dev/null 2>&1; then
        ready=true
        break
    fi
    sleep 1
done
if [[ "$ready" != true ]]; then
    echo 'Disposable MySQL did not become ready.' >&2
    exit 1
fi
mysql_host=$(docker inspect --format '{{.Config.Hostname}}' "$mysql_container")
run=(docker compose run --rm --no-deps --interactive=false -T
    -e APP_ENV=testing -e CACHE_STORE=array -e COMPETITION_TIMEZONE=Asia/Riyadh
    -e DB_CONNECTION=mysql -e "DB_HOST=$mysql_host" -e DB_DATABASE=scemory_weekly_test
    -e DB_USERNAME=root -e DB_PASSWORD= --entrypoint php app)
migrations=(
    0001_01_01_000000_create_users_table.php
    2026_09_15_000001_add_total_points_to_users_table.php
    2026_09_15_000002_create_point_rules_table.php
    2026_09_15_000003_create_user_daily_points_table.php
    2026_09_15_000004_create_user_points_history_table.php
    2026_09_22_000001_create_user_monthly_points_table.php
    2026_09_22_000002_create_monthly_leaderboards_table.php
    2026_10_17_000001_create_weekly_competition_tables.php
)
paths=()
for migration in "${migrations[@]}"; do
    paths+=("--path=database/migrations/$migration")
done
"${run[@]}" artisan migrate --force "${paths[@]}"
"${run[@]}" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if (Illuminate\Support\Facades\DB::selectOne("SELECT @@session.time_zone AS tz")->tz !== "+00:00") { exit(1); } echo "MySQL session timezone: UTC\n";'
docker compose run --rm --no-deps --interactive=false -T \
    -e APP_ENV=testing -e CACHE_STORE=array -e COMPETITION_TIMEZONE=Asia/Riyadh \
    -e WEEKLY_MYSQL_CONCURRENCY=1 -e DB_CONNECTION=mysql -e "DB_HOST=$mysql_host" \
    -e DB_DATABASE=scemory_weekly_test -e DB_USERNAME=root -e DB_PASSWORD= \
    --entrypoint php app vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
    tests/Feature/Points/WeeklyConcurrencyTest.php
