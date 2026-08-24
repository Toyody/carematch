# CareMatch

CareMatch is a multi-tenant healthcare workforce and recruitment SaaS
designed for healthcare organisations, recruitment agencies and workforce managers.

## Overview

The platform helps organisations manage:

- job vacancies
- candidates
- applications
- recruitment pipelines
- workforce compliance
- candidate matching

## Tech Stack

### Frontend
- Next.js
- React
- TypeScript

### Backend
- Laravel
- PHP

### Database
- PostgreSQL

### Infrastructure
- Docker
- AWS
- GitHub Actions

## Architecture

CareMatch is designed as a modular monolith using pragmatic Clean Architecture.

See:
- `docs/product-requirements.md`
- `docs/architecture.md`
- `docs/database-design.md`

## Current Status

Phase 1 provides the local development and quality foundation only. Authentication,
tenant business logic and recruitment features are intentionally not implemented yet.

## Local Development

Prerequisites:

- Docker Desktop with Docker Compose v2
- GNU Make

PHP, Composer, Node.js and npm are provided by the development containers.

First-time setup:

```bash
make setup
make up
```

Local services:

- Frontend: <http://localhost:3000>
- Backend API health: <http://localhost:8000/api/v1/health>
- Laravel liveness: <http://localhost:8000/up>
- PostgreSQL: `localhost:5432`

The checked-in `.env.example` files contain local-only defaults. `make setup`
copies them only when the corresponding local environment file does not exist.

Daily commands:

```bash
make up
make logs
make ps
make down
make shell-backend
make shell-frontend
make psql
make migrate
```

`make down` preserves Docker volumes and database data.

## Tests and Quality Checks

```bash
make test
make test-backend
make test-frontend
make lint
make analyse
make build
make openapi-lint
make audit
make check
make format
```

`make check` runs formatting/linting, static analysis, tests, the Next.js
production build and OpenAPI linting. `make format` applies formatting fixes.

Direct container commands:

```bash
docker compose exec backend php artisan test
docker compose exec backend composer lint
docker compose exec backend composer analyse
docker compose exec frontend npm test
docker compose exec frontend npm run lint
docker compose exec frontend npm run typecheck
docker compose exec frontend npm run build
```

## Repository Layout

```text
backend/       Laravel REST API
frontend/      Next.js web application
docker/        Local infrastructure support files
openapi/       Versioned REST API contract
docs/          Product and architecture documentation
```

Business modules will be introduced vertically from Phase 2 under
`backend/app/Modules`. Empty module scaffolds are deliberately avoided.

## API Conventions

- Base path: `/api/v1`
- JSON field names: `snake_case`
- Timestamps: ISO-8601 UTC
- Single-resource success responses: `{ "data": ... }`
- Paginated responses: Laravel Resource `data`, `links` and `meta`
- Validation errors: Laravel JSON `message` and `errors`
- OpenAPI contract: `openapi/openapi.yaml`

Tenant-owned routes will use `/api/v1/organisations/{organisation}/...` from
Phase 2. Client-supplied `organisation_id` values will not determine ownership.
