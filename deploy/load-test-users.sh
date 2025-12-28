#!/usr/bin/env bash

set -euo pipefail

DB_SERVICE=${DB_SERVICE:-db}
DB_NAME=${DB_NAME:-richmondsunlight}
MYSQL_USER=${MYSQL_USER:-root}
MYSQL_PASSWORD=${MYSQL_PASSWORD:-password}
MYSQL_HOST=${MYSQL_HOST:-localhost}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SQL_FILE="${SCRIPT_DIR}/mysql/test-users.sql"

if [ ! -f "${SQL_FILE}" ]; then
  echo "Test users SQL not found at ${SQL_FILE}" >&2
  exit 1
fi

echo "Loading test users into ${DB_NAME} on service ${DB_SERVICE}..."
docker compose exec -T "${DB_SERVICE}" sh -c "mysql -h${MYSQL_HOST} -u${MYSQL_USER} -p${MYSQL_PASSWORD} ${DB_NAME}" < "${SQL_FILE}"
echo "Test users loaded."
