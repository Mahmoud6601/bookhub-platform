#!/usr/bin/env bash
# BookHub — restore the MySQL database from a backup created by backup-db.sh.
# Usage:
#   ./scripts/restore-db.sh backups/bookapi-20261124-150000.sql.gz
#   ./scripts/restore-db.sh backups/bookapi-20261124-150000.sql   # plain .sql also works
# Requires the stack to be running (docker compose up -d).
# WARNING: this OVERWRITES the current database. The script asks for
# confirmation unless RESTORE_FORCE=1 is set.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -f .env ]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

BACKUP_FILE="${1:-}"
if [ -z "$BACKUP_FILE" ] || [ ! -f "$BACKUP_FILE" ]; then
  echo "Usage: $0 <backup-file.sql[.gz]>" >&2
  echo "Example: $0 backups/bookapi-20261124-150000.sql.gz" >&2
  exit 1
fi

DB_DATABASE="${DB_DATABASE:-bookapi}"

if [ "${RESTORE_FORCE:-0}" != "1" ]; then
  read -r -p "Restore '${BACKUP_FILE}' into database '${DB_DATABASE}'? This OVERWRITES current data. [y/N] " answer
  case "$answer" in
    y|Y|yes|YES) ;;
    *) echo "Aborted."; exit 1 ;;
  esac
fi

echo "BookHub: restoring '${BACKUP_FILE}' into database '${DB_DATABASE}' ..."
if [[ "$BACKUP_FILE" == *.gz ]]; then
  gunzip -c "$BACKUP_FILE" | docker compose exec -T db sh -c \
    'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
else
  docker compose exec -T db sh -c \
    'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < "$BACKUP_FILE"
fi

echo "BookHub: restore complete."
