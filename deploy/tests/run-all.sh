#!/usr/bin/env bash

# Run the front-end tests
./front-end.sh
if [ $? -ne 0 ]; then
    ERRORED=true
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    echo "Some tests failed"
    exit 1
fi
