# CareMatch

[![CI](https://github.com/Toyody/carematch/actions/workflows/ci.yml/badge.svg)](https://github.com/Toyody/carematch/actions/workflows/ci.yml)

CareMatch is a portfolio-ready, multi-tenant healthcare recruitment SaaS. It demonstrates tenant-safe candidate management, job requisitions, recruitment pipelines, private candidate documents, role-based access control, and a focused operational dashboard in a modular Laravel and Next.js application.

![CareMatch organisation dashboard](docs/screenshots/organisation-dashboard.jpg)

## What this project demonstrates

- A modular monolith organised by business module, with pragmatic Clean Architecture boundaries.
- Strict organisation isolation enforced through route-derived tenant context, active membership checks, policies, scoped queries, and database constraints.
- Stateful first-party SPA authentication with Laravel Sanctum, database sessions, CSRF protection, and abuse-sensitive rate limits.
- Recruitment workflows with guarded status transitions, immutable history, and concurrency protection.
- Private candidate-document storage and authorised download endpoints.
- A typed Next.js client, accessible responsive UI, OpenAPI contract, and layered automated tests including a critical Playwright flow.

## Core features

- Global user registration, login, logout, password reset, and current-session discovery.
- Organisations with Admin, Recruiter, and Hiring Manager membership roles.
- Invitation and membership lifecycle management.
- Tenant-scoped candidate records and private document handling.
- Job creation and lifecycle management.
- Applications with pipeline transitions and auditable status history.
- An organisation dashboard with status breakdowns and recent activity.

## Portfolio screens

| Candidate management | Job lifecycle detail |
| --- | --- |
| ![Tenant-scoped candidate list](docs/screenshots/candidate-list.jpg) | ![Editable job lifecycle detail](docs/screenshots/job-detail.jpg) |

![Application recruitment pipeline](docs/screenshots/application-pipeline.jpg)

All displayed names, email addresses, organisations, locations, notes, and job descriptions are synthetic portfolio data.

## Architecture

The backend is a Laravel modular monolith. Business code is organised module-first (`Identity`, `Organisation`, `Candidate`, and `Recruitment`) and then by `Domain`, `Application`, `Infrastructure`, and `Interfaces` where those layers provide concrete value. The Next.js frontend communicates with the REST API through a small typed fetch client.

```text
Browser / Next.js SPA
        │  stateful cookies + CSRF
        ▼
Laravel REST API
  ├── Identity
  ├── Organisation
  ├── Candidate
  └── Recruitment
        │
        ├── PostgreSQL
        └── Private document storage
```

See the [architecture overview](docs/diagrams/architecture.md), [entity-relationship diagram](docs/diagrams/entity-relationship.md), and [architecture decisions](docs/architecture.md) for more detail.

### Tenant isolation

Tenant-owned routes resolve the organisation from the URL. Laravel authenticates the user before tenant resolution, and the resolver verifies an active persisted membership before providing a request-scoped tenant context. Controllers and application use cases receive trusted tenant and user identifiers; request bodies cannot select ownership, users, or roles. Composite PostgreSQL constraints protect critical cross-tenant relationships.

### Concurrency and data integrity

- Organisation creation and its initial Admin membership are committed atomically.
- Invitation acceptance locks and consumes the invitation together with membership activation.
- Application creation locks the tenant-scoped Job, verifies its persisted state is Open, and creates the Application plus initial history atomically.
- Application transitions lock the tenant-scoped Application with `FOR UPDATE`, authorise against its persisted state, apply the Domain transition, and append immutable history in one transaction.
- Uniqueness, check, foreign-key, and composite constraints remain the final integrity boundary.

### Sensitive documents

Candidate documents use cryptographically random storage keys on a private disk. Uploads accept only server-detected PDF/DOCX files up to 10 MiB. Upload metadata and storage writes are coordinated so failed operations do not leave authorised database records pointing at missing files. Downloads pass through authentication, tenant membership, policy checks, and tenant-scoped lookup; storage URLs are never exposed directly. Admins and Recruiters may write, while Hiring Managers have read-only access. Production use with real candidate documents still requires a real malware-scanning strategy; CareMatch does not simulate or claim one.

### Dashboard query design

The dashboard uses fixed aggregate and recent-activity queries instead of loading entire collections. Recent application activity is eagerly loaded with the candidate and job data required by the response, avoiding an N+1 query pattern. A representative development `EXPLAIN ANALYZE` improved the measured recent-activity query from about 35.5 ms to 0.09 ms after one query-shaped index was added; this is evidence from local test data, not a production SLA.

## Technology

- PHP 8.5, Laravel 13, Laravel Sanctum
- PostgreSQL 18
- Node.js 24, Next.js 16, React 19, TypeScript
- Docker Compose
- PHPUnit, PHPStan level 8, Laravel Pint
- Vitest, Testing Library, ESLint, Prettier
- Playwright for the critical recruitment browser flow
- OpenAPI 3.1 with Redocly linting

## Testing and quality checks

Run the complete local validation suite:

```bash
make check
```

Run the isolated browser flow:

```bash
make test-e2e
```

The checks cover backend and frontend tests, static analysis, linting and formatting, dependency audits, production frontend build, OpenAPI linting, PostgreSQL-backed constraints, tenant-isolation scenarios, and the critical end-to-end flow.

## Synthetic local demo

The portfolio seeder is deliberately opt-in. It refuses to run in production, requires an explicit password from the environment, accepts only a reserved `example.test` email address, and creates no candidate-document files.

```bash
CARE_MATCH_DEMO_PASSWORD='choose-a-local-password-of-12-plus-characters' make demo-seed
```

The default account is `demo.admin@example.test`. You can override it with `CARE_MATCH_DEMO_EMAIL`, provided it remains under `example.test`. Running the command again reuses the same deterministic demo records rather than duplicating them.

The seeded organisation contains realistic but fictional candidates, jobs, one application in each pipeline status, and coherent transition histories. Never use real personal information in demo data.

A future Phase 6 public demo would provision a dedicated synthetic account at deployment time, source credentials from deployment secrets, and use an explicitly configured reset/reseed policy. It will not reuse a developer account or commit a shared password; no reset worker is part of Phase 5.

## Local development

Requirements: Docker with Compose support and GNU Make.

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env.local
make setup
make up
```

Open:

- Frontend: [http://localhost:3000](http://localhost:3000)
- Backend health endpoint: [http://localhost:8000/api/v1/health](http://localhost:8000/api/v1/health)

Useful commands:

```bash
make up          # Start the local stack
make down        # Stop it
make test        # Backend and frontend tests
make check       # Full validation suite
make test-e2e    # Isolated Playwright stack and critical flow
make demo-seed   # Seed deterministic synthetic portfolio data
```

## API and project documentation

- [OpenAPI contract](openapi/openapi.yaml)
- [Product requirements](docs/product-requirements.md)
- [Architecture](docs/architecture.md)
- [Database design](docs/database-design.md)
- [Roadmap](docs/roadmap.md)

## Repository structure

```text
backend/            Laravel modular monolith and PHPUnit suite
frontend/           Next.js SPA and Vitest suite
e2e/                Playwright critical recruitment flow
openapi/            OpenAPI 3.1 contract and lint configuration
docs/               Product, architecture, database, diagrams, and screenshots
compose.yaml        Local application stack
compose.e2e.yaml    Isolated browser-test stack
```

## Current status

The local and CI-tested portfolio scope through Phase 5 is implemented. Production deployment, HTTPS/domain configuration, managed infrastructure, production mail and storage providers, observability, backup/restore drills, and public demo hardening are intentionally deferred to Phase 6.
