#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

# Prevent shell-provided deployment settings or credentials from reaching Docker/Laravel.
while IFS='=' read -r name value; do
    case "$name" in
        APP_*|DB_*|DATABASE_URL|MYSQL_*|AWS_*|PAYPAL_*|STRIPE_*|REDIS_*|MAIL_*|SENTRY_*|DOCKER_*|COMPOSE_*)
            if [[ -n "$value" ]]; then
                printf 'Refusing inherited environment variable: %s\n' "$name" >&2
                exit 1
            fi
            ;;
    esac
done < <(env)
if [[ -e .env || -e .env.testing ]]; then
    echo 'Refusing to load application dotenv files; remove them from this isolated checkout before running.' >&2
    exit 1
fi
docker_context=$(docker context show)
docker_endpoint=$(docker context inspect "$docker_context" --format '{{.Endpoints.docker.Host}}')
if [[ "$docker_endpoint" != unix://* ]]; then
    echo 'Refusing non-local Docker context; select a local Unix-socket Docker context.' >&2
    exit 1
fi

run_id="$(date +%s)-$$-${RANDOM}"
network="scene-memory-weekly-test-${run_id}"
database="scemory_weekly_test_${run_id//-/}"
config_cache="/tmp/scene-memory-weekly-config-${run_id}.php"
project="scene-memory-weekly-${run_id}"
mysql_container=''
network_created=false
cleanup() {
    if [[ -n "$mysql_container" ]]; then docker rm -f "$mysql_container" >/dev/null 2>&1 || true; fi
    if [[ "$network_created" == true ]]; then docker network rm "$network" >/dev/null 2>&1 || true; fi
}
trap cleanup EXIT

docker network create "$network" >/dev/null
network_created=true
mysql_container=$(docker run -d --network "$network" --tmpfs /var/lib/mysql \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_ROOT_HOST=% -e "MYSQL_DATABASE=$database" mysql:8.0)
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
server_uuid=$(docker exec "$mysql_container" mysql --batch --skip-column-names -e 'SELECT @@server_uuid')
docker compose -f docker-compose.yml --env-file /dev/null -p "$project" build app >/dev/null
image=$(docker image inspect --format '{{.Id}}' "${project}-app")
run=(docker run --rm -i --network "$network" --user "$(id -u):$(id -g)"
    --volume "$PWD:/var/www" --workdir /var/www
    -e APP_ENV=testing -e APP_DEBUG=false -e CACHE_STORE=array -e COMPETITION_TIMEZONE=Asia/Riyadh
    -e "APP_CONFIG_CACHE=$config_cache" -e DB_URL= -e DATABASE_URL=
    -e DB_CONNECTION=mysql -e "DB_HOST=$mysql_host" -e DB_PORT=3306
    -e "DB_DATABASE=$database" -e DB_USERNAME=root -e DB_PASSWORD=
    -e "EXPECTED_MYSQL_HOST=$mysql_host" -e "EXPECTED_MYSQL_UUID=$server_uuid"
    -e WEEKLY_MYSQL_CONCURRENCY=1 --entrypoint php "$image")

# This is deliberately the first application invocation; abort before migration on any mismatch.
"${run[@]}" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $db = Illuminate\Support\Facades\DB::connection(); $target = $db->selectOne("SELECT DATABASE() AS db, @@hostname AS hostname, @@server_uuid AS server_uuid"); if ($db->getConfig("host") !== getenv("EXPECTED_MYSQL_HOST") || $target->db !== getenv("DB_DATABASE") || $target->hostname !== getenv("EXPECTED_MYSQL_HOST") || $target->server_uuid !== getenv("EXPECTED_MYSQL_UUID")) { fwrite(STDERR, "Refusing unexpected database target\\n"); exit(1); } echo "Confirmed disposable MySQL database\\n";'

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
"${run[@]}" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if (Illuminate\Support\Facades\DB::selectOne("SELECT @@session.time_zone AS tz")->tz !== "+00:00") { exit(1); } echo "MySQL session timezone: UTC\\n";'
"${run[@]}" vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
    tests/Feature/Points/WeeklyConcurrencyTest.php
