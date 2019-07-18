#!/usr/bin/env bash

# Is basic bill metadata being displayed?
if ! curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/" |grep -q "<h1>Cruelty to animals"
then
    echo "ERROR: Basic bill metadata isn't being displayed"
    ERRORED=true
fi

# Is bill text being displayed?
if ! curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/fulltext/" |grep -q "agricultural animal"
then
    echo "ERROR: Bill text isn't being displayed"
    ERRORED=true
fi

# Are bill comments being displayed?
if ! curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/" |grep -q "gruesome"
then
    echo "ERROR: Bill comments aren't being displayed"
    ERRORED=true
fi

# Are bill poll results being displayed?
if ! curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/" |grep -q "55 votes"
then
    echo "ERROR: Bill poll results aren't being displayed"
    ERRORED=true
fi

# Are bill outcomes being displayed?
if ! curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/" |grep -q "Bill Has Passed"
then
    echo "ERROR: Bill outcomes aren't being displayed"
    ERRORED=true
fi

# Are bill histories being displayed?
if ! curl --no-buffer --silent "http://localhost:5000/bill/2019/sb1604/" |grep -q "Rereferred to Finance"
then
    echo "ERROR: Bill histories aren't being displayed"
    ERRORED=true
fi

# Is basic legislator information being displayed?
if ! curl --no-buffer --silent "http://localhost:5000/legislator/lradams/" |grep -q "<h1>Del. Les Adams"
then
    echo "ERROR: Basic legislator information isn't being displayed"
    ERRORED=true
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    exit 1
fi
