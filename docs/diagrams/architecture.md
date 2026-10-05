# CareMatch Architecture Overview

This diagram shows the currently implemented local and CI architecture plus the
validated repository-side Phase 9 production target. Live HTTPS/AWS provisioning
and operational evidence remain Phase 6B/6C work.

```mermaid
flowchart TB
    browser[Browser]
    frontend[Next.js / React / TypeScript]

    subgraph backend[Laravel modular monolith]
        http[REST API and OpenAPI boundary]
        auth[Sanctum cookie session and CSRF]
        tenant[Active membership resolver and request-scoped TenantContext]

        subgraph modules[Business modules]
            identity[Identity]
            organisation[Organisation]
            candidate[Candidate]
            recruitment[Recruitment]
            compliance[Compliance catalogue and evaluator]
            matching[Matching ranking and spatial read model]
            ai[AI assistance lifecycle and provider boundary]
            audit[Audit trail]
            dashboard[Dashboard read model]
            analytics[Analytics cohort and reporting read model]
        end
    end

    postgres[(PostgreSQL 18 + PostGIS 3.6)]
    privateStorage[(Private Candidate document storage)]
    redis[(Redis shared cache and locks)]
    sqs[[SQS compliance queue]]
    worker[Laravel queue worker]
    dlq[[SQS dead-letter queue]]
    aiSqs[[SQS AI queue]]
    aiWorker[Laravel AI worker]
    aiDlq[[SQS AI dead-letter queue]]
    provider[[External AI provider]]
    ci[GitHub Actions]
    quality[PHPUnit · Vitest · PHPStan · Playwright · OpenAPI lint]

    browser -->|stateful cookie requests| frontend
    frontend -->|credentialed JSON + X-XSRF-TOKEN| http
    http --> auth
    auth --> tenant
    tenant --> organisation
    http --> identity
    tenant --> candidate
    tenant --> recruitment
    tenant --> compliance
    tenant --> matching
    tenant --> ai
    tenant --> audit
    tenant --> dashboard
    tenant --> analytics

    identity --> postgres
    organisation --> postgres
    candidate --> postgres
    recruitment --> postgres
    compliance --> postgres
    matching -->|tenant-scoped ranking, ST_Distance and ST_DWithin| postgres
    ai -->|durable requests and validated drafts only| postgres
    audit -->|append-only events and paginated reads| postgres
    dashboard -->|bounded tenant-scoped aggregation| postgres
    analytics -->|UTC cohort, funnel, daily series and medians| postgres
    candidate -->|authorised upload and download| privateStorage
    http -->|digest request ID after commit| sqs
    sqs --> worker
    worker -->|current trusted state and aggregate counts| postgres
    worker -->|bounded failures after 3 receives| dlq
    http -->|shared cache and rate limits| redis
    worker -->|distributed overlap lock| redis
    http -->|AI request ID after commit| aiSqs
    aiSqs --> aiWorker
    aiWorker -->|reload exact source IDs| postgres
    aiWorker -->|read selected private document| privateStorage
    aiWorker -->|minimal document or deterministic factors| provider
    aiWorker -->|bounded failures after 3 receives| aiDlq
    aiWorker -->|overlap lock| redis

    ci --> quality
    quality -. verifies .-> frontend
    quality -. verifies .-> backend
    quality -. verifies .-> postgres
```

## Key decisions

- The backend is one deployable modular monolith. Modules are organised first by
  business capability and then by pragmatic Clean Architecture layers.
- The Organisation route identifier is not trusted by itself. Authentication,
  active persisted membership resolution and the request-scoped `TenantContext`
  precede tenant-owned use cases.
- Candidate, Job, Application and document queries use the trusted Organisation
  identifier. PostgreSQL composite foreign keys independently reject important
  cross-tenant relationships.
- Recruitment state changes are domain-validated and transactionally locked.
  Application status updates append immutable history in the same transaction.
- Candidate document bytes live on a private Laravel disk. The API exposes only
  authorised downloads and never returns the random internal storage key.
- Dashboard is a read-only cross-module projection. It owns no Candidate or
  Recruitment records and uses four fixed tenant-scoped queries.
- Analytics is a separate read-only projection over authoritative Candidate,
  Application, Job and status-history records. It uses four fixed tenant-scoped
  PostgreSQL queries and persists no report or aggregate cache.
- Compliance owns the shared qualification catalogue and deterministic temporal
  evaluator. Candidate credentials remain in Candidate and Job requirements in
  Recruitment; tenant-safe Infrastructure read models provide bounded inputs to
  the framework-independent evaluator.
- Audit owns semantic tenant business events and an Admin-only read model.
  Business mutations and their audit insert commit together without observers
  or a generic event bus.
- Matching owns no persisted business records. Its two-query PostGIS projection
  ranks tenant Candidates by qualification, exact occupation, distance and a
  stable ID tie-breaker, and returns machine-readable factors for human review.
- Phase 8 queues only a Compliance expiry-digest request ID. PostgreSQL owns
  durable idempotency and delivery state; Redis supplies temporary coordination;
  SQS redrive owns dead-letter handling. The worker uses the backend image and
  never places invitation/reset tokens or Candidate details in a queue message.
- Phase 9 queues only an AI request ID on its dedicated timeout/DLQ topology.
  The AI module owns assistive drafts and explanations, while Candidate and
  deterministic Matching remain authoritative. Admin/Recruiter human review is
  mandatory before any extracted field reaches Candidate persistence.

See [the detailed architecture](../architecture.md) and
[the entity-relationship diagram](entity-relationship.md).
