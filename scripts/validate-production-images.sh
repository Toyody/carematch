#!/bin/sh

set -eu

COMPOSE_FILE=compose.production-validation.yaml
PRODUCTION_VALIDATION_APP_KEY="base64:$(openssl rand -base64 32)"
export PRODUCTION_VALIDATION_APP_KEY

cleanup() {
    docker compose -f "$COMPOSE_FILE" down --volumes --remove-orphans
}

trap cleanup EXIT INT TERM

docker compose -f "$COMPOSE_FILE" config --quiet
docker compose -f "$COMPOSE_FILE" build
docker compose -f "$COMPOSE_FILE" up -d postgres
docker compose -f "$COMPOSE_FILE" run --rm backend php artisan migrate --force

if ! docker compose -f "$COMPOSE_FILE" up -d --wait; then
    docker compose -f "$COMPOSE_FILE" logs --no-color backend frontend gateway
    exit 1
fi

curl --fail --silent --show-error http://localhost:8080/ >/dev/null
curl --fail --silent --show-error http://localhost:8080/api/v1/health >/dev/null
curl --fail --silent --show-error http://localhost:8080/sanctum/csrf-cookie >/dev/null

curl --fail --silent --show-error http://localhost:8080/ \
    | grep -F "Portfolio demo — use synthetic data only." >/dev/null

registration_status="$(curl --silent --output /dev/null --write-out '%{http_code}' \
    --request POST \
    --header 'Accept: application/json' \
    http://localhost:8080/api/v1/auth/register)"
test "$registration_status" = "403"

docker compose -f "$COMPOSE_FILE" exec -T backend php artisan about --only=environment,cache,drivers
docker compose -f "$COMPOSE_FILE" exec -T backend php artisan cache:clear
