#!/bin/bash
set -euo pipefail

# Duplicate the database from production to staging.
# Configure these via environment variables when running the script; defaults provided for staging.
DB_HOST="${DB_HOST:-{PDO_SERVER}}"
DB_USER="${DB_USER:-{PDO_USERNAME}}"
DB_PASS="${DB_PASS:-{PDO_PASSWORD}}"
DB_PROD="richmondsunlight"
DB_STAGING="rs-staging"
MYSQLPUMP_PARALLELISM="${MYSQLPUMP_PARALLELISM:-4}"

# Make sure we have our tools
for tool in mysql mysqldump mysqlpump; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    echo "Required tool '$tool' not found in PATH" >&2
    exit 1
  fi
done

# Ensure required DB settings are present
if [[ -z "${DB_HOST}" || -z "${DB_USER}" || -z "${DB_PASS}" ]]; then
  echo "DB_HOST/DB_USER/DB_PASS must be set (or templated) before running" >&2
  exit 1
fi

# Recreate the staging schema and populate it from production in-place.
mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" -e "DROP DATABASE IF EXISTS \`${DB_STAGING}\`; CREATE DATABASE \`${DB_STAGING}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Use mysqlpump for faster, parallelized export when available; fall back to mysqldump.
if command -v mysqlpump >/dev/null 2>&1; then
  mysqlpump \
    --default-parallelism="${MYSQLPUMP_PARALLELISM}" \
    --single-transaction \
    --set-gtid-purged=OFF \
    --include-databases="${DB_PROD}" \
    -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" \
    | sed -e "s/CREATE DATABASE \`\\?${DB_PROD}\`\\?/CREATE DATABASE \`${DB_STAGING}\`/g" \
          -e "s/USE \`\\?${DB_PROD}\`\\?/USE \`${DB_STAGING}\`/g" \
    | mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_STAGING}" \
    || { echo "mysqlpump staging refresh failed" >&2; exit 1; }
else
  mysqldump --single-transaction --set-gtid-purged=OFF -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_PROD}" \
    | mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_STAGING}" \
    || { echo "mysqldump staging refresh failed" >&2; exit 1; }
fi

# Verify staging has data
if mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" -e "SELECT 'Staging bills row count:', COUNT(*) FROM \`${DB_STAGING}\`.bills;"; then
  :
else
  echo "Warning: could not verify staging data load" >&2
fi
