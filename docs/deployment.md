# CareMatch Production Deployment

## Status and scope

Phase 6A prepares the repository for production but does not claim a live deployment. Phase 6B requires an authenticated AWS account, region, controlled DNS name and billable AWS resources. Phase 6C requires a real HTTPS smoke test, backup verification and operational checks.

No command in the normal container startup path migrates, seeds or resets the database automatically.

## Same-origin topology

The supported production topology is one browser origin:

```text
https://<host>/
    /api/*     -> ALB backend target group -> Laravel ECS service
    /sanctum/* -> ALB backend target group -> Laravel ECS service
    /*         -> ALB frontend target group -> Next.js ECS service
```

Use an ACM certificate on the public ALB listener. Route 53 or an external DNS provider points the controlled hostname to the ALB. The backend target group checks `/up`; the public API smoke check uses `/api/v1/health`. `NEXT_PUBLIC_API_URL` is deliberately an empty string so browser API and Sanctum CSRF requests remain relative to the current HTTPS origin.

The cost-conscious portfolio topology uses an internet-facing ALB and Fargate tasks in public subnets with public IPs, while task security groups permit inbound traffic only from the ALB security group. This avoids a NAT Gateway. RDS remains non-public in private database subnets. Tasks still need outbound internet access for ECR, CloudWatch, SSM/Secrets Manager and AWS APIs. One task per service and a Single-AZ RDS instance reduce cost but are not highly available.

## Production containers

The backend production image uses Apache with mod_php on port 8080. Apache is a small conventional Laravel runtime, serves only `backend/public`, runs as `www-data`, enables OPcache and sends access/error output to stdout/stderr. `php artisan serve` is a development server and is not used. Docker and ECS send the process termination signal to the foreground Apache process; Apache handles normal termination and ECS should provide its default stop timeout for in-flight requests.

The backend liveness healthcheck calls `/up`. It proves that the PHP/Laravel HTTP process can respond but intentionally does not make each health probe dependent on PostgreSQL or S3. Database readiness is checked by the migration task and application smoke tests.

The frontend production image uses the Next.js standalone output and runs `node server.js` as the unprivileged `node` user. It contains neither development dependencies nor the source development server.

Validate both images and ALB-like same-origin routing locally:

```bash
make test-production-images
```

The disposable validation stack uses HTTP and a local PostgreSQL container. It is not a production deployment.

## AWS bootstrap prerequisites

Before Phase 6B, an operator must choose or create:

- an AWS account and region;
- a controlled hostname and DNS write access;
- an ACM certificate valid in the ALB region;
- two ECR repositories, one each for `backend` and `frontend`;
- a VPC with at least two public ALB/task subnets and private RDS subnets;
- ALB, target groups and path rules for `/api/*`, `/sanctum/*` and the frontend default;
- backend and frontend ECS services with containers named `backend` and `frontend`;
- an RDS PostgreSQL instance whose selected PostgreSQL 18 engine supports the required PostGIS extension version in the chosen region;
- a private S3 bucket for candidate documents;
- ECS execution/task roles and a GitHub OIDC deployment role;
- CloudWatch log groups with explicit retention.

Use immutable ECR tags based on the Git commit SHA. Enable ECR scan-on-push and lifecycle rules appropriate to the desired rollback window.

## Environment and secrets

`backend/.env.production.example` is the non-secret configuration inventory. Do not copy it into an image or commit a populated file.

Important production values include:

- `APP_ENV=production`, `APP_DEBUG=false`;
- identical HTTPS `APP_URL` and `FRONTEND_URL`;
- `TRUSTED_PROXIES=*` only because the backend security group accepts HTTP solely from the ALB;
- host-only `carematch_session`, `SESSION_SECURE_COOKIE=true`, HttpOnly and SameSite Lax;
- the hostname in `SANCTUM_STATEFUL_DOMAINS`, without a scheme;
- `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`;
- `LOG_CHANNEL=stderr`;
- `CANDIDATE_DOCUMENTS_DISK=candidate_documents_s3`;
- `CARE_MATCH_PUBLIC_DEMO=true` for the portfolio deployment.

Inject `APP_KEY`, database credentials and the demo password through ECS secrets. Prefer an RDS-managed Secrets Manager secret for database credentials and SSM Parameter Store SecureString for application secrets where rotation is not required. The ECS task role obtains S3 credentials through the task metadata service; do not set static `AWS_ACCESS_KEY_ID` or `AWS_SECRET_ACCESS_KEY`.

## Database, sessions and cache

RDS PostgreSQL is the source of truth. Phase 7C requires PostGIS and its migration executes `CREATE EXTENSION IF NOT EXISTS postgis`; before Phase 6B deployment, verify PostgreSQL 18 compatibility, the available PostGIS version and the migration role's extension permission on the chosen RDS engine. Local and CI use PostgreSQL 18.6 with PostGIS 3.6. This does not claim that RDS compatibility has already been verified. The standard `sessions`, `cache` and `cache_locks` tables are deployed by migrations. Database cache gives authentication rate limits a shared store across backend tasks. Local and E2E stacks continue to use file cache, and queues remain synchronous.

Run migrations as a one-off ECS task using the exact backend image being deployed:

```bash
php artisan migrate --force
```

Never run `migrate:fresh`, seeding or migration automatically in the backend service startup command. The deployment workflow stops before updating either service when the migration task exits unsuccessfully.

Laravel migrations are expected to be backward compatible with the currently serving application. If a code rollback follows a forward migration, normally roll the ECS services back while leaving the compatible schema in place. Run `migrate:rollback --force` only after reviewing the exact migration and confirming that data loss and old-code compatibility are acceptable.

