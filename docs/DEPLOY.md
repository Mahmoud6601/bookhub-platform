# BookHub — Deploy guide

This describes the three environments, how to deploy, how to roll back,
and what to do when a deploy fails.

## Environments

| Environment | Compose files | APP_ENV | Secrets | Host ports |
| ----------- | ------------- | ------- | ------- | ---------- |
| **Local dev** | `docker-compose.yml` (+ `docker-compose.override.yml` auto-loaded) | `local` | root `.env` (throwaway values) | 8000 (api), 3000 (client), 3306 (db), 80/443 (nginx) |
| **Staging** | `docker-compose.yml` + `docker-compose.prod.yml` | `production` | staging secret manager / `.env` on the host | 80/443 only (nginx) |
| **Production** | `docker-compose.yml` + `docker-compose.prod.yml` | `production` | production secret manager / `.env` on the host | 80/443 only (nginx) |

The base `docker-compose.yml` is production-safe by default: it exposes NO
host ports for the API/client/database (only Nginx 80/443), runs with
`APP_DEBUG=false`, and refuses to start without `DB_PASSWORD`,
`MYSQL_ROOT_PASSWORD` and `APP_KEY` (fail-fast `${VAR:?message}` syntax).
The dev override re-adds host ports, the API code bind mount and debug mode
— never use it in staging or production.

## First deploy (any environment)

```bash
git clone https://github.com/Mahmoud6601/bookhub-platform.git
cd bookhub-platform

# 1. Secrets — root .env is gitignored, never committed.
cp .env.example .env
# Edit .env: set DB_PASSWORD, MYSQL_ROOT_PASSWORD, APP_KEY.
# Generate APP_KEY with: cd api && composer install && php artisan key:generate --show
# In production, prefer a secret manager (Vault / cloud secrets) that writes
# this file on the host — never paste real secrets into git.

# 2. TLS certificate (local/staging self-signed; production: real cert)
./scripts/generate-ssl.sh "${APP_URL_HOST:-localhost}"
# Production: place your real certificate + key at
#   nginx/ssl/nginx-selfsigned.crt and nginx-selfsigned.key
# (same filenames), or update the template paths.

# 3. Start
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
# Local dev instead:  docker compose up -d --build

# 4. Verify
docker compose ps                 # all services (healthy)
curl -fsS http://localhost:8000/api/health   # dev only (port exposed by override)
curl -kfsS https://localhost/healthz         # via Nginx
docker compose logs -f api
```

The API entrypoint waits for MySQL (up to 60s), generates the APP_KEY into
`api/.env` only if none is provided, and runs `php artisan migrate --force`.
Migrations run automatically on every container start — this is by design,
so a deploy that changes the schema migrates itself.

## Deploying a new version (CI)

CI builds images on every push to `main` and, after tests + Trivy scan +
smoke test pass, pushes them to Docker Hub tagged with the git SHA and the
build number (`bookhub-api:<sha>`, `bookhub-api:build-<run>`). `:latest` is
never used as the deploy tag.

To deploy a CI-built version:

```bash
# IMAGE_TAG is the git SHA from the CI run you want to deploy.
IMAGE_TAG=<sha> docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
docker compose -f docker-compose.yml -f docker-compose.prod.yml ps
curl -kfsS https://localhost/healthz
```

## Rollback

Every deploy is a specific image tag, so rollback is just re-deploying the
previous tag — no rebuild, no code checkout:

```bash
# 1. Find the previous good tag (git SHA from the last green CI run).
# 2. Re-deploy it:
IMAGE_TAG=<previous-sha> docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# 3. Verify health, then check the database state:
docker compose -f docker-compose.yml -f docker-compose.prod.yml ps
curl -kfsS https://localhost/healthz
```

Notes:
- Migrations are NOT rolled back automatically. If the bad deploy ran a
  migration, check `api/database/migrations/` for what changed and decide
  explicitly (usually: fix forward with a new migration, not
  `migrate:rollback`, which can destroy data).
- If the bad deploy corrupted data, restore from a backup first:
  `./scripts/restore-db.sh backups/bookapi-<date>.sql.gz`
  (see "Backups" below), then re-deploy the previous tag.

## Backups

```bash
./scripts/backup-db.sh            # -> backups/bookapi-YYYYMMDD-HHMMSS.sql.gz
./scripts/restore-db.sh backups/bookapi-YYYYMMDD-HHMMSS.sql.gz
```

Back up before every production deploy. `backups/` is gitignored.

## When a deploy fails

| Symptom | Likely cause | Fix |
| ------- | ------------ | --- |
| Compose exits immediately with `... is required` | A required secret is missing from `.env` / environment | Set `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `APP_KEY` in the root `.env` |
| `api` container restarts / `unhealthy` | MySQL not ready, or APP_KEY missing | `docker compose logs api` — the entrypoint prints what it waited for; `docker compose ps` shows db health |
| `db` container restarts | Bad MySQL password / corrupt volume | Check `docker compose logs db`; if the volume is corrupt, restore from backup (data loss risk — see above) |
| `nginx` returns 502 | API or client not up yet | Wait for healthchecks: `docker compose ps`; check `docker compose logs nginx api client` |
| `curl https://localhost` certificate error | Self-signed cert (expected locally) | Use `curl -k` locally; in production install a real certificate |
| Image pull fails in prod | Wrong `IMAGE_TAG` / `DOCKER_USERNAME` | Check the tag exists on Docker Hub; CI only pushes from `main` |
| Smoke test fails in CI | Real regression in the stack | Do NOT push — fix the code, not the test. Check the failing job's `docker compose logs api` output |

## What this project does NOT do (yet)

- No Kubernetes manifests or Helm chart — Compose is the deploy target.
- No blue/green or canary deploys — a deploy replaces the running containers
  (a few seconds of downtime on the API while the container restarts).
- No centralised logging/metrics — logs are per-container JSON files with
  rotation (`max-size: 10m`, `max-file: 3`); view with `docker compose logs`.
- No automatic TLS renewal — self-signed certs are local-only; production
  needs a real certificate (Let's Encrypt / cloud-managed).
