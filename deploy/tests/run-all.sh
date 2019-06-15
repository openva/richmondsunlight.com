#!/usr/bin/env bash

# If any sets of tests fails, exit the test script
set -e

./front-end.sh

echo "All tests passed"
