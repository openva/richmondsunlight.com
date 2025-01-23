#!/usr/bin/env bash

echo "Running API tests..."

# Is the bill's catch line included?
URL="http://api/1.1/bill/2024/sb278.json"
OUTPUT="$(curl --silent $URL | jq '.catch_line')"
EXPECTED='"Virginia Abortion Care &amp; Gender-Affirming Health Care Protection Act; established, civil penalties."';
if [ "$OUTPUT" != "$EXPECTED" ]
then
    echo "❌: $URL Bill's catch line isn't included (expected $EXPECTED, got \"$OUTPUT\")"
    ERRORED=true
else
    echo "✅: $URL Bill's catch line is included"
fi

# Is the bill's patron shortname correct?
URL="http://api/1.1/bill/2024/sb278.json"
OUTPUT="$(curl --silent $URL | jq '.patron_shortname')"
EXPECTED='"gfhashmi"';
if [ "$OUTPUT" != "$EXPECTED" ]
then
    echo "❌: $URL Bill's patron shortname isn't correct (expected $EXPECTED, got \"$OUTPUT\")"
    ERRORED=true
else
    echo "✅: $URL Bill's patron shortname is correct"
fi

# Is the legislator's formatted name correct?
URL="http://api/1.1/legislator/rcdeeds.json"
OUTPUT="$(curl --silent $URL | jq '.name_formatted')"
EXPECTED='"Sen. Creigh Deeds (D-Charlottesville)"';
if [ "$OUTPUT" != "$EXPECTED" ]
then
    echo "❌: $URL Legislator's formatted name isn't correct (expected $EXPECTED, got \"$OUTPUT\")"
    ERRORED=true
else
    echo "✅: $URL Legislator's formatted name is correct"
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    exit 1
fi
