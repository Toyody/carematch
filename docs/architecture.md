# CareMatch Architecture

## 1. Architectural Style

CareMatch is a modular monolith using pragmatic Clean Architecture. The local and CI system runs as one Laravel backend, one Next.js frontend and one PostgreSQL database. Phase 6A adds repository-side production readiness; actual AWS deployment and operational verification remain Phase 6B/6C work.

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
        Dashboard/
            Application/
            Infrastructure/
            Interfaces/
        Analytics/
            Application/
            Infrastructure/
            Interfaces/
        Audit/
            Application/
            Infrastructure/
            Interfaces/
        Matching/
            Domain/
            Application/
            Infrastructure/
            Interfaces/
```

Laravel bootstrap, shared framework configuration and genuinely cross-cutting providers may remain in conventional Laravel locations. A generic `Shared` or `Common` module must not become a dumping ground.

Phase 7A introduces a real Compliance module because it owns the shared Organisation qualification catalogue, date-based expiry semantics and deterministic Candidate/Job requirement evaluator. Candidate credentials remain owned by Candidate and Job requirements remain owned by Recruitment. Their Infrastructure adapters compose persisted data through focused Compliance Application contracts; Application and Domain code do not import another module's Eloquent models. Asynchronous messaging and AI remain future concerns and must not be represented by empty modules.

Phase 7B introduces Audit as a real module. It owns the structured recording
contract, append-only persistence and Admin-only tenant read API. Business
modules explicitly record semantic events at mutation boundaries; no generic
event bus, Eloquent observer, request logger or arbitrary metadata sink is used.

Phase 7C introduces Matching because it owns the Job-to-Candidate ranking rule,
match result DTOs, tenant-safe use case and PostgreSQL/PostGIS read adapter. It
owns no Candidate, Job, qualification, Application or persisted match record.
Its Application layer depends on one focused read-model port and receives
`TenantContext` explicitly; its PostgreSQL adapter performs the cross-module
projection without importing another module's Eloquent model.

Phase 7D introduces Analytics as a read-only module because it owns explicit UTC
reporting-period, Application-cohort, funnel, time-to-stage and bounded Job
aggregate semantics. It owns no transactional records and adds no analytics
table. Its Application layer depends on one focused read-model port; its
PostgreSQL adapter aggregates Candidate and Recruitment tables using only the
trusted Organisation identifier. No Domain layer is added because these report
definitions do not currently require reusable stateful domain behaviour.

Qualification coverage is calculated in bounded reads: the tenant-scoped Job and Candidate are verified, required definitions are loaded in one query, and relevant Candidate evidence is loaded in one query. The framework-independent evaluator receives those records plus an explicit UTC date and warning threshold. No compliance rule is duplicated in React, and no global Candidate compliance flag is stored.

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

### Dashboard

Owns no business records. It is a read-only cross-module projection for the
Organisation workspace. Its Application action depends on one focused read-model
port, and its PostgreSQL adapter aggregates Candidate and Recruitment tables using
trusted tenant identifiers. It does not expose a generic reporting framework or
move Candidate, Job, Application or history ownership out of their modules.

### Analytics

Owns the deeper operational recruitment report and no business records. It is
separate from Dashboard: Dashboard answers current-volume and recent-activity
questions, while Analytics reports an explicit `applied_at` cohort over a UTC
period and its later recorded progression. Candidate and Recruitment retain
their tables and mutation rules. Analytics returns aggregate Job identity only,
never Candidate identity, actor productivity or inferred personal attributes.

### Audit

Owns Organisation-scoped audit-event semantics, persistence and querying. Other
modules depend only on the focused `AuditRecorder` Application contract and
provide trusted tenant/actor identifiers plus allow-listed metadata. Actor
display is batch-enriched through the existing Identity Application lookup.

### Matching

Owns deterministic, explainable matching policy and the read-only Job match API.
The authoritative order is qualification coverage, exact normalised occupation,
known distance and Candidate ID. The implementation deliberately has no numeric
score, cache table, generic search framework, external geocoder or AI component.

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

Phase 3A implements the first nested tenant-owned resource without globally binding its Eloquent model. Candidate detail and update Actions pass the trusted `TenantContext.organisationId` and numeric route Candidate ID to focused persistence ports; Infrastructure resolves them with one `organisation_id = ? AND id = ?` query. A cross-tenant Candidate ID and nonexistent Candidate therefore return the same `404`. Candidate creation takes ownership only from `TenantContext`, while Candidate Policy abilities allow every active role to view and restrict create/update to Admin and Recruiter.

Phase 3B applies the same resolution pattern to Recruitment Jobs. Job Application Actions receive `TenantContext` explicitly and depend on focused create, list, detail, update and lifecycle ports; they do not import Laravel HTTP or Eloquent. Infrastructure scopes every nested Job query by both trusted Organisation ID and Job ID. Job lifecycle rules are a small framework-independent `JobStatus` enum rather than a column-mirroring aggregate. Admin and Recruiter may write; Hiring Manager is read-only. Profile update input excludes `status`, and the three server-selected lifecycle endpoints are the only normal status mutation paths.

Client-supplied `organisation_id` does not determine ownership. Create operations use the trusted tenant context.

Tenant protection is layered through membership middleware, Policies, tenant-scoped route binding and queries, application invariants, composite PostgreSQL constraints and negative isolation tests. A global Eloquent scope may be defence in depth but cannot be the only control.

Tenant-owned audit fields that identify an actor use composite membership foreign keys so the recorded actor is associated with the same organisation. Memberships are deactivated rather than deleted when historical records refer to them.

The Phase 5B dashboard uses the same tenant boundary. `auth:sanctum` and tenant
resolution run before its controller, and the Application action receives the
request-scoped `TenantContext` explicitly. All active roles may read it. The
adapter applies `organisation_id` inside each count, aggregation and recent-history
query; it never aggregates globally and filters afterward. Recent activity joins
Candidate and Job identity in the same bounded query, avoiding per-item lookups.

The Phase 7C match endpoint follows the same tenant chain: `auth:sanctum`, active
membership resolution, Policy, then an explicit `TenantContext` passed to the
Application action. The adapter resolves the Job by trusted Organisation and Job
IDs and filters Candidates by that same Organisation inside SQL. Cross-tenant and
missing Jobs share `404` semantics, and client input cannot select an
Organisation or Candidate pool.

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

Phase 3B adds the equivalent Job routes and keeps Job list filters, sort and page
in the URL. Loaded Job state is keyed by authenticated user, Organisation and Job
route identifiers. The shared credentialed fetch/CSRF client remains the only
browser transport; frontend role and status checks shape controls but backend
middleware, Policies, tenant-scoped queries and Domain rules remain authoritative.

Phase 4 extends Recruitment with Application creation, listing, detail,
explicit pipeline transitions and status-history reads. Recruitment never imports Candidate Eloquent persistence. It
uses the batch-capable `CandidateReferenceLookup` Application contract for
tenant-scoped Candidate existence and compact name summaries. Application list
enrichment performs one Candidate batch query rather than one lookup per row.
Job summaries remain internal to Recruitment and are selected in the
tenant-scoped Application query.

Application ownership and actor identity come only from `TenantContext`.
Nested Application detail uses Organisation and Application ID together, while
creation locks a Job using Organisation and Job ID together before checking the
persisted Open status. Composite PostgreSQL foreign keys independently prevent
cross-tenant Candidate, Job, and actor references. The framework-independent
`ApplicationStatus` enum owns the exact transition graph. There is no generic
Application status PATCH; status changes use the explicit transition use case.
Frontend Application state is keyed by authenticated user and route identifiers,
with filters and pagination stored in the URL. The detail screen reads the
append-only timeline and derives role/status controls only for UX; backend
authorisation remains authoritative and a `409` causes detail and history to be
reloaded from the server.

Application Policy provides broad transition eligibility for all three fixed
roles. The persistence adapter then locks the tenant-scoped Application and uses
a small Application-layer permission rule against the persisted current status,
trusted membership role and requested target. Admin and Recruiter may attempt
the full Domain graph. Hiring Manager is authorised only for Interview to Offer
or Interview to Rejected. Role/state denial is `403`; a role-authorised request
that conflicts with the Domain graph is `409`.

## 8. Transaction Boundaries

The Application layer defines atomic use cases; Infrastructure supplies the Laravel/PostgreSQL transaction implementation.

The following operations are single database transactions:

- reset a password, rotate the remember token and invalidate the user's existing sessions
- create an organisation and its initial Admin membership
- accept an invitation and create or activate its membership
- transition a Job after locking and re-reading its tenant-scoped current row
- create an application and its initial status-history entry
- change an application status and append its status-history entry
- write each required database-backed business mutation and its audit event
- any future operation that records multiple writes as one business decision

Audit insertion occurs after the protected state mutation while the existing
transaction and lock order remain in force. An audit insert failure rolls back
the corresponding database mutation. Single-row mutations now use a transaction
because the audit row is a second required write. Audit insertion acquires no
additional business-row locks. Organisation creation records only after the
initial Admin membership exists; invitation acceptance retains the
`Organisation -> invitation -> membership` lock order.

Candidate-document upload writes the object first, then atomically writes
metadata and its audit event, compensating with object deletion on database
failure. Delete removes the object first, then atomically deletes metadata and
appends the event. If that transaction fails, sensitive bytes are not restored
and the inaccessible metadata row may require reconciliation.

Phase 2-A continues to use Laravel Password Broker's standard reset sequence: token validation occurs before the reset callback, and token deletion occurs after the callback completes. The User row lock serialises password, remember-token and session mutations for that user, but it does not atomically consume the reset token. Two concurrent requests that both validate before either deletes the token can therefore enter the reset callback. Strict atomic single-use under concurrent requests is an accepted Laravel framework and MVP trade-off; sequential reuse after a successful reset is rejected.

Invitation acceptance is stricter: Infrastructure starts one transaction, uses a non-locking token-hash lookup only to identify the parent Organisation, locks that Organisation row and then locks and revalidates the invitation row. It validates the unresolved and unexpired state plus canonical recipient email, locks any existing membership, creates or reactivates the membership with the persisted invitation role, and then sets `accepted_at`. Concurrent or sequential reuse observes the locked, consumed invitation and fails safely. Any membership write failure rolls back `accepted_at`.

Membership role changes and deactivation lock the Organisation row first, then lock the actor and target membership rows in ascending membership-ID order. While holding the Organisation lock, Infrastructure revalidates the actor, evaluates whether another active Admin would remain, and applies the mutation. This serialises Admin-removing decisions for one Organisation and prevents two concurrent requests from each observing the other Admin before both remove Admin status. Invitation creation now uses `Organisation -> invitation -> membership`; invitation acceptance performs a non-locking token lookup and then uses `Organisation -> invitation -> membership`. This common root ordering avoids a lock cycle with membership management. Membership listing takes a shared Organisation lock while rechecking the persisted actor and reading the tenant membership set.

Job lifecycle mutation starts a database transaction, resolves the Job by trusted
Organisation and Job IDs with `FOR UPDATE`, evaluates the transition against the
locked persisted status, and writes the new status. This serialises competing
transitions and makes a later incompatible request fail with `409` instead of
silently overwriting. Profile editing uses a separate path and cannot mutate
status; opening and closing do not implicitly rewrite profile dates.

Application creation uses the same Job-row lock as Job lifecycle transitions.
Inside one transaction it resolves and locks the tenant-scoped Job, confirms its
persisted status is Open, validates the Candidate through the Candidate module's
Application contract, inserts the Application, and appends the initial
`null -> applied` history row. Competing close/archive and create operations are
therefore serialised on the same Job row. Concurrent duplicate creation is
settled by the named `(organisation_id, job_id, candidate_id)` unique constraint;
only that constraint is translated to the duplicate-Application `409` contract.

Application status changes start one transaction and select the Application by
trusted Organisation and route ID with `FOR UPDATE`. Exact role/state
authorisation and Domain validation occur only after that lock. The status
update and one new history row then commit together. A competing request waits
and evaluates against the newly persisted status, so it cannot blindly apply a
decision made from stale frontend state. This is serialisation of transition
decisions, not an optimistic client-version contract.

Object storage and PostgreSQL cannot share a normal transaction. Phase 5A keeps
Candidate document contents on a dedicated private Laravel filesystem disk and
metadata in PostgreSQL. Upload validates the server-detected MIME and size,
generates a random 32-byte key, writes the private object, and then inserts
metadata. If metadata persistence fails, the action deletes the just-written
object as compensation. If storage fails, metadata is never inserted. This is an
ordered, compensated workflow rather than distributed atomicity.
The local PHP upload and request ceilings remain slightly above the 10 MiB
product limit so oversized multipart requests reach Laravel validation; the
application validation rule remains the exact size authority.

Deletion removes the private object before deleting metadata. A storage deletion
failure leaves metadata unchanged and returns a generic internal error. A later
metadata deletion failure may leave an inaccessible metadata row whose object is
already gone; downloads then return a generic storage-inconsistency error, and a
retry can reconcile the row. This ordering favours immediate removal of sensitive
content over metadata/file atomicity and does not justify an outbox or queue for
the portfolio MVP.

Document Application actions depend only on focused metadata and storage ports.
Laravel filesystem and Eloquent implementations remain in Candidate
Infrastructure; multipart requests, Policies, Resources and attachment responses
remain in Interfaces. No separate Document module or mirrored Domain entity is
introduced because document metadata has no independent Domain behaviour.

## 9. API Conventions

- Version tenant-owned endpoints under `/api/v1`.
- Represent timestamps as ISO-8601 UTC values.
- Use stable lowercase strings for statuses and roles.
- Use a consistent validation/error response shape.
- Define allowed filters, sorts and maximum page size per list endpoint.
- Maintain OpenAPI as the REST contract.
- Do not expose credentials, storage keys or unnecessary personal information through API Resources.

## 10. Read-model and query strategy

The Audit list is tenant-scoped and paginated at 20 rows by default with a hard
maximum of 100. It sorts by `occurred_at DESC, id DESC`, applies only documented
exact/date filters, and resolves actor IDs in one Identity lookup rather than
one query per event. Subject rendering remains ID-based so deleted records do
not invalidate history.

Audit metadata is allow-listed per event. It may contain controlled state/role
transitions, changed field names and internal IDs; it never stores request
payloads, snapshots, tokens, notes, credential numbers, document names, storage
keys or document content. `application_status_history` remains the authoritative
workflow timeline and keeps its optional note separately.

No production audit-retention period is approved. No purge is implemented, and
retention must be chosen before real sensitive Candidate data is used. Phase 7B
assumes authenticated human actors; a future worker/system actor requires an
explicit extension instead of a fabricated membership.

The Phase 5B Dashboard uses four fixed PostgreSQL queries: one tenant Candidate
count, one tenant Open Job count, one grouped Application-status count and one
bounded latest-five status-history query with Candidate and Job enrichment. It
does not load collections to count them, issue one query per status or enrich
activity with N+1 lookups. Representative `EXPLAIN ANALYZE` inspection determines
whether an index is added; indexes are not inferred mechanically from API fields.

Matching uses two database queries regardless of Candidate count: one scoped Job
existence/coordinate query and one set-based query for Candidate factors,
qualification aggregation, existing Application status, ranking and pagination.
`row_number()` establishes global deterministic rank before page slicing.
`ST_Distance` returns geography metres converted to kilometres; optional radius
filtering uses `ST_DWithin`. React only renders returned factors and does not
reimplement ranking. No match read creates an Audit event.

Analytics uses four fixed PostgreSQL queries regardless of Candidate, Job or
Application count. One query returns summary, reached-stage funnel and current
status counts; one uses `generate_series` for zero-filled UTC daily Application
volume; one calculates Interview/Hired medians with `percentile_cont(0.5)` and
sample sizes; one returns at most ten set-aggregated Job rows. Every CTE and join
is tenant-scoped. Stage timestamps are the first matching immutable history row,
not `applications.updated_at`; negative durations are excluded. React renders
the backend result and does not reconstruct cohort or funnel semantics. Analytics
reads do not create Audit events.

Primary-flow query review also verifies that existing list/detail/document paths
remain tenant-scoped, bounded where pagination applies, and batch-enriched where
cross-module Candidate summaries are needed. Exact query-count tests protect only
stable, intentional properties rather than Laravel internals.

The Phase 5B application-level logging review found no deliberate request-body,
session/CSRF cookie, password, reset-token, invitation-token, Candidate document
content or private storage-key logging in the primary flow. The local log mailer
remains an explicit development-only exception because it substitutes for email
delivery. Production reverse-proxy/runtime access-log configuration is not yet
selected; Phase 6 must ensure password-reset query strings and other sensitive URL
material are omitted or redacted. This review does not claim that deployment-level
logging is already operationally configured.

## 11. Pragmatism and Laravel Conventions

Use Form Requests, Policies, Gates, API Resources, Eloquent, migrations and service providers where they clearly solve the problem.

Simple CRUD does not automatically require factories, Value Objects, domain services, mappers or multiple DTO layers. Pure Domain objects are used where meaningful rules benefit from framework independence, especially recruitment state transitions.

If an abstraction does not protect a rule, isolate a real dependency or improve testability and maintenance, do not add it.

## 12. Portfolio diagrams

The recruiter-readable [architecture overview](diagrams/architecture.md) shows
the current runtime and module boundaries. The
[entity-relationship diagram](diagrams/entity-relationship.md) is derived from
the implemented migrations. Neither diagram presents future AWS infrastructure
as current functionality.
