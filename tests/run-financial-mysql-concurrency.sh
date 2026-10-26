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
    echo 'Refusing non-local Docker context.' >&2
    exit 1
fi

run_id="$(date +%s)-$$-${RANDOM}"
network="scemory-concurrency-test-${run_id}"
database="scemory_concurrency_test_${run_id//-/}"
server=''
cleanup() {
    if [[ -n "$server" ]]; then docker rm -f "$server" >/dev/null 2>&1 || true; fi
    docker network rm "$network" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker network create "$network" >/dev/null
server=$(docker run -d --network "$network" --tmpfs /var/lib/mysql \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_ROOT_HOST=% -e "MYSQL_DATABASE=$database" mysql:8.0)
ready=false
for attempt in {1..45}; do
    if docker exec "$server" mysqladmin --protocol=TCP --host=127.0.0.1 ping --silent >/dev/null 2>&1; then
        ready=true
        break
    fi
    sleep 1
done
if [[ "$ready" != true ]]; then echo 'Disposable MySQL failed to start.' >&2; exit 1; fi

host=$(docker inspect --format '{{.Config.Hostname}}' "$server")
run=(docker run --rm -i --network "$network" --user "$(id -u):$(id -g)"
    --volume "$PWD:/var/www" --workdir /var/www
    -e APP_ENV=testing -e APP_DEBUG=false
    -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
    -e CACHE_STORE=array -e DB_URL= -e DATABASE_URL=
    -e DB_CONNECTION=mysql -e "DB_HOST=$host" -e DB_PORT=3306
    -e "DB_DATABASE=$database" -e DB_USERNAME=root -e DB_PASSWORD=
    -e FINANCIAL_MYSQL_CONCURRENCY=1 --entrypoint php scene_memory-app:latest)

"${run[@]}" -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $db=Illuminate\Support\Facades\DB::connection(); $actual=$db->selectOne("SELECT DATABASE() db")->db; if ($actual!==getenv("DB_DATABASE") || !str_starts_with($actual,"scemory_concurrency_test_")) {fwrite(STDERR,"Unsafe DB target\n"); exit(1);} echo "Verified disposable database: {$actual}\n";'
base_migrations=(
    0001_01_01_000000_create_users_table.php
    0001_01_01_000001_create_cache_table.php
    0001_01_01_000002_create_jobs_table.php
    2025_05_03_143457_create_personal_access_tokens_table.php
    2026_01_30_133904_create_reset_tokens_table.php
    2026_02_08_121349_create_countries_table.php
    2026_02_08_130359_create_cities_table.php
    2026_02_08_130851_create_categories_table.php
    2026_02_08_131140_create_events_table.php
    2026_02_08_135131_create_tags_table.php
    2026_02_08_135252_create_event__tags_table.php
    2026_02_08_135634_create_user_interactions_table.php
    2026_02_08_140907_create_wishlists_table.php
    2026_02_11_133751_create_events_images_table.php
)
base_paths=()
for migration in "${base_migrations[@]}"; do base_paths+=("--path=database/migrations/$migration"); done
"${run[@]}" artisan migrate --force "${base_paths[@]}"
docker exec "$server" mysql "$database" -e 'CREATE TABLE events_images LIKE events_imges;'
docker exec "$server" mysql "$database" -e 'CREATE TRIGGER sync_images_to_imges AFTER INSERT ON events_images FOR EACH ROW INSERT INTO events_imges (id, event_id, price, is_active, created_at, updated_at) VALUES (NEW.id, NEW.event_id, NEW.price, NEW.is_active, NEW.created_at, NEW.updated_at);'
"${run[@]}" artisan migrate --force
"${run[@]}" vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
    tests/Feature/Api/Payments/FinancialConcurrencyTest.php
