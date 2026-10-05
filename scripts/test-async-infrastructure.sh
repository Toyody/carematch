#!/bin/sh

set -eu

COMPOSE_PROJECT_NAME=carematch-async-test
export COMPOSE_PROJECT_NAME
COMPOSE_FILES="-f compose.yaml -f compose.async.yaml"
ASYNC_INTEGRATION_APP_KEY="base64:$(openssl rand -base64 32)"
export ASYNC_INTEGRATION_APP_KEY
POSTGRES_DB=carematch_async
POSTGRES_USER=carematch
POSTGRES_PASSWORD=async_integration_only
export POSTGRES_DB POSTGRES_USER POSTGRES_PASSWORD

cleanup() {
    status=$?
    trap - EXIT INT TERM
    if [ "$status" -ne 0 ]; then
        docker compose $COMPOSE_FILES --profile async logs --no-color || true
    fi
    docker compose $COMPOSE_FILES --profile async down --volumes --remove-orphans
    exit "$status"
}

trap cleanup EXIT INT TERM

docker compose $COMPOSE_FILES --profile async config --quiet
docker compose $COMPOSE_FILES --profile async up -d --wait postgres redis elasticmq

docker compose $COMPOSE_FILES --profile async run --rm \
    -e RUN_ASYNC_INTEGRATION=true \
    backend php artisan test tests/Integration/Infrastructure/AsyncInfrastructureTest.php