## Private candidate documents

The production disk uses the Laravel S3 adapter with `visibility=private` and `throw=true`. The S3 bucket must have Block Public Access enabled, default encryption enabled and no public bucket policy. Grant the backend task role only the required object operations under the configured prefix. The application continues to generate random keys and serves downloads only through authenticated, tenant-authorised endpoints; it never returns a public S3 URL.

Candidate data and documents remain synthetic in the public portfolio environment. Malware scanning is still required before enabling real personal-document uploads.

## Public demo restrictions and mail

With `CARE_MATCH_PUBLIC_DEMO=true`, the backend rejects public registration, Organisation creation, password recovery, invitation creation and Candidate document upload/deletion with `403`. Login/logout, Dashboard, browsing/editing synthetic Candidates, Job operations, Application operations and pipeline transitions remain available. Frontend-hidden controls are convenience only; backend middleware is authoritative.

The frontend must be built with `NEXT_PUBLIC_CARE_MATCH_PUBLIC_DEMO=true` and displays: “Portfolio demo — use synthetic data only.”

The public-demo task may use `MAIL_MAILER=array` only because every email-triggering entry point is disabled server-side. Do not use `MAIL_MAILER=log` in production. Configure and verify a real provider such as SES before disabling public-demo restrictions or enabling invitation/password-reset delivery.

## Guarded demo provisioning and reset

The existing `PortfolioDemoSeeder` still refuses production. After migrations, an operator may explicitly provision the synthetic dataset inside a one-off backend task:

```bash
php artisan carematch:demo:provision \
  --confirm=PROVISION_SYNTHETIC_PUBLIC_DEMO
```

The command requires `APP_ENV=production`, `CARE_MATCH_PUBLIC_DEMO=true`, an `@example.test` identity and a 12-or-more-character bcrypt-compatible password injected as `CARE_MATCH_DEMO_PASSWORD`. It performs no truncation, `migrate:fresh` or unrelated overwrite. Conflicting records cause it to fail closed. It is not part of service startup or the normal deployment workflow.

There is no automated destructive demo reset. For routine reset, an operator should inspect the synthetic tenant, remove only clearly identified demo-created records through an audited/manual procedure, and rerun the guarded provision command. The current provisioner is repeatable only while its deterministic baseline records have not been changed incompatibly; it deliberately refuses to overwrite ambiguous records. Document each reset. Snapshot restore is reserved for disaster recovery and backup verification, not routine demo cleanup.

## GitHub OIDC deployment

Create a GitHub Environment (normally `production`) with approval protection where available. Configure these non-secret Environment variables:

- `AWS_DEPLOY_ROLE_ARN`
- `ECR_BACKEND_REPOSITORY`, `ECR_FRONTEND_REPOSITORY`
- `ECS_CLUSTER`
- `ECS_BACKEND_SERVICE`, `ECS_FRONTEND_SERVICE`
- `ECS_BACKEND_TASK_FAMILY`, `ECS_FRONTEND_TASK_FAMILY`
- `ECS_TASK_SUBNETS`, `ECS_TASK_SECURITY_GROUPS` as comma-separated IDs suitable for the AWS CLI network configuration

The AWS role trust policy must restrict `token.actions.githubusercontent.com` to this repository and protected environment. Its least-privilege permissions cover ECR pushes, ECS task-definition registration, one-off task execution/inspection, service updates and `iam:PassRole` only for the ECS roles.

Run `.github/workflows/deploy-production.yml` manually with the region and exact HTTPS URL. It fails visibly on missing inputs, OIDC authentication, image push, migration, ECS stabilization or HTTPS smoke failure. It assumes bootstrap-created ECS task definitions already contain runtime environment variables, secrets, task/execution roles, CloudWatch `awslogs` configuration, CPU/memory, port mappings and health settings.

## Logging, health and operations

Configure both ECS task definitions with the `awslogs` driver and explicit log-group retention. Laravel writes to stderr; Next.js writes to stdout/stderr. Do not enable request-body logging. ALB/application logging must not record secrets; password-reset query tokens are unavailable in public-demo mode, and invitation tokens remain browser fragments.

Minimum operational checks:

- ALB target health for both services;
- `GET https://<host>/`;
- `GET https://<host>/api/v1/health`;
- `GET https://<host>/sanctum/csrf-cookie` and expected secure cookies;
- CloudWatch log delivery;
- ECS stopped-task reasons and service event history;
- ALB 5xx and unhealthy-host metrics.

## Backup and restore

Enable RDS encryption, automated backups with a documented retention period, final snapshots on deletion and deletion protection. Take a manual snapshot before high-risk schema work. Enable S3 versioning and encryption; lifecycle rules must not contradict the documented explicit-deletion policy.

Restore verification is a Phase 6C operator exercise:

1. Restore a snapshot into a new, non-public RDS instance.
2. Attach equivalent security groups and parameter settings.
3. Point a non-production ECS task at a temporary restored credential secret.
4. Run `php artisan migrate:status` and read-only integrity/smoke checks.
5. Record snapshot identifier, timings and results.
6. Delete the temporary instance only after verification and according to the approved change process.

For disaster recovery, restore to a new instance rather than overwriting the failed instance, update the ECS secret/configuration, deploy tasks, verify migrations and complete the HTTPS smoke checklist before DNS or service cutover.

## Remaining Phase 6B/6C evidence

Repository readiness does not prove AWS deployment. Phase 6 remains incomplete until the chosen hostname serves the application over HTTPS and operators have verified ECS stabilization, secret injection, RDS connectivity, private S3 access, CloudWatch logs, public-demo restrictions, backup configuration and at least one documented restore exercise.
