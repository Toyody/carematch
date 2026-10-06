#!/usr/bin/env bash

set -euo pipefail

fail() {
    echo "deployment consistency: $1" >&2
    exit 1
}

tracked_files="$(git ls-files)"

if printf '%s\n' "$tracked_files" | grep -E '(^|/)(\.env|[^/]+\.tfvars|terraform\.tfstate($|\.)|.*\.(dump|backup))$' \
    | grep -Ev '(^|/)\.env\.example$|\.env\.production\.example$|\.tfvars\.example$' >/dev/null; then
    fail 'a populated environment, tfvars, Terraform state or database dump is tracked'
fi

for pattern in '*.tfstate' '*.tfstate.*' '*.tfvars' '*.dump' '*.backup' '.env' '.env.*'; do
    grep -F "$pattern" .gitignore >/dev/null || fail ".gitignore is missing $pattern"
done

if git grep -I -E 'AKIA[0-9A-Z]{16}|ASIA[0-9A-Z]{16}|sk-[A-Za-z0-9_-]{20,}' -- . \
    ':(exclude)package-lock.json' ':(exclude)composer.lock' >/dev/null; then
    fail 'a credential-shaped value is present in tracked source'
fi

for name in \
    APP_KEY DB_PASSWORD REDIS_PASSWORD MAIL_PASSWORD OPENAI_API_KEY; do
    grep -F "{ name = \"$name\", valueFrom =" infra/terraform/compute.tf >/dev/null \
        || fail "$name is not supplied to ECS as a secret reference"
    if grep -F "{ name = \"$name\", value =" infra/terraform/compute.tf >/dev/null; then
        fail "$name is supplied to ECS as plaintext"
    fi
done

for output in \
    ecs_cluster ecs_backend_service ecs_frontend_service ecs_worker_service ecs_ai_worker_service \
    ecs_backend_task_family ecs_frontend_task_family ecs_worker_task_family ecs_ai_worker_task_family \
    ecs_task_subnets ecs_task_security_groups; do
    grep -F "output \"$output\"" infra/terraform/outputs.tf >/dev/null \
        || fail "Terraform output $output required by deployment is missing"
done

for container in backend frontend worker ai-worker; do
    grep -F "container-name: $container" .github/workflows/deploy-production.yml >/dev/null \
        || fail "deployment image rendering does not contain container $container"
done

grep -F '"php","artisan","migrate","--force"' .github/workflows/deploy-production.yml >/dev/null \
    || fail 'deployment workflow does not run the one-off migration command'

grep -F '{ name = "CARE_MATCH_PUBLIC_DEMO", value = tostring(var.care_match_public_demo) }' infra/terraform/compute.tf >/dev/null \
    || fail 'backend public-demo mode is not driven by the Terraform deployment variable'
grep -F 'default     = true' infra/terraform/variables.tf -B 2 | grep -F 'variable "care_match_public_demo"' >/dev/null \
    || fail 'the documented portfolio deployment must default backend public-demo restrictions on'
grep -F 'NEXT_PUBLIC_CARE_MATCH_PUBLIC_DEMO=true' .github/workflows/deploy-production.yml >/dev/null \
    || fail 'the deployment workflow does not build the frontend in public-demo mode'

for validation_entry_point in Makefile scripts/*.sh; do
    if [[ "$validation_entry_point" == "scripts/check-deployment-consistency.sh" ]]; then
        continue
    fi

    if grep -E '(^|[[:space:]])terraform[[:space:]]+apply' "$validation_entry_point" >/dev/null; then
        fail "local validation entry point $validation_entry_point invokes terraform apply"
    fi
done

grep -F 'visibility_timeout_seconds = 90' infra/terraform/messaging.tf >/dev/null \
    || fail 'Compliance queue visibility timeout drifted from the worker contract'
grep -F 'visibility_timeout_seconds = 180' infra/terraform/messaging.tf >/dev/null \
    || fail 'AI queue visibility timeout drifted from the worker contract'
grep -F '"--timeout=60"' infra/terraform/compute.tf >/dev/null \
    || fail 'Compliance worker timeout drifted from the queue contract'
grep -F '"--timeout=120"' infra/terraform/compute.tf >/dev/null \
    || fail 'AI worker timeout drifted from the queue contract'

grep -F '### Pre-Phase 6B — Offline production rehearsal' docs/roadmap.md >/dev/null \
    || fail 'offline rehearsal roadmap section is missing'

echo 'deployment consistency: PASS'
