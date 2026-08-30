# CareMatch Architecture

## 1. Architectural Style

CareMatch is a modular monolith using pragmatic Clean Architecture. It is deployed as one Laravel backend, one Next.js frontend and one PostgreSQL database.

The architecture protects meaningful business rules, tenant isolation and data integrity while preserving Laravel conventions and avoiding ceremony. Microservices, Kubernetes, Kafka, GraphQL, CQRS and event sourcing are not part of the MVP architecture.

## 2. Module-first Organisation

Business code is organised by module first and by architectural layer second:

```text
backend/app/
    Modules/
        Identity/
            Domain/
            Application/
            Infrastructure/
            Interfaces/
        Organisation/
            Domain/
            Application/
            Infrastructure/
            Interfaces/
        Candidate/
            Domain/
            Application/
            Infrastructure/
            Interfaces/
        Recruitment/
            Domain/
            Application/
            Infrastructure/
            Interfaces/
```

Laravel bootstrap, shared framework configuration and genuinely cross-cutting providers may remain in conventional Laravel locations. A generic `Shared` or `Common` module must not become a dumping ground.

Compliance, matching, asynchronous messaging and AI are future concerns and must not be represented by empty MVP modules.

## 3. Module Ownership

### Identity

Owns global users, credentials, password lifecycle, authentication and sessions. A global user may exist without any Organisation membership. Identity does not create Organisations or memberships and does not own organisation roles.

### Organisation

Owns organisations, memberships, fixed organisation roles, membership invitations and active tenant access decisions.

### Candidate

Owns tenant-scoped candidate records, profile information, document metadata and document access rules.

### Recruitment

Owns tenant-scoped jobs, applications, job state rules, application status transitions and application status history.

Recruitment refers to Candidate through an explicit application-level contract or identifier. It does not reach into Candidate's internal persistence implementation.

## 4. Layers and Dependency Rule

```text
Interfaces -> Application -> Domain
Infrastructure implements ports required by inner layers
```

### Domain

Contains framework-independent rules such as application status transitions and job state rules that affect applications. It must not depend on Laravel, Eloquent, HTTP, PostgreSQL, AWS or external APIs.

Simple database records do not require a separate Domain model merely to mirror their columns.

### Application

Contains focused use cases or Actions such as `CreateOrganisation`, `CreateCandidate`, `OpenJob`, `SubmitApplication` and `ChangeApplicationStatus`.

Application code coordinates workflow rules, Domain rules, focused persistence ports and transaction boundaries. Use one clear Action/use-case style; do not add a command bus. Introduce DTOs only at meaningful boundaries.

### Infrastructure

Contains Eloquent persistence models and query implementations, PostgreSQL-specific adapters, the Laravel transaction adapter, private document storage and future external-service adapters.

Use focused interfaces required by use cases. Do not introduce a generic or base repository.

### Interfaces

Contains controllers, Form Requests, API Resources, routes and HTTP mapping. Controllers accept validated input, invoke one use case and return an API Resource or standard response. Laravel Policies and middleware enforce resource access where appropriate.

## 5. Module Communication

- A module exposes only explicit Application contracts needed by another module.
- A module does not use another module's internal Eloquent model as an informal API.
- Cross-module database foreign keys are allowed when they protect integrity.
- Direct application-service calls are preferred for simple synchronous collaboration.
- Domain events, queues and messaging are not required for MVP workflows.

## 6. Tenant Resolution and Isolation

A global user may have memberships in multiple organisations. Tenant-owned routes use an organisation parameter:

```text
/api/v1/organisations/{organisation}/candidates
/api/v1/organisations/{organisation}/jobs
/api/v1/organisations/{organisation}/applications
```

For each request, the backend:

1. Authenticates the user.
2. Resolves the organisation from the route.
3. Verifies a non-deactivated membership for that user and organisation.
4. Creates a request-scoped tenant context.
5. Scopes route binding, authorisation and queries to that tenant.

Client-supplied `organisation_id` does not determine ownership. Create operations use the trusted tenant context.

Tenant protection is layered through membership middleware, Policies, tenant-scoped route binding and queries, application invariants, composite PostgreSQL constraints and negative isolation tests. A global Eloquent scope may be defence in depth but cannot be the only control.

Tenant-owned audit fields that identify an actor use composite membership foreign keys so the recorded actor is associated with the same organisation. Memberships are deactivated rather than deleted when historical records refer to them.

## 7. Authentication and RBAC

The first-party Next.js SPA uses Laravel Sanctum stateful cookie authentication backed by database sessions. Public registration is part of Phase 2-A and signs the newly created user in automatically. Registration does not create an Organisation or membership.

Laravel's stateful API middleware handles the SPA session and CSRF flow, and protected API routes use the standard `auth:sanctum` middleware. CSRF, CORS and secure cookie configuration must match the frontend/backend domain topology. A successful password reset invalidates all existing sessions belonging to that user.

Sanctum's standard `personal_access_tokens` infrastructure remains installed. Phase 2-A does not add `HasApiTokens` to the User model and exposes no token issuing or token-management functionality. API-token authentication remains deferred until an external API consumer creates a documented requirement.

Identity centralises email normalisation so registration, login and password reset use the same canonical email representation. Password validation is deliberately simple: at least 12 characters, confirmation and a maximum of 72 bytes for the configured bcrypt hasher. Composition rules are not added mechanically.

Email verification is deferred. Users may register and authenticate without a verified email during Phase 2-A.

MVP roles are `admin`, `recruiter` and `hiring_manager`, stored on organisation memberships. Laravel Policies implement the permission matrix in `product-requirements.md`. Frontend checks never replace server-side authorisation.

Organisation creation, memberships, RBAC, tenant resolution and tenant-isolation enforcement are outside Phase 2-A and begin only in the subsequent Organisation slice.

## 8. Transaction Boundaries

The Application layer defines atomic use cases; Infrastructure supplies the Laravel/PostgreSQL transaction implementation.

The following operations are single database transactions:

- reset a password, rotate the remember token and invalidate the user's existing sessions
- create an organisation and its initial Admin membership
- accept an invitation and create or activate its membership
- create an application and its initial status-history entry
- change an application status and append its status-history entry
- any future operation that records multiple writes as one business decision

Application status changes use a row lock or explicit version check so concurrent changes cannot silently overwrite each other.

Object storage and PostgreSQL cannot share a normal transaction. Document workflows use ordered writes and compensating cleanup so failures do not leave accessible orphan records or files.

## 9. API Conventions

- Version tenant-owned endpoints under `/api/v1`.
- Represent timestamps as ISO-8601 UTC values.
- Use stable lowercase strings for statuses and roles.
- Use a consistent validation/error response shape.
- Define allowed filters, sorts and maximum page size per list endpoint.
- Maintain OpenAPI as the REST contract.
- Do not expose credentials, storage keys or unnecessary personal information through API Resources.

## 10. Pragmatism and Laravel Conventions

Use Form Requests, Policies, Gates, API Resources, Eloquent, migrations and service providers where they clearly solve the problem.

Simple CRUD does not automatically require factories, Value Objects, domain services, mappers or multiple DTO layers. Pure Domain objects are used where meaningful rules benefit from framework independence, especially recruitment state transitions.

If an abstraction does not protect a rule, isolate a real dependency or improve testability and maintenance, do not add it.
