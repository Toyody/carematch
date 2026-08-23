# CareMatch Engineering Instructions

## Project

CareMatch is a production-oriented, multi-tenant healthcare workforce and recruitment SaaS.

Before implementing or modifying business features, read:

- `docs/product-requirements.md`
- `docs/architecture.md`
- `docs/database-design.md`
- `docs/roadmap.md`

Do not make major architectural changes without explaining them first.

## Core Stack

- Backend: PHP, Laravel, REST API and OpenAPI
- Frontend: Next.js, React and TypeScript
- Database: PostgreSQL
- Local development: Docker
- Architecture: Modular Monolith and pragmatic Clean Architecture

## Module and Dependency Rules

- Organise backend business code module-first, then by layer within each module.
- Initial MVP modules are Identity, Organisation, Candidate and Recruitment.
- Do not scaffold Compliance, matching, messaging or other advanced modules before they are required.
- Dependencies must point inward. Domain code must not depend on Laravel, Eloquent, HTTP, PostgreSQL, AWS or other infrastructure.
- Business use cases belong in the Application layer; core business rules belong in Domain.
- Infrastructure adapters implement focused interfaces required by inner layers; HTTP concerns belong in Interfaces.
- Modules communicate through explicit application contracts, not another module's internal persistence implementation.
- Do not introduce generic repositories, base services, command buses or multiple DTO layers mechanically.
- Prefer Laravel conventions and simple module-local code when additional abstraction provides no concrete value.

## Laravel Conventions

- Keep controllers thin.
- Use Form Requests for HTTP validation and authorisation where appropriate.
- Use Policies and Gates for server-side resource authorisation.
- Use API Resources for response shaping.
- Do not access Eloquent directly from controllers for business operations.
- Prevent N+1 queries through explicit eager loading and query review.

## Multi-tenancy

- A user may belong to more than one organisation through organisation memberships.
- Tenant-owned API routes identify the organisation, for example `/api/v1/organisations/{organisation}/candidates`.
- Resolve the active organisation from the route and verify the authenticated user's active membership server-side.
- Never trust an `organisation_id` supplied in a request body for tenant ownership.
- Scope route binding, authorisation and queries to the resolved tenant context and fail closed outside it.
- Use database constraints to prevent cross-tenant relationships where practical.
- Every relevant feature requires negative tenant-isolation tests.
- A global Eloquent scope alone is not sufficient protection.

## Authentication and Authorisation

- The first-party web MVP uses Laravel Sanctum stateful cookie authentication.
- Configure CSRF, CORS and secure cookie settings explicitly for the deployment topology.
- Enforce authentication and authorisation server-side.
- MVP roles are fixed: `admin`, `recruiter` and `hiring_manager`.
- Roles belong to organisation memberships, not global users.
- Use the permission matrix in `docs/product-requirements.md` as the product source of truth.
- Rate-limit authentication and other abuse-sensitive endpoints.

## Security

- Validate all external input.
- Do not expose sensitive information in API responses or logs.
- Treat candidate details and documents as sensitive personal information.
- Store candidate documents privately and serve them only through authorised endpoints.
- Use random storage keys and validate file type and size.
- Do not use real personal information in demo data.
- Follow Laravel and general web security best practices.

## Database and Transactions

- Use PostgreSQL foreign keys, unique constraints, check constraints and justified indexes.
- Tenant-owned tables contain `organisation_id` unless ownership is safely and explicitly derived.
- Enforce cross-tenant integrity for applications and candidate documents with composite constraints.
- Define nullability and deletion behaviour deliberately.
- Use transactions when multiple related writes must succeed or fail together.
- Follow the MVP transaction boundaries in `docs/architecture.md`.
- Protect application status changes from concurrent updates.

## Testing

Tests are implemented with each vertical slice, not deferred to a final testing phase.

Prioritise domain tests for meaningful rules, application/use-case tests, API/feature tests, validation tests, authorisation tests, tenant-isolation tests, critical database-constraint tests and critical browser-flow tests.

Do not weaken tests simply to make an implementation pass.

## Development Workflow

Before substantial implementation:

1. Inspect the existing code and documentation.
2. Explain the proposed implementation approach.
3. Identify architectural, security and data-integrity implications.
4. Implement the smallest coherent vertical slice.
5. Run relevant tests and static analysis.
6. Report changed files and unresolved issues.

Do not introduce microservices, Kubernetes, Kafka, GraphQL, CQRS, event sourcing, Redis, SQS, PostGIS, AI features or advanced observability before a documented need exists.
