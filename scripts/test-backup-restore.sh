#!/usr/bin/env bash

set -euo pipefail

PROJECT_NAME="carematch-backup-restore-$$"
COMPOSE_FILE="compose.backup-restore.yaml"
PREDEPLOYMENT_TMP_DIR="$(mktemp -d)"
PREDEPLOYMENT_APP_KEY="base64:$(openssl rand -base64 32)"
PREDEPLOYMENT_PASSWORD="synthetic-rehearsal-password"
export PREDEPLOYMENT_TMP_DIR PREDEPLOYMENT_APP_KEY PREDEPLOYMENT_PASSWORD

compose() {
    docker compose --project-name "$PROJECT_NAME" -f "$COMPOSE_FILE" "$@"
}

cleanup() {
    status=$?
    trap - EXIT INT TERM
    if [[ $status -ne 0 ]]; then
        compose logs --no-color source-postgres restore-postgres backend || true
    fi
    compose down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf "$PREDEPLOYMENT_TMP_DIR"
    exit "$status"
}

trap cleanup EXIT INT TERM

cp scripts/predeployment/database-baseline.sql "$PREDEPLOYMENT_TMP_DIR/database-baseline.sql"

echo "[backup/restore] Starting isolated PostgreSQL/PostGIS source and restore targets."
compose config --quiet
compose up -d --build --wait source-postgres restore-postgres

# The PostGIS image preloads extensions into POSTGRES_DB. Recreate the restore
# target from template0 so pg_restore exercises the dump's extension/schema
# definitions against a genuinely empty database.
compose exec -T restore-postgres dropdb \
    --username carematch \
    carematch_rehearsal_restore
compose exec -T restore-postgres createdb \
    --username carematch \
    --template template0 \
    carematch_rehearsal_restore

echo "[backup/restore] Migrating and seeding deterministic synthetic source data."
compose run --rm backend php artisan migrate --force --no-interaction
compose run --rm backend php artisan db:seed --class=PredeploymentRehearsalSeeder --force --no-interaction

compose exec -T source-postgres psql \
    --username carematch \
    --dbname carematch_rehearsal_source \
    --no-align --tuples-only \
    --file /rehearsal/database-baseline.sql \
    >"$PREDEPLOYMENT_TMP_DIR/source.baseline"

echo "[backup/restore] Creating a logical custom-format dump with pg_dump."
compose exec -T source-postgres pg_dump \
    --username carematch \
    --dbname carematch_rehearsal_source \
    --format custom \
    --no-owner \
    --no-privileges \
    --file /rehearsal/carematch-rehearsal.dump

test -s "$PREDEPLOYMENT_TMP_DIR/carematch-rehearsal.dump"

echo "[backup/restore] Restoring into the separate fresh target."
compose exec -T restore-postgres pg_restore \
    --username carematch \
    --dbname carematch_rehearsal_restore \
    --no-owner \
    --no-privileges \
    --exit-on-error \
    /rehearsal/carematch-rehearsal.dump

compose exec -T restore-postgres psql \
    --username carematch \
    --dbname carematch_rehearsal_restore \
    --no-align --tuples-only \
    --file /rehearsal/database-baseline.sql \
    >"$PREDEPLOYMENT_TMP_DIR/restore.baseline"

diff -u "$PREDEPLOYMENT_TMP_DIR/source.baseline" "$PREDEPLOYMENT_TMP_DIR/restore.baseline"

echo "[backup/restore] Verifying Laravel migration history and restored connectivity."
status_output="$PREDEPLOYMENT_TMP_DIR/migration-status.txt"
compose run --rm \
    -e DB_HOST=restore-postgres \
    -e DB_DATABASE=carematch_rehearsal_restore \
    backend php artisan migrate:status --no-ansi >"$status_output"
if grep -Fq "Pending" "$status_output"; then
    echo "Restored database contains pending migrations." >&2
    exit 1
fi
grep -F "postgis_extension|1" "$PREDEPLOYMENT_TMP_DIR/restore.baseline" >/dev/null
grep -F "orphan_applications|0" "$PREDEPLOYMENT_TMP_DIR/restore.baseline" >/dev/null
grep -F "orphan_history|0" "$PREDEPLOYMENT_TMP_DIR/restore.baseline" >/dev/null

echo "[backup/restore] PASS: logical dump, separate restore, relational baseline, PostGIS and Laravel connectivity match."
