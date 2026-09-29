# CareMatch Development Roadmap

## Goal

The primary goal is a secure, portfolio-ready v1.0 that demonstrates a complete recruitment workflow. Advanced infrastructure and speculative features do not precede a stable core MVP.

Automated tests are delivered with each vertical slice rather than postponed to a final testing phase.

## Phase 0 — Design Decisions

Status: In Progress

- [x] Define product scope and target roles
- [x] Choose a module-first modular monolith
- [x] Define pragmatic Clean Architecture boundaries
- [x] Define URL-based tenant resolution and layered tenant isolation
- [x] Choose fixed MVP roles and an initial permission matrix
- [x] Define initial Job and Application state transitions
- [x] Define cross-tenant database constraints
- [x] Identify required transaction boundaries
- [ ] Decide candidate email uniqueness and duplicate handling
- [ ] Decide whether structured skills and employment history are in v1.0
- [ ] Decide salary/rate representation
- [x] Resolve MVP invitation verification and vendor-neutral delivery policy
- [x] Define portfolio-MVP document scanning prerequisite and explicit-deletion retention behaviour

Exit criteria:

- No unresolved decision blocks the next feature being implemented.
- Tenant, authorisation and data-integrity controls are understood before schema implementation.
- MVP scope is precise enough to avoid speculative modules or abstractions.

## Phase 1 — Local Development and Quality Foundation

- [x] Initialise Laravel backend
- [x] Initialise Next.js / React / TypeScript frontend
- [x] Configure PostgreSQL
- [x] Configure Docker
- [x] Verify frontend and backend run locally
- [x] Verify Laravel connects to PostgreSQL
- [x] Establish backend and frontend test commands
- [x] Add formatting and static-analysis commands
- [x] Add a minimal GitHub Actions workflow
- [x] Define initial `/api/v1` and OpenAPI conventions

Exit criteria:

- The complete development environment starts locally with documented commands.
- Backend, frontend and database communicate correctly.
- A minimal test and static-analysis pipeline runs in CI.

## Phase 2-A — Identity Authentication

- [x] Public user registration
- [x] Automatic sign-in after successful registration
- [x] Laravel Sanctum stateful cookie authentication
- [x] Database-backed sessions
- [x] Protected SPA API routes using `auth:sanctum`
- [x] Login and logout
- [x] Current-user endpoint
- [x] Password reset
- [x] Invalidate existing sessions after a successful password reset
- [x] Authentication rate limiting
- [x] Authentication tests
- [x] Minimal Next.js registration, login, logout, current-user and password-reset integration
- [x] OpenAPI authentication contract

Phase 2-A permits a global user to exist without an Organisation membership. Email verification is deferred. Sanctum's `personal_access_tokens` infrastructure remains installed, but CareMatch does not issue or manage API tokens and does not add `HasApiTokens` to the User model in this phase.

Password validation requires at least 12 characters, confirmation and a maximum of 72 bytes for the configured bcrypt hasher. Email normalisation is centralised and reused across Identity workflows.

Organisation creation, Organisation memberships, fixed roles, RBAC, tenant resolution, invitations and tenant-isolation enforcement are explicitly outside Phase 2-A.

Exit criteria:

- A user can register, is signed in automatically and may remain without an Organisation membership.
- A user can log in, retrieve their current identity and log out using a stateful cookie session.
- A user can request and complete a password reset, and successful reset invalidates their existing sessions.
- Protected endpoints use `auth:sanctum`; no API-token product functionality is exposed.
- Backend and minimal frontend authentication tests pass.

## Phase 2-B — Organisation and Multi-tenancy

### Organisation and Membership

- [x] Organisation creation with initial Admin membership in one transaction
- [x] Active Organisation listing for the authenticated user's memberships
- [x] Organisation selection for multi-organisation users
- [x] Membership invitation creation, listing, revocation and atomic acceptance
- [x] Admin membership listing, fixed-role changes and deactivation
- [x] Fixed membership roles enforced in the membership foundation schema
- [x] Transactional last-active-Admin protection
- [x] Laravel Policies and active-membership tenant route resolution for Organisation routes
- [x] Tenant-aware Candidate lookup for nested Organisation routes
- [x] Tenant-aware nested resource resolution for Recruitment Job routes

### Isolation verification

- [x] Authorisation tests for each role on Organisation detail/settings
- [x] Cross-tenant Organisation read tests
- [x] Cross-tenant Organisation write tests
- [x] Organisation tenant-route resolution tests
- [x] Invitation lifecycle, validation, integrity and tenant-isolation tests
- [x] Membership management authorisation, state, last-Admin and tenant-isolation tests

Exit criteria:

- Authenticated users access only authorised organisations.
- Tenant context fails closed when membership or resource ownership is invalid.
- Organisation creation and invitation acceptance are atomic.

## Phase 3 — Candidate and Job Vertical Slices

### Candidates

- [x] Candidate creation
- [x] Candidate listing and details
- [x] Candidate editing
- [x] Search, filtering, sorting and pagination
- [x] Candidate validation and authorisation tests
- [x] Candidate tenant-isolation tests

### Jobs

- [x] Job creation and editing
- [x] Job listing and details
- [x] Draft, Open, Closed and Archived lifecycle
- [x] Search, filtering, sorting and pagination
- [x] Job state-rule tests
- [x] Job authorisation and tenant-isolation tests

Exit criteria:

- Recruiters manage tenant-owned candidates and jobs.
- The Job lifecycle is ready to enforce Application rules.
- Tests ship with both vertical slices.

