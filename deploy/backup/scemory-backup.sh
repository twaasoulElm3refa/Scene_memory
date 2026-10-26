#!/usr/bin/env bash
# Consistent backup of the Scemory database and private media before a deployment.
# Read-only for the application: it dumps and archives, it never deletes app data.
#
#   BACKUP_DIR=/home/scemory/backups APP_DIR=/home/scemory/public_html DB_NAME=scemory \
#     bash deploy/backup/scemory-backup.sh
#
# Database credentials come from ~/.my.cnf ([mysqldump] user/password) so they never
# appear in the process list. Copy the result off the server; a backup on the same
# disk does not survive a disk failure.
set -euo pipefail

: "${BACKUP_DIR:?Set BACKUP_DIR}"
: "${APP_DIR:?Set APP_DIR}"
: "${DB_NAME:?Set DB_NAME (the DB_DATABASE value from .env)}"
[[ -f "$HOME/.my.cnf" ]] || { echo 'Create ~/.my.cnf with a [mysqldump] section first.' >&2; exit 1; }

stamp="$(date +%Y%m%d-%H%M%S)"
target="$BACKUP_DIR/scemory-$stamp"
mkdir -p "$target"
chmod 700 "$target"

# --single-transaction: a consistent InnoDB snapshot without locking writers.
mysqldump --single-transaction --quick --routines --triggers --events \
    --default-character-set=utf8mb4 "$DB_NAME" | gzip -6 > "$target/database.sql.gz"

# Private media, quarantined legacy files and security manifests. The public
# storage link is archived as a link, not followed.
tar -C "$APP_DIR/storage/app" -czf "$target/storage-app.tar.gz" .
cp "$APP_DIR/.env" "$target/env.backup"
chmod 600 "$target/env.backup"

( cd "$target" && sha256sum database.sql.gz storage-app.tar.gz env.backup > SHA256SUMS )
gzip -t "$target/database.sql.gz"
tar -tzf "$target/storage-app.tar.gz" > /dev/null
echo "Backup written to $target"
du -sh "$target"
