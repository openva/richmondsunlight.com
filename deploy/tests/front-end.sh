#!/usr/bin/env bash

# If any test fails, exit the test script
set -e

# Is the basic bill text being displayed?
curl --silent "http://localhost:5000/bill/2019/sb1604/" |grep "<h1>Cruelty to animals"
