#!/bin/bash
set -euo pipefail

# Duplicate the database from production to staging.
# Reads database credentials from includes/settings.inc.php

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SETTINGS_FILE="${SCRIPT_DIR}/../htdocs/includes/settings.inc.php"

echo "====================================="
echo "Database Staging Update"
echo "====================================="
echo ""

# Check if settings file exists
if [ ! -f "${SETTINGS_FILE}" ]; then
  echo "ERROR: Settings file not found at ${SETTINGS_FILE}" >&2
  exit 1
fi

# Read database credentials from PHP settings file
echo "Reading database credentials from settings.inc.php..."
DB_CREDENTIALS=$(php -r "
  require_once '${SETTINGS_FILE}';
  echo PDO_SERVER . '|' . PDO_USERNAME . '|' . PDO_PASSWORD;
")

IFS='|' read -r DB_HOST DB_USER DB_PASS <<< "${DB_CREDENTIALS}"

# Allow override from environment variables
DB_HOST="${DB_HOST_OVERRIDE:-${DB_HOST}}"
DB_USER="${DB_USER_OVERRIDE:-${DB_USER}}"
DB_PASS="${DB_PASS_OVERRIDE:-${DB_PASS}}"
DB_PROD="richmondsunlight"
DB_STAGING="rs-staging"

echo "Production DB: ${DB_PROD}"
echo "Staging DB:    ${DB_STAGING}"
echo "Host:          ${DB_HOST}"
echo "User:          ${DB_USER}"
echo ""

# Make sure we have our tools (use mariadb-dump/mariadb if available, fallback to mysql/mysqldump)
MYSQL_CMD="mysql"
MYSQLDUMP_CMD="mysqldump"

if command -v mariadb >/dev/null 2>&1; then
  MYSQL_CMD="mariadb"
fi

if command -v mariadb-dump >/dev/null 2>&1; then
  MYSQLDUMP_CMD="mariadb-dump"
fi

for tool in ${MYSQL_CMD} ${MYSQLDUMP_CMD}; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    echo "ERROR: Required tool '$tool' not found in PATH" >&2
    exit 1
  fi
done

echo "Using ${MYSQL_CMD} and ${MYSQLDUMP_CMD}"
echo ""

# Ensure required DB settings were read successfully
if [[ -z "${DB_HOST}" || -z "${DB_USER}" || -z "${DB_PASS}" || -z "${DB_PROD}" ]]; then
  echo "ERROR: Failed to read database credentials from settings.inc.php" >&2
  echo "Make sure PDO_SERVER, PDO_USERNAME, PDO_PASSWORD, and MYSQL_DATABASE are defined" >&2
  exit 1
fi

# Test database connection
echo "Testing database connection..."
if ! ${MYSQL_CMD} -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" -e "SELECT 1" >/dev/null 2>&1; then
  echo "ERROR: Cannot connect to database" >&2
  exit 1
fi
echo "✓ Connection successful"
echo ""

# Check if production database exists
echo "Checking production database..."
if ! ${MYSQL_CMD} -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" -e "USE \`${DB_PROD}\`" >/dev/null 2>&1; then
  echo "ERROR: Production database '${DB_PROD}' does not exist" >&2
  exit 1
fi
echo "✓ Production database found"
echo ""

# Recreate the staging schema
echo "Dropping and recreating staging database..."
${MYSQL_CMD} -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" <<SQL
DROP DATABASE IF EXISTS \`${DB_STAGING}\`;
CREATE DATABASE \`${DB_STAGING}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL
echo "✓ Staging database created"
echo ""

# Dump production and import to staging
echo "Copying data from production to staging..."
echo "This may take several minutes for large databases..."
if ${MYSQLDUMP_CMD} \
  --single-transaction \
  --quick \
  --lock-tables=false \
  --skip-add-locks \
  --skip-comments \
  --ignore-table="${DB_PROD}.logs" \
  -h "${DB_HOST}" \
  -u "${DB_USER}" \
  -p"${DB_PASS}" \
  "${DB_PROD}" \
  | ${MYSQL_CMD} \
      -h "${DB_HOST}" \
      -u "${DB_USER}" \
      -p"${DB_PASS}" \
      "${DB_STAGING}"; then
  echo "✓ Data copied successfully"
  echo ""
else
  echo "ERROR: Failed to copy database" >&2
  exit 1
fi

# Copy the logs table structure (but not its data)
echo "Copying logs table structure (empty)..."
if ${MYSQLDUMP_CMD} \
  --no-data \
  -h "${DB_HOST}" \
  -u "${DB_USER}" \
  -p"${DB_PASS}" \
  "${DB_PROD}" logs \
  | ${MYSQL_CMD} \
      -h "${DB_HOST}" \
      -u "${DB_USER}" \
      -p"${DB_PASS}" \
      "${DB_STAGING}"; then
  echo "✓ Logs table structure copied"
  echo ""
else
  echo "ERROR: Failed to copy logs table structure" >&2
  exit 1
fi

# Verify staging has data
echo "Verifying staging database..."
BILL_COUNT=$(${MYSQL_CMD} -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" -N -e "SELECT COUNT(*) FROM \`${DB_STAGING}\`.bills;" 2>/dev/null || echo "0")

if [ "$BILL_COUNT" -gt 0 ]; then
  echo "✓ Staging database verified: ${BILL_COUNT} bills found"
  echo ""
  echo "====================================="
  echo "SUCCESS: Database staging complete!"
  echo "====================================="
else
  echo "WARNING: No bills found in staging database" >&2
  echo "This may indicate a problem with the copy" >&2
fi
