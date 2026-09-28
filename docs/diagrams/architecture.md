# CareMatch Architecture Overview

This diagram shows the currently implemented local and CI architecture. Public
HTTPS hosting and AWS infrastructure remain Phase 6 work.

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
            dashboard[Dashboard read model]
        end
    end

    postgres[(PostgreSQL)]
    privateStorage[(Private Candidate document storage)]
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
    tenant --> dashboard

    identity --> postgres
    organisation --> postgres
    candidate --> postgres
    recruitment --> postgres
    dashboard -->|bounded tenant-scoped aggregation| postgres
    candidate -->|authorised upload and download| privateStorage

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

See [the detailed architecture](../architecture.md) and
[the entity-relationship diagram](entity-relationship.md).
