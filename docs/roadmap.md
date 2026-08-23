# CareMatch Development Roadmap

## Goal

The primary goal is to complete a portfolio-ready v1.0 before moving to Australia.

Version 1.0 must be usable as a standalone portfolio project even if no advanced features have been implemented yet.

---

## Phase 0 — Design

Status: In Progress

- [ ] Define product requirements
- [ ] Define pragmatic Clean Architecture
- [ ] Define initial database model
- [ ] Review multi-tenancy strategy
- [ ] Review authentication and authorisation strategy
- [ ] Review MVP scope with Codex
- [ ] Resolve major architectural risks

Exit criteria:

- Product requirements are internally consistent.
- Core domain boundaries are understood.
- MVP scope is sufficiently clear to begin implementation.

---

## Phase 1 — Local Development Foundation

- [ ] Initialise Laravel backend
- [ ] Initialise Next.js / React / TypeScript frontend
- [ ] Configure PostgreSQL
- [ ] Configure Docker
- [ ] Verify frontend and backend run locally
- [ ] Verify Laravel can connect to PostgreSQL

Exit criteria:

- The complete development environment can be started locally.
- Backend, frontend and database communicate correctly.

---

## Phase 2 — Identity and Multi-tenancy

- [ ] Authentication
- [ ] Login / logout
- [ ] Organisations
- [ ] Organisation memberships
- [ ] Role-based access control
- [ ] Tenant isolation
- [ ] Authorisation tests
- [ ] Tenant-isolation tests

Exit criteria:

- Authenticated users can access only resources belonging to authorised organisations.

---

## Phase 3 — Recruitment Core

### Candidates
- [ ] Candidate creation
- [ ] Candidate listing
- [ ] Candidate details
- [ ] Candidate editing
- [ ] Search
- [ ] Filtering
- [ ] Pagination

### Jobs
- [ ] Job creation
- [ ] Job listing
- [ ] Job details
- [ ] Job editing
- [ ] Search
- [ ] Filtering
- [ ] Pagination

### Applications
- [ ] Create application
- [ ] View applications
- [ ] Recruitment status workflow
- [ ] Valid status-transition rules
- [ ] Recruitment pipeline UI

Exit criteria:

A recruiter can complete the core flow:

1. Log in
2. Create a job
3. Create or review a candidate
4. Create an application
5. Move the application through recruitment stages

---

## Phase 4 — Portfolio-ready v1.0

- [ ] Dashboard
- [ ] Candidate document uploads
- [ ] Error states
- [ ] Loading states
- [ ] Empty states
- [ ] Backend automated tests
- [ ] Frontend tests
- [ ] Playwright critical-flow tests
- [ ] Static analysis
- [ ] GitHub Actions CI
- [ ] AWS deployment
- [ ] HTTPS
- [ ] Demo data
- [ ] Demo account
- [ ] Architecture diagram
- [ ] ER diagram
- [ ] Screenshots
- [ ] Production-quality README

Exit criteria:

- The application is publicly accessible.
- The primary recruitment workflow works end to end.
- CI passes.
- The repository can be shown to employers.

This is the minimum target before relocation.

---

## Phase 5 — Advanced Workforce Features

Implemented after v1.0 is stable.

- [ ] Compliance management
- [ ] Qualifications and certifications
- [ ] Expiry tracking
- [ ] Audit logging
- [ ] Candidate matching engine
- [ ] PostGIS distance matching
- [ ] Advanced analytics

---

## Phase 6 — Infrastructure and Asynchronous Processing

- [ ] Redis
- [ ] SQS
- [ ] Background workers
- [ ] Retry handling
- [ ] Dead-letter queue
- [ ] Idempotency
- [ ] Terraform
- [ ] Enhanced CloudWatch monitoring
- [ ] Performance testing

---

## Phase 7 — AI-assisted Product Features

AI must not control business-critical hiring decisions.

- [ ] CV parsing
- [ ] Structured data extraction
- [ ] Human review before persistence
- [ ] Match explanation generation

The deterministic matching engine remains the source of the match score.

---

## Non-goals

Do not introduce these unless future requirements clearly justify them:

- Microservices
- Kubernetes
- Kafka
- Event sourcing
- CQRS
- Blockchain
- Unnecessary GraphQL