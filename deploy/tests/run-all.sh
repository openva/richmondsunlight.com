#!/usr/bin/env bash

# Switch to the working directory from wherever this is being invoked
pushd .
ERRORED=false
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$DIR" || exit

REPO_ROOT="$( cd "$DIR/../.." >/dev/null 2>&1 && pwd )"

# Run PHPUnit test suite (unit + integration tests)
if ! "$REPO_ROOT/htdocs/includes/vendor/bin/phpunit" \
        --configuration "$REPO_ROOT/phpunit.xml" \
        --testsuite default; then
    ERRORED=true
fi

# Run the page-scan smoke tests (kept as standalone — requires live web server)
if ! php ./page-scan.php; then
    ERRORED=true
fi

# Note: API tests are run separately from the host via docker-tests.sh,
# not from inside the container, since they require docker access.

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    echo "Some tests failed"
    popd || exit 1
    exit 1
fi

# Switch back to the directory this was invoked from
popd || exit

echo "All tests passed successfully"
