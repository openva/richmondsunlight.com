#!/usr/bin/env bash

# Switch to the working directory from wherever this is being invoked
pushd .
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$DIR" || exit

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
