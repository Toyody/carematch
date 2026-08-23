# CareMatch Engineering Instructions

## Project

CareMatch is a multi-tenant healthcare workforce and recruitment SaaS.

Before implementing or modifying business features, read:

- `docs/product-requirements.md`
- `docs/architecture.md`
- `docs/database-design.md`

Do not make major architectural changes without explaining them first.

## Core Stack

Backend:
- PHP
- Laravel
- REST API

Frontend:
- Next.js
- React
- TypeScript

Database:
- PostgreSQL

Architecture:
- Modular Monolith
- Pragmatic Clean Architecture

## Clean Architecture Rules

- Dependencies must point inward.
- Domain logic must not depend on Laravel, Eloquent, HTTP, AWS or other infrastructure.
- Controllers must remain thin.
- Business use cases belong in the Application layer.
- Core business rules belong in the Domain layer.
- Infrastructure concerns belong in the Infrastructure layer.
- HTTP concerns belong in the Interface layer.
- Do not access Eloquent directly from controllers for business-critical operations.
- Do not introduce abstractions mechanically.
- Prefer maintainability over architectural ceremony.

## Multi-tenancy

CareMatch is multi-tenant.

- Organisation data must remain isolated.
- Users must never access resources belonging to another organisation.
- Tenant isolation must be enforced server-side.
- Relevant features must include tenant-isolation tests.

## Security

- Validate all external input.
- Enforce authentication and authorisation server-side.
- Do not expose sensitive information in API responses or logs.
- Follow Laravel and general web security best practices.

## Database

- Use PostgreSQL.
- Use foreign keys and appropriate constraints.
- Consider indexes for frequently queried fields.
- Avoid N+1 queries.
- Use transactions when multiple related changes must succeed or fail together.

## Testing

Prioritise:

- Domain tests
- Application/use-case tests
- API/feature tests
- Authorisation tests
- Tenant-isolation tests

Do not weaken tests simply to make an implementation pass.

## Development Workflow

Before substantial implementation:

1. Inspect the existing code and documentation.
2. Explain the proposed implementation approach.
3. Identify architectural, security and data-integrity implications.
4. Implement the smallest coherent change.
5. Run relevant tests.
6. Report changed files and unresolved issues.

Do not introduce microservices, Kubernetes, Kafka, GraphQL,
CQRS or event sourcing unless explicitly requested.