#!/usr/bin/env bash

set -euo pipefail

PROJECT_NAME="carematch-rollback-$$"
COMPOSE_FILE="compose.rollback.yaml"
ROLLBACK_REF="${ROLLBACK_REF:-f94ada7}"
ROLLBACK_TMP_DIR="$(mktemp -d)"
ROLLBACK_APP_KEY="base64:$(openssl rand -base64 32)"
ROLLBACK_PASSWORD="synthetic-rollback-password"
export ROLLBACK_APP_KEY ROLLBACK_PASSWORD

CURRENT_COMMIT="$(git rev-parse HEAD)"
ROLLBACK_COMMIT="$(git rev-parse "${ROLLBACK_REF}^{commit}")"
CURRENT_IMAGE="carematch-rollback-current:${CURRENT_COMMIT}"
OLD_IMAGE="carematch-rollback-old:${ROLLBACK_COMMIT}"

compose() {
    docker compose --project-name "$PROJECT_NAME" -f "$COMPOSE_FILE" "$@"
}

cleanup() {
    status=$?
    trap - EXIT INT TERM
    if [[ $status -ne 0 ]]; then
        compose logs --no-color postgres backend || true
    fi
    compose down --volumes --remove-orphans >/dev/null 2>&1 || true
    docker image rm "$CURRENT_IMAGE" "$OLD_IMAGE" >/dev/null 2>&1 || true
    rm -rf "$ROLLBACK_TMP_DIR"
    exit "$status"
}

trap cleanup EXIT INT TERM

if ! git merge-base --is-ancestor "$ROLLBACK_COMMIT" "$CURRENT_COMMIT"; then
    echo "ROLLBACK_REF must resolve to an ancestor of the current release." >&2
    exit 1
fi

echo "[rollback] Current release:  $CURRENT_COMMIT"
echo "[rollback] Rollback release: $ROLLBACK_COMMIT"

mkdir -p "$ROLLBACK_TMP_DIR/release"
git archive "$ROLLBACK_COMMIT" | tar -x -C "$ROLLBACK_TMP_DIR/release"

echo "[rollback] Building isolated old and current production images."
docker build -q -f "$ROLLBACK_TMP_DIR/release/backend/Dockerfile.production" \
    -t "$OLD_IMAGE" "$ROLLBACK_TMP_DIR/release/backend" >/dev/null
docker build -q -f backend/Dockerfile.production -t "$CURRENT_IMAGE" backend >/dev/null

ROLLBACK_BACKEND_IMAGE="$OLD_IMAGE"
export ROLLBACK_BACKEND_IMAGE
compose config --quiet
compose up -d --wait postgres

echo "[rollback] Applying previous-release migrations and synthetic fixture."
compose run --rm backend php artisan migrate --force --no-interaction
compose run --rm backend php artisan db:seed --class=PortfolioDemoSeeder --force --no-interaction

smoke_backend() {
    expected_ai_status="$1"
    compose up -d --wait --force-recreate backend
    compose exec -T backend php -r '
        $checks = [
            "/api/v1/health" => 200,
            "/api/v1/auth/me" => 401,
            "/api/v1/organisations" => 401,
            "/api/v1/organisations/1/candidates" => 401,
            "/api/v1/organisations/1/jobs" => 401,
            "/api/v1/organisations/1/applications" => 401,
            "/api/v1/organisations/1/jobs/1/matches" => 401,
        ];
        foreach ($checks as $path => $expected) {
            $context = stream_context_create(["http" => ["ignore_errors" => true, "header" => "Accept: application/json\r\n"]]);
            file_get_contents("http://127.0.0.1:8080".$path, false, $context);
            preg_match("#HTTP/\\S+ (\\d{3})#", $http_response_header[0] ?? "", $matches);
            if ((int) ($matches[1] ?? 0) !== $expected) {
                fwrite(STDERR, "Unexpected status for {$path}.\n");
                exit(1);
            }
        }
    '
    compose exec -T backend php -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        foreach (["organisations", "candidates", "jobs", "applications"] as $table) {
            if (\Illuminate\Support\Facades\DB::table($table)->count() < 1) {
                fwrite(STDERR, "Expected synthetic rows in {$table}.\n");
                exit(1);
            }
        }
    '
    actual_ai_status="$(compose exec -T backend php -r '
        $context = stream_context_create(["http" => ["ignore_errors" => true, "header" => "Accept: application/json\r\n"]]);
        file_get_contents("http://127.0.0.1:8080/api/v1/organisations/1/candidates/1/documents/1/ai-extractions/1", false, $context);
        preg_match("#HTTP/\\S+ (\\d{3})#", $http_response_header[0] ?? "", $matches);
        echo $matches[1] ?? "000";
    ' | tr -d '\r')"
    test "$actual_ai_status" = "$expected_ai_status"
}

echo "[rollback] Verifying the previous release on its own schema."
smoke_backend 404

echo "[rollback] Applying current forward-only migrations and verifying current code."
ROLLBACK_BACKEND_IMAGE="$CURRENT_IMAGE"
export ROLLBACK_BACKEND_IMAGE
compose run --rm backend php artisan migrate --force --no-interaction
smoke_backend 401

echo "[rollback] Starting the previous release against the forward-migrated database."
ROLLBACK_BACKEND_IMAGE="$OLD_IMAGE"
export ROLLBACK_BACKEND_IMAGE
smoke_backend 404
compose run --rm backend php artisan migrate:status --no-ansi >/dev/null

echo "[rollback] PASS: the previous application release tolerates the current forward schema without reversing migrations."
