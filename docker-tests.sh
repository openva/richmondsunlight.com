#!/usr/bin/env bash

set -euo pipefail

COMPOSE_BINARY=${DOCKER_COMPOSE:-"docker compose"}
WEB_SERVICE=${WEB_SERVICE:-web}
CONTAINER_NAME=${CONTAINER_NAME:-rs_web}

# Ensure the specific container is running (compose ps gives container names when container_name is set)
if ! $COMPOSE_BINARY ps --format '{{.Name}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "Container '${CONTAINER_NAME}' is not running. Please start docker compose before running tests." >&2
    exit 1
fi

# Execute test suite inside the running container (service name required for exec)
$COMPOSE_BINARY exec "${WEB_SERVICE}" deploy/tests/run-all.sh

echo "All tests completed successfully."
