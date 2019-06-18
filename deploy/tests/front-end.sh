#!/usr/bin/env bash

# Is basic bill metadata being displayed?
curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/" |grep -q "<h1>Cruelty to animals"
if [ $? -ne 0 ]; then
    echo "ERROR: Basic bill metadata isn't being displayed"
    ERRORED=true
fi

# Is bill text being displayed?
curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/fulltext" |grep -q "agricultural animal"
if [ $? -ne 0 ]; then
    echo "ERROR: Bill text isn't being displayed"
    ERRORED=true
fi

# Is basic legislator information being displayed?
curl --no-buffer --silent "http://localhost:5000/legislator/lradams/" |grep -q "<h1>Del. Les Adams"
if [ $? -ne 0 ]; then
    echo "ERROR: Basic legislator information isn't being displayed"
    ERRORED=true
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    exit 1
fi
