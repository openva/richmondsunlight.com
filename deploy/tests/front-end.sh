#!/usr/bin/env bash

# Is basic bill metadata being displayed?
curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/" |grep -q "<h1>Cruelty to animals"
if [ $? -ne 0 ]; then
    echo "ERROR: Basic bill metadata isn't being displayed"
    ERRORED=true
fi

# Is bill text being displayed?
curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/fulltext/" |grep -q "agricultural animal"
if [ $? -ne 0 ]; then
    echo "ERROR: Bill text isn't being displayed"
    ERRORED=true
fi

# Are bill comments being displayed?
curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/" |grep -q "gruesome"
if [ $? -ne 0 ]; then
    echo "ERROR: Bill comments aren't being displayed"
    ERRORED=true
fi

# Are bill poll results being displayed?
curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/" |grep -q "55 votes"
if [ $? -ne 0 ]; then
    echo "ERROR: Bill poll results aren't being displayed"
    ERRORED=true
fi

# Are bill outcomes being displayed?
curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/" |grep -q "Bill Has Passed"
if [ $? -ne 0 ]; then
    echo "ERROR: Bill outcomes aren't being displayed"
    ERRORED=true
fi

# Are bill histories being displayed?
curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/" |grep -q "Rereferred to Finance"
if [ $? -ne 0 ]; then
    echo "ERROR: Bill histories aren't being displayed"
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
