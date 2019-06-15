#!/usr/bin/env bash

# If any test fails, exit the test script
set -e

# Is basic bill metadata being displayed?
curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/" |grep -q "<h1>Cruelty to animals"

# Is bill text being displayed?
#curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/fulltext" |grep -q ""

# Is basic legislator information being displayed?
curl --no-buffer --silent "http://localhost:5000/legislator/lradams/" |grep -q "<h1>Del. Les Adams"

echo "Front-end tests passed"
