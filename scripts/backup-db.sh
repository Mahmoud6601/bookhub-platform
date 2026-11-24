#!/usr/bin/env bash
# BookHub — back up the MySQL database with mysqldump (inside the db container).
# Usage:
#   ./scripts/backup-db.sh            # writes backups/bookapi-YYYYMMDD-HHMMSS.sql.gz
#   DB_DATABASE=bookapi ./scripts/backup-db.sh
# Requires the stack to be running (docker compose up -d) and a root .env
# file (or exported MYSQL_ROOT_PASSWORD / DB_DATABASE) for credentials.
# The dump runs as the db service's root user; the password is passed inside
# the container, never on the host command line.
set -euo pipefail
cd "$(dirname "$0")/.."

# Load root .env if present (Compose does the same for ${...} interpolation).
if [ -f .env ]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

DB_DATABASE="${DB_DATABASE:-bookapi}"
BACKUP_DIR="${BACKUP_DIR:-backups}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
OUT="${BACKUP_DIR}/${DB_DATABASE}-${TIMESTAMP}.sql.gz"

mkdir -p "$BACKUP_DIR"

echo "BookHub: backing up database '${DB_DATABASE}' to ${OUT} ..."
docker compose exec -T db sh -c \
  'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --databases "$MYSQL_DATABASE"' \
  | gzip > "$OUT"

echo "BookHub: backup complete: ${OUT} ($(du -h "$OUT" | cut -f1))"
echo "BookHub: restore with: ./scripts/restore-db.sh ${OUT}"
