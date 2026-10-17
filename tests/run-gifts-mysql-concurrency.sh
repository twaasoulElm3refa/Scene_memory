#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
network="${GIFTS_TEST_NETWORK:-scene_memory_laravel}"
docker network inspect "$network" >/dev/null
server=$(docker run --rm -d --network "$network" --tmpfs /var/lib/mysql \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_DATABASE=scemory_gifts_test mysql:8.0)
trap 'docker stop "$server" >/dev/null' EXIT
ready=false
for attempt in {1..45}; do
    if docker exec "$server" mysqladmin --protocol=TCP --host=127.0.0.1 ping --silent >/dev/null 2>&1; then ready=true; break; fi
    sleep 1
done
if [[ "$ready" != true ]]; then echo 'Disposable MySQL failed to start.' >&2; exit 1; fi
host=$(docker inspect --format '{{.Config.Hostname}}' "$server")
run=(docker compose run --rm --no-deps --interactive=false -T -e APP_ENV=testing -e CACHE_STORE=array
    -e APP_CONFIG_CACHE=/tmp/scemory-gifts-isolated-config.php -e DB_URL=
    -e DB_CONNECTION=mysql -e "DB_HOST=$host" -e DB_DATABASE=scemory_gifts_test -e DB_USERNAME=root -e DB_PASSWORD= --entrypoint php app)
"${run[@]}" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $db = Illuminate\Support\Facades\DB::connection(); if ($db->getConfig("host") !== getenv("DB_HOST") || $db->selectOne("SELECT DATABASE() AS db")->db !== "scemory_gifts_test") { fwrite(STDERR, "Refusing non-test database\n"); exit(1); } echo "Confirmed disposable MySQL database\n";'
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
    2026_07_19_000001_create_payment_hardening_tables.php
    2026_07_19_000002_harden_existing_payment_tables.php
    2026_09_15_000001_add_total_points_to_users_table.php
    2026_10_18_000001_create_virtual_gift_tables.php
)
# Legacy migrations disagree about the media table spelling. Keep both empty
# names only in this disposable fixture; never alter the application's tables.
base_paths=()
for migration in "${migrations[@]:0:5}"; do base_paths+=("--path=database/migrations/$migration"); done
"${run[@]}" artisan migrate --force "${base_paths[@]}"
docker exec "$server" mysql scemory_gifts_test -e 'CREATE TABLE events_images LIKE events_imges;'
paths=()
for migration in "${migrations[@]:5}"; do paths+=("--path=database/migrations/$migration"); done
"${run[@]}" artisan migrate --force "${paths[@]}"
docker compose run --rm --no-deps --interactive=false -T -e APP_ENV=testing -e CACHE_STORE=array \
    -e APP_CONFIG_CACHE=/tmp/scemory-gifts-isolated-config.php -e DB_URL= \
    -e GIFTS_MYSQL_CONCURRENCY=1 -e DB_CONNECTION=mysql -e "DB_HOST=$host" \
    -e DB_DATABASE=scemory_gifts_test -e DB_USERNAME=root -e DB_PASSWORD= \
    --entrypoint php app vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
    tests/Feature/Api/Payments/VirtualGiftConcurrencyTest.php
