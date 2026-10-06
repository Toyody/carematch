#!/usr/bin/env bash

set -euo pipefail

PROJECT_NAME="carematch-failures-$$"
COMPOSE_FILES=(-f compose.production-validation.yaml -f compose.failures.yaml)
FAILURE_REHEARSAL_PORT="${FAILURE_REHEARSAL_PORT:-8182}"
PRODUCTION_VALIDATION_APP_KEY="base64:$(openssl rand -base64 32)"
TMP_DIR="$(mktemp -d)"
export FAILURE_REHEARSAL_PORT PRODUCTION_VALIDATION_APP_KEY

compose() {
    docker compose --project-name "$PROJECT_NAME" "${COMPOSE_FILES[@]}" "$@"
}

cleanup() {
    status=$?
    trap - EXIT INT TERM
    if [[ $status -ne 0 ]]; then
        compose logs --no-color postgres redis backend || true
    fi
    compose down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf "$TMP_DIR"
    exit "$status"
}

trap cleanup EXIT INT TERM

base_url="http://localhost:${FAILURE_REHEARSAL_PORT}"
cookie_jar="$TMP_DIR/cookies.txt"
guest_cookie_jar="$TMP_DIR/guest-cookies.txt"
recovery_cookie_jar="$TMP_DIR/recovery-cookies.txt"

csrf_token() {
    jar="$1"
    curl --fail --silent --show-error \
        --cookie "$jar" --cookie-jar "$jar" \
        --header 'Accept: application/json' \
        --header "Origin: $base_url" \
        --header "Referer: $base_url/" \
        "$base_url/sanctum/csrf-cookie" >/dev/null
    raw="$(awk '$6 == "XSRF-TOKEN" { print $7 }' "$jar" | tail -n 1)"
    test -n "$raw"
    compose exec -T backend php -r 'echo urldecode($argv[1]);' "$raw"
}

post_json() {
    jar="$1"
    token="$2"
    path="$3"
    data="$4"
    output="$5"
    curl --silent --show-error \
        --output "$output" \
        --write-out '%{http_code}' \
        --cookie "$jar" --cookie-jar "$jar" \
        --request POST \
        --header 'Accept: application/json' \
        --header 'Content-Type: application/json' \
        --header "Origin: $base_url" \
        --header "Referer: $base_url/" \
        --header "X-XSRF-TOKEN: $token" \
        --data "$data" \
        "$base_url$path"
}

assert_safe_failure() {
    file="$1"
    if grep -Eiq 'trace|exception|sqlstate|postgres|redis|password|secret' "$file"; then
        echo "Dependency failure response exposed internal detail." >&2
        exit 1
    fi
}

compose config --quiet
compose build backend
compose up -d --wait postgres redis backend
compose exec -T backend php artisan migrate --force --no-interaction

curl --fail --silent --show-error "$base_url/up" >/dev/null

token="$(csrf_token "$cookie_jar")"
register_status="$(post_json \
    "$cookie_jar" "$token" /api/v1/auth/register \
    '{"name":"Synthetic Failure User","email":"failure.rehearsal@example.test","password":"synthetic-failure-password","password_confirmation":"synthetic-failure-password"}' \
    "$TMP_DIR/register.json")"
test "$register_status" = "201"
curl --fail --silent --show-error \
    --cookie "$cookie_jar" \
    --header 'Accept: application/json' \
    --header "Origin: $base_url" \
    --header "Referer: $base_url/" \
    "$base_url/api/v1/auth/me" >/dev/null

echo "[failures] PostgreSQL unavailable: liveness remains shallow and DB requests fail closed."
compose stop postgres
curl --fail --silent --show-error "$base_url/up" >/dev/null
db_status="$(curl --silent --show-error --output "$TMP_DIR/db-down.json" --write-out '%{http_code}' \
    --cookie "$cookie_jar" \
    --header 'Accept: application/json' \
    --header "Origin: $base_url" \
    --header "Referer: $base_url/" \
    "$base_url/api/v1/auth/me")"
test "$db_status" = "500"
assert_safe_failure "$TMP_DIR/db-down.json"

compose up -d --wait postgres
recovery_token="$(csrf_token "$recovery_cookie_jar")"
recovery_status="$(post_json \
    "$recovery_cookie_jar" "$recovery_token" /api/v1/auth/login \
    '{"email":"failure.rehearsal@example.test","password":"synthetic-failure-password"}' \
    "$TMP_DIR/db-recovered.json")"
test "$recovery_status" = "200"
curl --fail --silent --show-error \
    --cookie "$recovery_cookie_jar" \
    --header 'Accept: application/json' \
    --header "Origin: $base_url" \
    --header "Referer: $base_url/" \
    "$base_url/api/v1/auth/me" >/dev/null

guest_token="$(csrf_token "$guest_cookie_jar")"
login_payload='{"email":"unknown.failure@example.test","password":"incorrect-synthetic-password"}'
login_status="$(post_json "$guest_cookie_jar" "$guest_token" /api/v1/auth/login "$login_payload" "$TMP_DIR/login-baseline.json")"
test "$login_status" = "401"

echo "[failures] Redis unavailable: security rate limiting fails closed while liveness remains available."
compose stop redis
curl --fail --silent --show-error "$base_url/up" >/dev/null
redis_status="$(post_json "$guest_cookie_jar" "$guest_token" /api/v1/auth/login "$login_payload" "$TMP_DIR/redis-down.json")"
test "$redis_status" = "500"
assert_safe_failure "$TMP_DIR/redis-down.json"

compose up -d --wait redis
recovered_status="$(post_json "$guest_cookie_jar" "$guest_token" /api/v1/auth/login "$login_payload" "$TMP_DIR/login-recovered.json")"
test "$recovered_status" = "401"

echo "[failures] PASS: PostgreSQL and Redis failures were safe, visible and recovered without rebuilding the application."
