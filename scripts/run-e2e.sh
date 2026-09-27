#!/bin/sh

set -eu

PROJECT_NAME=carematch-e2e
ENV_FILE=docker/e2e.env
OVERRIDE_FILE=compose.e2e.yaml
E2E_APP_KEY="base64:$(openssl rand -base64 32)"
export E2E_APP_KEY

compose_e2e() {
    docker compose \
        --project-name "$PROJECT_NAME" \
        --env-file "$ENV_FILE" \
        -f compose.yaml \
        -f "$OVERRIDE_FILE" \
        "$@"
}

cleanup() {
    compose_e2e down --volumes --remove-orphans
}

trap cleanup EXIT INT TERM

compose_e2e config --quiet
if ! compose_e2e up -d --build --wait; then
    compose_e2e logs --no-color backend frontend
    exit 1
fi
compose_e2e exec -T backend /app/scripts/reset-e2e-database.sh
compose_e2e run --rm playwright npm ci

if ! compose_e2e run --rm playwright npm run test:e2e; then
    compose_e2e logs --no-color backend frontend
    exit 1
fi
