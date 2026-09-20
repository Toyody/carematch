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
- [ ] Decide email verification and invitation delivery
- [ ] Decide document malware scanning and retention policy before production use

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
- [ ] Organisation selection for multi-organisation users
- [ ] Membership invitation lifecycle
- [x] Fixed membership roles enforced in the membership foundation schema
- [ ] Last-Admin protection
- [x] Laravel Policies and active-membership tenant route resolution for Organisation routes
- [ ] Tenant-aware nested resource binding for Candidate and Recruitment routes

### Isolation verification

- [x] Authorisation tests for each role on Organisation detail/settings
- [x] Cross-tenant Organisation read tests
- [x] Cross-tenant Organisation write tests
- [x] Organisation tenant-route resolution tests
- [ ] Membership lifecycle and validation tests

Exit criteria:

- Authenticated users access only authorised organisations.
- Tenant context fails closed when membership or resource ownership is invalid.
- Organisation creation and invitation acceptance are atomic.

## Phase 3 — Candidate and Job Vertical Slices

### Candidates

- [ ] Candidate creation
- [ ] Candidate listing and details
- [ ] Candidate editing
- [ ] Search, filtering, sorting and pagination
- [ ] Candidate validation and authorisation tests
- [ ] Candidate tenant-isolation tests

### Jobs

- [ ] Job creation and editing
- [ ] Job listing and details
- [ ] Draft, Open, Closed and Archived lifecycle
- [ ] Search, filtering, sorting and pagination
- [ ] Job state-rule tests
- [ ] Job authorisation and tenant-isolation tests

Exit criteria:

- Recruiters manage tenant-owned candidates and jobs.
- The Job lifecycle is ready to enforce Application rules.
- Tests ship with both vertical slices.

## Phase 4 — Applications and Recruitment Pipeline

- [ ] Cross-tenant-safe Application schema and composite foreign keys
- [ ] Application creation for an Open job
- [ ] Duplicate candidate/job prevention
- [ ] Application listing and details
- [ ] Domain-level status-transition rules
- [ ] Immutable application status history
- [ ] Atomic status update and history append
- [ ] Concurrent-update protection
- [ ] Role-based transition authorisation
- [ ] Recruitment pipeline UI
- [ ] Domain, transaction, authorisation and tenant-isolation tests

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

- [ ] Resolve malware-scanning and retention decisions
- [ ] Private candidate document upload
- [ ] Authorised document download and deletion
- [ ] Document validation and tenant-isolation tests
- [ ] Minimal dashboard and verified dashboard indexes

### User experience and quality

- [ ] Loading, validation, error and empty states
- [ ] Frontend component/integration tests
- [ ] Playwright critical recruitment flow
- [ ] Accessibility review of the primary flow
- [ ] Security and sensitive-logging review
- [ ] Query and N+1 review

### Portfolio assets

- [ ] Safe demo data
- [ ] Demo account strategy
- [ ] Architecture diagram
- [ ] ER diagram
- [ ] Screenshots
- [ ] Production-quality README

Exit criteria:

- The primary recruitment workflow works end to end.
- CI passes, including backend, frontend, static-analysis and critical-flow checks.
- Candidate documents are private and tenant-isolated.
- The repository is ready to present to employers.

## Phase 6 — Deployment

- [ ] Confirm production domain topology for Sanctum, CORS and CSRF
- [ ] AWS deployment
- [ ] HTTPS
- [ ] Secrets management
- [ ] Database backup and restore procedure
- [ ] Production migrations and rollback procedure
- [ ] Basic health checks and operational logging

Exit criteria:

- The application is publicly accessible over HTTPS.
- No real candidate data is required for the public demo.
- Backup, secrets and deployment procedures are documented.

## Phase 7 — Advanced Workforce Features

Implemented only after v1.0 is stable:

- [ ] Compliance management
- [ ] Qualifications and certifications
- [ ] Expiry tracking
- [ ] Broader audit logging
- [ ] Candidate matching engine
- [ ] PostGIS distance matching
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
