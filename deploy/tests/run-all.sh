#!/usr/bin/env bash


# Run the front-end tests
if ! ./front-end.sh; then
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
