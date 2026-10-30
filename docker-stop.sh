#!/bin/bash

# Stop everything, including the "tools" containers (ZAP and Playwright), and any of those that
# docker-tests.sh left behind, if it was interrupted.
docker compose --profile tools down --remove-orphans
