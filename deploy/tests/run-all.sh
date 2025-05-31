#!/usr/bin/env bash

# Switch to the working directory from wherever this is being invoked
pushd .
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$DIR" || exit

# Check if the site is running, polling for up to 30 seconds
SITE_URL="http://localhost:8000/"
TIMEOUT=30
ELAPSED=0
until curl --output /dev/null --silent --head --fail "$SITE_URL"; do
    if [ $ELAPSED -ge $TIMEOUT ]; then
        echo "Site is not running or not reachable at $SITE_URL after $TIMEOUT seconds, abandoning tests"
        exit 1
    fi
    sleep 1
    ELAPSED=$((ELAPSED+1))
done
echo "Site is up and running at $SITE_URL"

# Run the page-scan tests
if ! php ./page-scan.php; then
    ERRORED=true
fi

# Run the API tests
if ! ./api.sh; then
    ERRORED=true
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    echo "Some tests failed"
    exit 1
fi

# Switch back to the directory this was invoked from
popd || exit
