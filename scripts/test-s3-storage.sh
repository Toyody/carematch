#!/usr/bin/env bash

set -euo pipefail

PROJECT_NAME="carematch-storage-$$"
COMPOSE_FILES=(-f compose.yaml -f compose.storage.yaml)
STORAGE_INTEGRATION_APP_KEY="base64:$(openssl rand -base64 32)"
POSTGRES_DB=carematch_storage
POSTGRES_USER=carematch
POSTGRES_PASSWORD=storage_only
export STORAGE_INTEGRATION_APP_KEY POSTGRES_DB POSTGRES_USER POSTGRES_PASSWORD

compose() {
    docker compose --project-name "$PROJECT_NAME" "${COMPOSE_FILES[@]}" --profile storage "$@"
}

cleanup() {
    status=$?
    trap - EXIT INT TERM
    if [[ $status -ne 0 ]]; then
        compose logs --no-color postgres s3 backend || true
    fi
    compose down --volumes --remove-orphans >/dev/null 2>&1 || true
    exit "$status"
}

trap cleanup EXIT INT TERM

compose config --quiet
compose up -d --build --wait postgres s3

echo "[storage] Exercising Candidate Documents through the real S3-compatible adapter."
compose run --rm \
    -e STORAGE_INTEGRATION_SCENARIO=available \
    backend php artisan test tests/Integration/Infrastructure/CandidateDocumentS3IntegrationTest.php

echo "[storage] Exercising an unreachable S3 endpoint and metadata fail-closed behaviour."
compose run --rm \
    -e STORAGE_INTEGRATION_SCENARIO=unavailable \
    -e AWS_CANDIDATE_DOCUMENTS_ENDPOINT=http://127.0.0.1:1 \
    backend php artisan test tests/Integration/Infrastructure/CandidateDocumentS3IntegrationTest.php

echo "[storage] Re-running the successful lifecycle after endpoint recovery."
compose run --rm \
    -e STORAGE_INTEGRATION_SCENARIO=recovered \
    backend php artisan test tests/Integration/Infrastructure/CandidateDocumentS3IntegrationTest.php

echo "[storage] PASS: private upload/download/delete, tenant/RBAC boundaries, outage safety and recovery were verified."