## Phase 4 — Applications and Recruitment Pipeline

- [x] Cross-tenant-safe Application schema and composite foreign keys
- [x] Application creation for an Open job
- [x] Duplicate candidate/job prevention
- [x] Application listing and details
- [x] Domain-level status-transition rules
- [x] Immutable application status history foundation and initial Applied entry
- [x] Atomic status update and history append
- [x] Concurrent-update protection
- [x] Role-based transition authorisation
- [x] Recruitment pipeline UI
- [x] Domain, transaction, authorisation and tenant-isolation tests

Exit criteria:

A Recruiter can:

1. Log in and select an authorised organisation.
2. Create and open a job.
3. Create or review a candidate.
4. Create an application.
5. Move it through valid recruitment stages.

The database cannot link a candidate and job from different organisations.

## Phase 5 — Portfolio-ready Product Completion

### Documents and dashboard

- [x] Resolve portfolio-MVP malware-scanning prerequisite and retention behaviour
- [x] Private candidate document upload
- [x] Authorised document download and deletion
- [x] Document validation and tenant-isolation tests
- [x] Minimal tenant dashboard and verified dashboard indexes

### User experience and quality

- [x] Candidate-document loading, validation, error and empty states
- [x] Candidate-document component/integration tests
- [x] Primary-flow loading, validation, error and empty states
- [x] Dashboard component/integration tests
- [x] Playwright critical recruitment flow with isolated PostgreSQL
- [x] Accessibility and responsive review of the primary flow
- [x] Candidate-document security and sensitive-logging review
- [x] Candidate-document query and index review
- [x] Broader Phase 5 security and sensitive-logging review
- [x] Broader Phase 5 query and N+1 review

### Portfolio assets

- [x] Safe demo data
- [x] Demo account strategy
- [x] Architecture diagram
- [x] ER diagram
- [x] Screenshots
- [x] Production-quality README

Exit criteria:

- The primary recruitment workflow works end to end.
- CI passes, including backend, frontend, static-analysis and critical-flow checks.
- Candidate documents are private and tenant-isolated.
- The repository is ready to present to employers.

## Phase 6 — Deployment

### Phase 6A — Repository-side production readiness

- [x] Define the same-origin topology for Sanctum, CORS and CSRF
- [x] Add production Laravel and Next.js container images
- [x] Add local production-image and same-origin routing validation
- [x] Add database-cache migrations for distributed backend rate limits
- [x] Add the private S3 Candidate-document adapter configuration
- [x] Add backend-enforced public-demo restrictions and frontend demo UX
- [x] Add guarded production demo provisioning without destructive reset automation
- [x] Add a manual GitHub OIDC deployment workflow
- [x] Document AWS bootstrap, migration, rollback, backup and restore procedures
- [x] Add production image validation to CI

### Phase 6B — AWS provisioning and HTTPS deployment

- [ ] Confirm the controlled production hostname and AWS region
- [ ] Provision or configure ECR, ECS Fargate, ALB, ACM, RDS and private S3
- [ ] Configure Route 53 or external DNS
- [ ] Configure deployed secrets and GitHub OIDC permissions
- [ ] Deploy successfully through the manual workflow
- [ ] Verify the application is publicly reachable over HTTPS

### Phase 6C — Production and operational verification

- [ ] Complete public browser authentication and recruitment-flow smoke tests
- [ ] Verify deployed private S3 document behaviour with synthetic data
- [ ] Verify CloudWatch log delivery and health visibility
- [ ] Verify RDS automated backup settings
- [ ] Perform and record a database restore exercise
- [ ] Verify the documented application rollback procedure

Exit criteria:

- The application is publicly accessible over HTTPS.
- No real candidate data is required for the public demo.
- Backup, secrets and deployment procedures are documented and the deployed controls are verified.

## Phase 7 — Advanced Workforce Features

### Phase 7A — Compliance & Credentials

- [x] Organisation-defined qualification catalogue with active/inactive lifecycle
- [x] Candidate qualifications and certifications, including renewals and date-only expiry
- [x] Required Job qualifications
- [x] Deterministic Candidate/Job qualification requirement coverage
- [x] Organisation-scoped expired and expiring credential view
- [x] Tenant isolation, RBAC, PostgreSQL constraints and boundary tests
- [x] Synthetic portfolio qualification data and accessible frontend workflows

### Phase 7B — Audit Trail

- [x] Broader audit logging

### Phase 7C — Deterministic Matching & PostGIS

- [ ] Candidate matching engine
- [ ] PostGIS distance matching

### Phase 7D — Advanced Analytics

- [ ] Advanced analytics

## Phase 8 — Infrastructure and Asynchronous Processing

Introduced only for measured product or operational requirements:

- [ ] Redis
- [ ] SQS
- [ ] Background workers
- [ ] Retry and dead-letter handling
- [ ] Idempotency
- [ ] Terraform
- [ ] Enhanced CloudWatch monitoring
- [ ] Performance testing

## Phase 9 — AI-assisted Product Features

AI must not control business-critical hiring decisions.

- [ ] CV parsing
- [ ] Structured data extraction
- [ ] Human review before persistence
- [ ] Match explanation generation

A deterministic and reviewable process remains the source of any match score.

## Non-goals

Do not introduce these unless future requirements clearly justify them:

- Microservices
- Kubernetes
- Kafka
- Event sourcing
- CQRS
- Blockchain
- Unnecessary GraphQL
- Generic repository frameworks
- Command buses without a demonstrated need
