-- BookHub — MySQL init script.
-- Runs ONCE, only when the mysql-data volume is first created (the official
-- mysql image executes *.sql files in /docker-entrypoint-initdb.d).
-- MYSQL_DATABASE already creates the database, so this script only makes the
-- charset/collation explicit and idempotent.
CREATE DATABASE IF NOT EXISTS bookapi
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
