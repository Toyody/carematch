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

Phase 2-B2 implements this boundary for Organisation detail and settings routes. After `auth:sanctum`, tenant middleware uses the unbound numeric route identifier and authenticated user identifier to resolve an active persisted membership through an Application port. It stores a framework-independent, read-only `TenantContext` in the current HTTP request attributes. The context contains only organisation, user and membership identifiers plus the fixed persisted role; it is not a global mutable singleton and Application actions receive it explicitly.

The resolver does not first expose a globally bound Organisation model. Nonexistent Organisations, missing memberships and deactivated memberships therefore fail through the same `404` path. Once active tenant access is established, Laravel Policies return `403` when the persisted role lacks permission.

Phase 3A implements the first nested tenant-owned resource without globally binding its Eloquent model. Candidate detail and update Actions pass the trusted `TenantContext.organisationId` and numeric route Candidate ID to focused persistence ports; Infrastructure resolves them with one `organisation_id = ? AND id = ?` query. A cross-tenant Candidate ID and nonexistent Candidate therefore return the same `404`. Candidate creation takes ownership only from `TenantContext`, while Candidate Policy abilities allow every active role to view and restrict create/update to Admin and Recruiter. Recruitment nested-resource resolution remains deferred to its own vertical slice.

Client-supplied `organisation_id` does not determine ownership. Create operations use the trusted tenant context.

Tenant protection is layered through membership middleware, Policies, tenant-scoped route binding and queries, application invariants, composite PostgreSQL constraints and negative isolation tests. A global Eloquent scope may be defence in depth but cannot be the only control.

Tenant-owned audit fields that identify an actor use composite membership foreign keys so the recorded actor is associated with the same organisation. Memberships are deactivated rather than deleted when historical records refer to them.

## 7. Authentication and RBAC

The first-party Next.js SPA uses Laravel Sanctum stateful cookie authentication backed by database sessions. Public registration is part of Phase 2-A and signs the newly created user in automatically. Registration does not create an Organisation or membership.

Laravel's stateful API middleware handles the SPA session and CSRF flow, and protected API routes use the standard `auth:sanctum` middleware. CSRF, CORS and secure cookie configuration must match the frontend/backend domain topology. A successful password reset invalidates all existing sessions belonging to that user.

Sanctum's standard `personal_access_tokens` infrastructure remains installed. Phase 2-A does not add `HasApiTokens` to the User model and exposes no token issuing or token-management functionality. API-token authentication remains deferred until an external API consumer creates a documented requirement.

Identity centralises email normalisation so registration, login and password reset use the same canonical email representation. Password validation is deliberately simple: at least 12 characters, confirmation, no NUL bytes and a maximum of 72 bytes for the configured bcrypt hasher. Composition rules are not added mechanically.

Email verification is deferred. Users may register and authenticate without a verified email during Phase 2-A.

MVP roles are `admin`, `recruiter` and `hiring_manager`, stored on organisation memberships. Laravel Policies implement the permission matrix in `product-requirements.md`. Frontend checks never replace server-side authorisation.

Phase 2-B1 implements Organisation creation with an atomic initial Admin membership and lists only the authenticated user's active memberships. Phase 2-B2 adds request-scoped tenant resolution and an explicit Organisation Policy: all active membership roles may view Organisation details, while only `admin` may update Organisation settings. Phase 2-B3a adds Admin-only invitation creation, listing and revocation plus a global authenticated acceptance endpoint. Invitation acceptance does not require an existing tenant context because the invitee may not yet be a member.

Organisation Application code never imports Identity's Eloquent user. Invitation workflows depend on the small Identity Application contracts for canonical email normalisation and global-user lookup. The HTTP boundary supplies only the authenticated user identifier; the invitation supplies the trusted Organisation and role. Email verification is not required for the MVP invitation flow, so acceptance requires both the high-entropy bearer token and an authenticated account with the matching canonical email.

The raw invitation token exists only in delivery and acceptance-flow memory. The URL carries it in a browser fragment so it is not sent in the frontend HTTP request or ordinary access logs; after reading it, the frontend removes the fragment from the current browser-history entry. PostgreSQL stores a deterministic SHA-256 hash of the 256-bit random token. The default invitation lifetime is seven days and is configured once through CareMatch configuration. Laravel notifications keep delivery vendor-neutral; the log mailer is a local-development substitute only.

Phase 2-B3b adds Admin-only membership listing, fixed-role changes and deactivation. Membership rows are never physically deleted. The Application workflows receive the trusted `TenantContext`, while Infrastructure rechecks the actor's persisted active Admin membership inside the protected database operation so a stale request context cannot bypass a concurrent role change or deactivation. Member name and email enrichment uses one batch-oriented Identity Application lookup rather than importing Identity persistence or issuing one query per membership. Membership-administration UI and nested tenant-resource binding remain deferred.

Phase 2-B4 adds frontend Organisation selection. The route `/organisations/{organisation}` is the selected-workspace source of truth, and changing workspace means navigating to another authorised Organisation URL. The frontend does not persist a trusted active-tenant identifier in local storage, session storage or the authentication session. This selected Organisation is UX state only; the backend still rebuilds request-scoped `TenantContext` from the route identifier, authenticated user and active persisted membership for every tenant request. Zero-, one- and multiple-Organisation accounts use the same list and navigation model.

Phase 3A extends that URL model with `/organisations/{organisation}/candidates` and `/organisations/{organisation}/candidates/{candidate}`. Candidate list query state lives in the URL; Candidate data is not stored globally and is keyed to the authenticated user and route identifiers while displayed. Frontend role checks only shape create/edit controls and never replace backend Policy enforcement.

## 8. Transaction Boundaries

The Application layer defines atomic use cases; Infrastructure supplies the Laravel/PostgreSQL transaction implementation.

The following operations are single database transactions:

- reset a password, rotate the remember token and invalidate the user's existing sessions
- create an organisation and its initial Admin membership
- accept an invitation and create or activate its membership
- create an application and its initial status-history entry
- change an application status and append its status-history entry
- any future operation that records multiple writes as one business decision

Phase 2-A continues to use Laravel Password Broker's standard reset sequence: token validation occurs before the reset callback, and token deletion occurs after the callback completes. The User row lock serialises password, remember-token and session mutations for that user, but it does not atomically consume the reset token. Two concurrent requests that both validate before either deletes the token can therefore enter the reset callback. Strict atomic single-use under concurrent requests is an accepted Laravel framework and MVP trade-off; sequential reuse after a successful reset is rejected.

Invitation acceptance is stricter: Infrastructure starts one transaction, uses a non-locking token-hash lookup only to identify the parent Organisation, locks that Organisation row and then locks and revalidates the invitation row. It validates the unresolved and unexpired state plus canonical recipient email, locks any existing membership, creates or reactivates the membership with the persisted invitation role, and then sets `accepted_at`. Concurrent or sequential reuse observes the locked, consumed invitation and fails safely. Any membership write failure rolls back `accepted_at`.

Membership role changes and deactivation lock the Organisation row first, then lock the actor and target membership rows in ascending membership-ID order. While holding the Organisation lock, Infrastructure revalidates the actor, evaluates whether another active Admin would remain, and applies the mutation. This serialises Admin-removing decisions for one Organisation and prevents two concurrent requests from each observing the other Admin before both remove Admin status. Invitation creation now uses `Organisation -> invitation -> membership`; invitation acceptance performs a non-locking token lookup and then uses `Organisation -> invitation -> membership`. This common root ordering avoids a lock cycle with membership management. Membership listing takes a shared Organisation lock while rechecking the persisted actor and reading the tenant membership set.

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
