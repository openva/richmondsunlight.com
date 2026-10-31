#!/usr/bin/env bash
#==================================================================================
# Fail if an OWASP ZAP scan found any problem that isn't allowed.
#
# Usage: check-zap-alerts.sh <ZAP's JSON report> [<allowlist>]
#
# docker-tests.sh runs this after its ZAP scan. ZAP rates each finding by risk:
# Informational, Low, Medium, or High. Any finding rated Low or higher fails, unless
# its alert reference is in the allowlist (by default, zap-allowed-alerts.txt, beside
# this script), which explains the format and why each finding there is allowed.
#==================================================================================
set -euo pipefail

report="${1:?Usage: $0 <ZAP JSON report> [<allowlist>]}"
allowlist="${2:-$(dirname "${BASH_SOURCE[0]}")/zap-allowed-alerts.txt}"

if [[ ! -f "$report" ]]; then
    echo "There's no ZAP report at $report, so the scan can't be checked." >&2
    exit 1
fi

# The allowed references: each line's first word, leaving out comments and blank lines
allowed="$(sed -e 's/#.*//' "$allowlist" | awk 'NF { print $1 }')"

# Every finding rated Low risk (riskcode 1) or higher whose reference isn't allowed, one per
# line: its reference, risk, name, and how many URLs it was found on. (The report lists each
# finding once per site scanned, with every URL where it was found.)
problems="$(jq -r --arg allowed "$allowed" '
    ($allowed | split("\n")) as $allowed
    | .site[].alerts[]
    | select((.riskcode | tonumber) >= 1)
    | select(.alertRef as $ref | $allowed | index($ref) | not)
    | "\(.alertRef)\t\(.riskdesc)\t\(.name)\t\(.instances | length) URL(s), e.g. \(.instances[0].uri)"
' "$report")"

if [[ -n "$problems" ]]; then
    echo "ZAP found problems that aren't allowed. Fix them, or, if one should be allowed, add" >&2
    echo "its reference (the first column) to $allowlist, with the reason:" >&2
    echo "$problems" | column -t -s $'\t' >&2
    exit 1
fi

echo "ZAP found nothing that isn't allowed (see $allowlist)."
