#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

# Do not let a caller's production credentials/configuration reach Docker or Laravel.
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
network="scene-memory-gifts-test-${run_id}"
database="scemory_gifts_test_${run_id//-/}"
config_cache="/tmp/scene-memory-gifts-config-${run_id}.php"
project="scene-memory-gifts-${run_id}"
server=''
network_created=false
cleanup() {
    if [[ -n "$server" ]]; then docker rm -f "$server" >/dev/null 2>&1 || true; fi
    if [[ "$network_created" == true ]]; then docker network rm "$network" >/dev/null 2>&1 || true; fi
}
trap cleanup EXIT

docker network create "$network" >/dev/null
network_created=true
server=$(docker run -d --network "$network" --tmpfs /var/lib/mysql \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_ROOT_HOST=% -e "MYSQL_DATABASE=$database" mysql:8.0)
ready=false
for attempt in {1..45}; do
    if docker exec "$server" mysqladmin --protocol=TCP --host=127.0.0.1 ping --silent >/dev/null 2>&1; then ready=true; break; fi
    sleep 1
done
if [[ "$ready" != true ]]; then echo 'Disposable MySQL failed to start.' >&2; exit 1; fi
host=$(docker inspect --format '{{.Config.Hostname}}' "$server")
server_uuid=$(docker exec "$server" mysql --batch --skip-column-names -e 'SELECT @@server_uuid')

docker compose -f docker-compose.yml --env-file /dev/null -p "$project" build app >/dev/null
image=$(docker image inspect --format '{{.Id}}' "${project}-app")
run=(docker run --rm -i --network "$network" --user "$(id -u):$(id -g)"
    --volume "$PWD:/var/www" --workdir /var/www
    -e APP_ENV=testing -e APP_DEBUG=false -e CACHE_STORE=array
    -e "APP_CONFIG_CACHE=$config_cache" -e DB_URL= -e DATABASE_URL=
    -e DB_CONNECTION=mysql -e "DB_HOST=$host" -e DB_PORT=3306
    -e "DB_DATABASE=$database" -e DB_USERNAME=root -e DB_PASSWORD=
    -e "EXPECTED_MYSQL_HOST=$host" -e "EXPECTED_MYSQL_UUID=$server_uuid"
    -e GIFTS_MYSQL_CONCURRENCY=1 --entrypoint php "$image")
verify_target() {
    "${run[@]}" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $db = Illuminate\Support\Facades\DB::connection(); $target = $db->selectOne("SELECT DATABASE() AS db, @@hostname AS hostname, @@server_uuid AS server_uuid"); if ($db->getConfig("host") !== getenv("EXPECTED_MYSQL_HOST") || $target->db !== getenv("DB_DATABASE") || $target->hostname !== getenv("EXPECTED_MYSQL_HOST") || $target->server_uuid !== getenv("EXPECTED_MYSQL_UUID")) { fwrite(STDERR, "Refusing unexpected database target\\n"); exit(1); } echo "Confirmed disposable MySQL database\\n";'
}
verify_target

migrations=(
    0001_01_01_000000_create_users_table.php
    2026_02_08_121349_create_countries_table.php
    2026_02_08_130359_create_cities_table.php
    2026_02_08_131140_create_events_table.php
    2026_02_11_133751_create_events_images_table.php
    2026_02_23_104429_create_comments_table.php
    2026_04_01_140655_create_purchases_table.php
    2026_04_01_141851_create_purchase_items_table.php
    2026_04_07_141334_create_wallets_table.php
    2026_04_11_094754_add_type_to_purchases_table.php
    2026_04_11_124707_add_wallet_credited_to_purchases_table.php
    2026_05_05_131439_create_wallet_transactions_table.php
    2026_06_03_095044_add_column_to_purchase_items_table.php
    2026_07_18_000002_add_paypal_lifecycle_columns_to_purchases_table.php
    2026_07_18_000003_add_source_cart_item_id_to_purchase_items_table.php
    2026_07_19_000001_create_payment_hardening_tables.php
    2026_07_19_000002_harden_existing_payment_tables.php
    2026_09_15_000001_add_total_points_to_users_table.php
    2026_10_18_000001_create_virtual_gift_tables.php
    2026_10_18_000003_add_gift_withdrawal_review_fields.php
    2026_10_20_000001_add_media_storage_and_withdrawal_audit.php
    2026_10_24_000001_add_payment_verification_tracking.php
)
# Legacy migrations disagree about the media table spelling. Keep both empty
# names only in this disposable fixture; never alter the application's tables.
base_paths=()
for migration in "${migrations[@]:0:5}"; do base_paths+=("--path=database/migrations/$migration"); done
"${run[@]}" artisan migrate --force "${base_paths[@]}"
docker exec "$server" mysql "$database" -e 'CREATE TABLE events_images LIKE events_imges;'
docker exec "$server" mysql "$database" -e 'CREATE TRIGGER sync_images_to_imges AFTER INSERT ON events_images FOR EACH ROW INSERT INTO events_imges (id, event_id, price, is_active, created_at, updated_at) VALUES (NEW.id, NEW.event_id, NEW.price, NEW.is_active, NEW.created_at, NEW.updated_at);'
paths=()
for migration in "${migrations[@]:5}"; do paths+=("--path=database/migrations/$migration"); done
"${run[@]}" artisan migrate --force "${paths[@]}"
"${run[@]}" vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
    tests/Feature/Api/Payments/VirtualGiftConcurrencyTest.php
