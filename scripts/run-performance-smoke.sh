#!/bin/sh

set -eu

COMPOSE_PROJECT_NAME=carematch-performance-test
export COMPOSE_PROJECT_NAME
COMPOSE_FILES="-f compose.yaml -f compose.performance.yaml"
PERFORMANCE_APP_KEY="base64:$(openssl rand -base64 32)"
PERFORMANCE_PASSWORD="synthetic-performance-password"
POSTGRES_DB=carematch_performance
POSTGRES_USER=carematch
POSTGRES_PASSWORD=performance_only
export PERFORMANCE_APP_KEY PERFORMANCE_PASSWORD POSTGRES_DB POSTGRES_USER POSTGRES_PASSWORD

cleanup() {
    status=$?
    trap - EXIT INT TERM
    if [ "$status" -ne 0 ]; then
        docker compose $COMPOSE_FILES --profile performance logs --no-color || true
    fi
    docker compose $COMPOSE_FILES --profile performance down --volumes --remove-orphans
    exit "$status"
}

trap cleanup EXIT INT TERM

docker compose $COMPOSE_FILES --profile performance config --quiet
docker compose $COMPOSE_FILES --profile performance up -d --build --wait postgres redis backend
docker compose $COMPOSE_FILES --profile performance exec -T backend php artisan migrate --force
docker compose $COMPOSE_FILES --profile performance exec -T backend php artisan db:seed --class=PerformanceTestSeeder --force
docker compose $COMPOSE_FILES --profile performance run --rm k6 run /scripts/smoke.js
