#!/bin/sh
# BookHub API entrypoint.
# 1. Waits for MySQL to accept connections (retries, never crashes on first try).
# 2. Ensures a .env file exists and an APP_KEY is set (generates one if missing).
# 3. Runs migrations (only after the DB is reachable) and warms/clears caches.
# 4. Hands over to Apache (or whatever CMD was given).
set -e

cd /var/www

# --- 1. Wait for the database -------------------------------------------
# DB_* come from the compose environment (see docker-compose.yml).
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:-app}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_DATABASE="${DB_DATABASE:-bookapi}"

echo "BookHub: waiting for MySQL at ${DB_HOST}:${DB_PORT} ..."
i=0
until php -r '
  $host = getenv("DB_HOST") ?: "db";
  $port = getenv("DB_PORT") ?: "3306";
  $user = getenv("DB_USERNAME") ?: "app";
  $pass = getenv("DB_PASSWORD") ?: "";
  $db   = getenv("DB_DATABASE") ?: "bookapi";
  try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass, [PDO::ATTR_TIMEOUT => 2]);
    $pdo->query("SELECT 1");
    exit(0);
  } catch (Throwable $e) {
    exit(1);
  }
'; do
  i=$((i + 1))
  if [ "$i" -ge 30 ]; then
    echo "BookHub: ERROR: MySQL did not become ready after 60s at ${DB_HOST}:${DB_PORT}." >&2
    echo "BookHub: check that the db service is healthy (docker compose ps / docker compose logs db)." >&2
    exit 1
  fi
  echo "BookHub: MySQL not ready yet (attempt $i/30), retrying in 2s..."
  sleep 2
done
echo "BookHub: MySQL is ready."

# --- 2. Application key ---------------------------------------------------
# Laravel reads real environment variables first, so an APP_KEY passed via
# compose environment is already in effect. Only generate one into .env
# when neither the environment nor the .env file provides one.
if [ ! -f .env ]; then
  cp .env.example .env
  echo "BookHub: created .env from .env.example."
fi
if [ -z "${APP_KEY:-}" ] && grep -q '^APP_KEY=$' .env; then
  echo "BookHub: APP_KEY is empty — generating one."
  php artisan key:generate --force
fi

# --- 3. Migrations & caches ------------------------------------------------
echo "BookHub: running migrations..."
php artisan migrate --force

if [ "${APP_ENV:-production}" = "production" ]; then
  echo "BookHub: caching configuration and routes..."
  php artisan config:cache
  php artisan route:cache
else
  php artisan config:clear >/dev/null 2>&1 || true
  php artisan route:clear >/dev/null 2>&1 || true
fi

# --- 4. Start the server ---------------------------------------------------
echo "BookHub: starting $*"
exec "$@"
