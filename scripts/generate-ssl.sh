#!/usr/bin/env bash
# BookHub — generate a self-signed TLS certificate for local development.
# The certificate and key are generated at runtime and are NEVER committed
# (see .gitignore: *.key, *.crt, nginx/ssl/).
# Usage: ./scripts/generate-ssl.sh [hostname]
#   Hostname defaults to APP_URL_HOST (from .env) or "localhost".
set -euo pipefail
cd "$(dirname "$0")/.."
HOSTNAME="${1:-${APP_URL_HOST:-localhost}}"
mkdir -p nginx/ssl
if [ -f nginx/ssl/nginx-selfsigned.crt ] && [ -f nginx/ssl/nginx-selfsigned.key ]; then
  echo "SSL certificate already exists in nginx/ssl — skipping."
  exit 0
fi
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout nginx/ssl/nginx-selfsigned.key \
  -out nginx/ssl/nginx-selfsigned.crt \
  -subj "/C=US/ST=State/L=City/O=BookHub/OU=Dev/CN=${HOSTNAME}"
chmod 600 nginx/ssl/nginx-selfsigned.key
echo "Generated nginx/ssl/nginx-selfsigned.crt and nginx-selfsigned.key for CN=${HOSTNAME} (local only, not committed)."
