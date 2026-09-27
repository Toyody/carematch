#!/bin/sh

set -eu

if [ "${APP_ENV:-}" != "e2e" ]; then
    echo "Refusing E2E reset: APP_ENV must be exactly e2e." >&2
    exit 1
fi

if [ "${DB_CONNECTION:-}" != "pgsql" ]; then
    echo "Refusing E2E reset: DB_CONNECTION must be exactly pgsql." >&2
    exit 1
fi

if [ "${DB_HOST:-}" != "postgres" ]; then
    echo "Refusing E2E reset: DB_HOST must be the isolated Compose postgres service." >&2
    exit 1
fi

if [ "${DB_DATABASE:-}" != "carematch_e2e" ]; then
    echo "Refusing E2E reset: DB_DATABASE must be exactly carematch_e2e." >&2
    exit 1
fi

php artisan migrate:fresh --force
