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

    response="$(curl --silent --show-error --fail "$url")" || {
        echo "❌: $url request failed"
        response="$(curl --silent "$url")"
        echo "$response"
        ERRORED=true
        return
    }

    output="$(printf '%s' "$response" | jq "$jq_expr")"
    if [ "$output" != "$expected" ]; then
        echo "❌: $url (${jq_expr}) expected $expected, got \"$output\""
        echo "$response"
        ERRORED=true
    else
        echo "✅: $url (${jq_expr}) matches expected"
    fi
}

check "/bill/2025/hb41.json" ".catch_line" '"Standards of Learning; programs of instruction, civics education on local government."'
check "/bill/2025/hb41.json" ".patron_shortname" '"wcgreen"'
check "/bill/2025/hb41.json" ".status" '"failed committee"'
check "/bill/2025/hb41.json" ".chamber" '"house"'
check "/bill/2025/hb41.json" ".year" '"2025"'
check "/bill/2025/hb41.json" ".related | length > 0" 'true'
check "/bill/2025/hb41.json" ".text[0].number" '"HB41"'
check "/bill/2025/hb41.json" "any(.status_history[].translation; contains(\"failed committee\"))" 'true'
check "/bill/2025/hb41.json" "any(.tags[]?; . == \"high school\")" 'true'
check "/bill/2025/hb41.json" ".full_text | contains(\"develop the skills\")" 'true'

check "/legislator/rcdeeds.json" ".name_formatted" '"Sen. Creigh Deeds (D-Charlottesville)"'
check "/legislator/rcdeeds.json" ".shortname" '"rcdeeds"'
check "/legislator/rcdeeds.json" ".chamber" '"senate"'
check "/legislator/rcdeeds.json" ".district" '"11"'
check "/legislator/rcdeeds.json" ".email" '"senatordeeds@senate.virginia.gov"'

if [ "$ERRORED" = true ]; then
    exit 1
fi
