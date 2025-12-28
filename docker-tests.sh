#!/usr/bin/env bash

set -euo pipefail

COMPOSE_BINARY=${DOCKER_COMPOSE:-"docker compose"}
WEB_SERVICE=${WEB_SERVICE:-web}
CONTAINER_NAME=${CONTAINER_NAME:-rs_web}
ZAP_TARGET=${ZAP_TARGET:-http://rs_web}
ZAP_FAIL_LEVEL_RAW=${ZAP_FAIL_LEVEL:-WARN}
ZAP_BASELINE_MINUTES=${ZAP_BASELINE_MINUTES:-1}
ZAP_REPORT_DIR=${ZAP_REPORT_DIR:-$(pwd)/zap-reports}
ZAP_REPORT_JSON=${ZAP_REPORT_JSON:-/zap/wrk/zap-baseline.json}
ZAP_REPORT_HTML=${ZAP_REPORT_HTML:-/zap/wrk/zap-baseline.html}

FULL_SCAN=false
RUN_BROWSER=false
while [[ $# -gt 0 ]]; do
  case "$1" in
    --zap-full-scan)
      FULL_SCAN=true
      shift
      ;;
    --browser-tests)
      RUN_BROWSER=true
      shift
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 1
      ;;
  esac
done

# Execute test suite inside the running container (service name required for exec)
$COMPOSE_BINARY exec "${WEB_SERVICE}" /var/www/deploy/tests/run-all.sh

# Ensure the specific container is running (compose ps gives container names when container_name is set)
if ! $COMPOSE_BINARY ps --format '{{.Name}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "Container '${CONTAINER_NAME}' is not running. Please start docker compose before running tests." >&2
    exit 1
fi

# Run browser-based interaction tests
if [ "${RUN_BROWSER}" = true ]; then
  echo "Running Playwright browser interaction tests..."
  $COMPOSE_BINARY run --rm \
    -e PLAYWRIGHT_BASE_URL="${ZAP_TARGET}" \
    --workdir /workspace/deploy/browser-tests \
    playwright bash -lc "npm ci --ignore-scripts && npx playwright test"
fi

mkdir -p "${ZAP_REPORT_DIR}"

# Normalize and validate ZAP fail level
ZAP_FAIL_LEVEL=$(echo "${ZAP_FAIL_LEVEL_RAW}" | tr '[:lower:]' '[:upper:]')
case "${ZAP_FAIL_LEVEL}" in
  PASS|IGNORE|INFO|WARN|FAIL) ;;
  *)
    echo "Invalid ZAP_FAIL_LEVEL '${ZAP_FAIL_LEVEL_RAW}'. Use one of PASS, IGNORE, INFO, WARN, FAIL." >&2
    exit 1
    ;;
esac

# Run an OWASP ZAP scan against the site
if [ "${FULL_SCAN}" = true ]; then
  echo "Running OWASP ZAP full scan against ${ZAP_TARGET} (fail on ${ZAP_FAIL_LEVEL}+ alerts)..."
  $COMPOSE_BINARY run --rm --entrypoint="" -v "${ZAP_REPORT_DIR}:/zap/wrk" owasp_zap \
    zap-full-scan.py \
      -t "${ZAP_TARGET}" \
      -m "${ZAP_BASELINE_MINUTES}" \
      -I \
      -J "${ZAP_REPORT_JSON}" \
      -r "${ZAP_REPORT_HTML}" \
      -l "${ZAP_FAIL_LEVEL}"
  echo "ZAP full scan complete. Reports available in ${ZAP_REPORT_DIR}."
else
  echo "Running OWASP ZAP baseline scan against ${ZAP_TARGET} (fail on ${ZAP_FAIL_LEVEL}+ alerts)..."
  $COMPOSE_BINARY run --rm --entrypoint="" -v "${ZAP_REPORT_DIR}:/zap/wrk" owasp_zap \
    zap-baseline.py \
      -t "${ZAP_TARGET}" \
      -m "${ZAP_BASELINE_MINUTES}" \
      -I \
      -J "${ZAP_REPORT_JSON}" \
      -r "${ZAP_REPORT_HTML}" \
      -l "${ZAP_FAIL_LEVEL}"
  echo "ZAP baseline scan complete. Reports available in ${ZAP_REPORT_DIR}."
fi

echo "All tests completed successfully."
