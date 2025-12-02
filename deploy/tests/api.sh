#!/usr/bin/env bash

set -euo pipefail

echo "Running API tests..."

# Prefer in-cluster hostname if available; otherwise fall back to published port
if getent hosts rs_api >/dev/null 2>&1; then
    API_BASE="${API_BASE:-http://rs_api/1.1}"
else
    API_BASE="${API_BASE:-http://localhost:5001/1.1}"
fi

ERRORED=false

check() {
    local path="$1"
    local jq_expr="$2"
    local expected="$3"
    local url="${API_BASE}${path}"

    output="$(curl --silent "$url" | jq "$jq_expr")"
    if [ "$output" != "$expected" ]; then
        echo "❌: $url (${jq_expr}) expected $expected, got \"$output\""
        ERRORED=true
    else
        echo "✅: $url (${jq_expr}) matches expected"
    fi
}

check "/bill/2024/sb278.json" ".catch_line" '"Virginia Abortion Care &amp; Gender-Affirming Health Care Protection Act; established, civil penalties."'
check "/bill/2024/sb278.json" ".patron_shortname" '"gfhashmi"'
check "/legislator/rcdeeds.json" ".name_formatted" '"Sen. Creigh Deeds (D-Charlottesville)"'

if [ "$ERRORED" = true ]; then
    exit 1
fi
