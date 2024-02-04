#!/usr/bin/env bash

# Is the bill's catch line included?
OUTPUT="$(curl --silent http://api/1.1/bill/2024/sb278.json | jq '.catch_line')"
EXPECTED='"Virginia Abortion Care &amp; Gender-Affirming Health Care Protection Act; established, civil penalties."';
if [ "$OUTPUT" != "$EXPECTED" ]
then
    echo "ERROR: Bill's catch line isn't included"
    ERRORED=true
fi

# Is the bill's patron shortname correct?
OUTPUT="$(curl --silent http://api/1.1/bill/2024/sb278.json | jq '.patron_shortname')"
EXPECTED='"gfhashmi"';
if [ "$OUTPUT" != "$EXPECTED" ]
then
    echo "ERROR: Bill's patron shortname isn't correct"
    ERRORED=true
fi

# Is the legislator's formatted name correct?
OUTPUT="$(curl --silent http://api/1.1/legislator/rcdeeds.json | jq '.name_formatted')"
EXPECTED='"Sen. Creigh Deeds (D-Charlottesville)"';
if [ "$OUTPUT" != "$EXPECTED" ]
then
    echo "ERROR: Legislator's formatted name isn't correct"
    ERRORED=true
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    exit 1
fi
