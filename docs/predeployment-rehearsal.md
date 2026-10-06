# Offline Pre-deployment Rehearsal

## Scope and safety boundary

This rehearsal provides repository-side and disposable local evidence before
Phase 6B. It uses synthetic `example.test` data, isolated Docker Compose project
names and temporary volumes/directories. It does not use AWS credentials, query
AWS, run `terraform apply`, call an external AI provider or mutate the normal
developer database.

Passing these checks does **not** verify RDS snapshots, AWS S3/IAM, ECS rollback,
ALB/HTTPS, deployed secrets, CloudWatch delivery, DNS or ACM. Those remain Phase
6B/6C evidence.

## Commands

The reasonably fast zero-AWS preflight is:

```bash
make predeploy-check
```

It runs deployment/static-secret consistency checks, Terraform
`fmt/init -backend=false/validate/test`, OpenAPI linting and production-image
validation. The heavier drills remain explicit:

```bash
make test-backup-restore
make test-storage
make test-rollback
make test-failures
make test-async
```

`test-backup-restore` and `test-storage` are suitable for CI. The two-version
rollback build and deliberate dependency-stop rehearsal remain manual because
they build production images and repeatedly replace or stop services.

## PostgreSQL/PostGIS logical restore

`make test-backup-restore` starts separate source and restore containers using
PostgreSQL 18/PostGIS 3.6. The source is migrated and populated by the guarded
`PredeploymentRehearsalSeeder`, which reuses deterministic portfolio data and
adds synthetic Candidate-document metadata plus queued Compliance and AI request
state. It creates no real document bytes and calls no provider.

The drill records a non-sensitive baseline of migration/table counts, orphan
checks, PostGIS presence/version and a representative `ST_DWithin` result. It
creates a custom-format logical backup with `pg_dump`, restores it with
`pg_restore` into a separate database created from `template0`, compares the
baselines byte-for-byte, and runs Laravel `migrate:status` against the restored
database. Dumps and volumes are temporary and cleaned on success or failure.

This validates a local logical restore shape only. Phase 6C must still restore a
real RDS snapshot into a temporary non-public RDS instance and record the result.

## Forward-schema application rollback

`make test-rollback` defaults `ROLLBACK_REF` to `f94ada7`, the merged Phase 8
release immediately before Phase 9. An operator may select another ancestor:

```bash
ROLLBACK_REF=<ancestor-ref> make test-rollback
```

The old source is materialised with `git archive` into a temporary build context;
the active working tree is never checked out or changed. The drill builds old
and current production images, applies old migrations and synthetic seed data,
smokes stable health/authentication/Organisation/Candidate/Job/Application/
Matching paths, applies current forward migrations, smokes current code, then
starts the old image against the forward-migrated database. An absent Phase 9
route on old code is expected. No reverse migration runs.

This proves local application/schema compatibility for the selected commits. It
does not prove ECR image retention, ECS permissions/stabilisation, ALB target
transition or deployed-secret compatibility.

## S3-compatible Candidate documents

`make test-storage` starts a disposable, authenticated SeaweedFS 4.48 S3 API.
Synthetic credentials exist only in the test overlay. The application uses the
normal Laravel/Flysystem S3 adapter through environment-driven endpoint and
path-style options; production defaults remain AWS endpoints with path-style
disabled.

The integration creates a private bucket and verifies Admin/Recruiter upload,
opaque storage keys, no URL/key response exposure, anonymous object denial,
authorised streaming download, Hiring Manager write denial, cross-tenant denial,
delete and post-delete failure. It then points the adapter at an unreachable
endpoint, verifies a generic error and no committed metadata, and reruns the
successful lifecycle after recovery. This does not verify AWS Block Public
Access, task-role IAM, AWS encryption or live S3 networking.

## Terraform invariants and deployment consistency

`make test-terraform` pins Terraform 1.14.6 in Docker and runs the native test
framework with a mocked AWS provider. The plan assertions cover:

- private data subnets, non-public encrypted RDS, retention/deletion protection
  and the explicit Single-AZ trade-off;
- private encrypted/authenticated Redis;
- S3 Block Public Access, AES256 encryption, versioning and no force destroy;
- encrypted Compliance/AI queues, DLQs, receive counts and visibility/worker
  timeout ordering;
- HTTP-only ALB registration, worker topology and disabled-by-default AI worker;
- workload-specific SQS/S3 IAM, read-only AI document access, constrained
  `iam:PassRole` and repository/environment-scoped GitHub OIDC trust;
- secret references rather than plaintext task credentials; and
- log retention plus queue, DLQ, RDS and Redis alarm representation.

`make deployment-consistency` additionally checks ignored state/dumps/env files,
credential-shaped tracked content, deployment output/container names, one-off
migration wiring, public-demo frontend/backend agreement, queue/worker timeout
contracts and that local validation entry points do not invoke Terraform apply.
Mocked/offline evaluation does not prove that AWS accepts every regional engine
or service combination.

## Controlled failure and recovery matrix

| Dependency | Evidence | `/up` | User-visible effect | Recovery |
| --- | --- | --- | --- | --- |
| PostgreSQL | Real local production image + PostGIS container | Remains 200 by design | DB/session request returns generic 500 with `APP_DEBUG=false` | Restart DB; existing persisted user can authenticate again without rebuilding the app |
| Redis | Real local production image + Redis | Remains 200 | Redis-backed login rate-limit precheck fails closed with generic 500 | Restart Redis; normal generic 401 credential response resumes |
| S3 protocol | SeaweedFS 4.48 via Flysystem S3 adapter | Not part of the storage test | Upload returns generic 500; no successful metadata row | Restore endpoint; complete private lifecycle succeeds again |
| SQS | ElasticMQ 1.7.1 in existing `make test-async` | Not applicable | Retryable work remains queued; final failures are persisted safely and redriven | Same idempotency key safely redispatches the single logical request; real DLQ redrive is asserted |
| External AI provider | Existing fake-provider and retry tests only | Not applicable | Safe permanent code or retryable failure; no raw provider error | Real connectivity deliberately untested; guarded provider smoke remains optional |

The liveness endpoint is intentionally shallow and these tests do not claim high
availability or automatic failover. Worker interruption is not duplicated here:
the existing real ElasticMQ integration already proves repeated receive/redrive,
terminal state before final rethrow and DLQ arrival for both asynchronous queues.

## Remaining live prerequisites

Phase 6B still requires a controlled hostname/region, live account validation,
billable provisioning, secret injection, HTTPS deployment and public smoke.
Phase 6C still requires real private-S3 behaviour, CloudWatch/log/alarm delivery,
RDS backup settings and snapshot restore, and ECS rollback evidence. No offline
check should be cited as completing either phase.
