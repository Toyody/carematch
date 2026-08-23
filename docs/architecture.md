# CareMatch Architecture

## 1. Architectural Style

CareMatch uses:

- Modular Monolith
- Pragmatic Clean Architecture

The primary objective is to separate business rules from framework
and infrastructure concerns while avoiding unnecessary abstraction.

## 2. Dependency Rule

Dependencies must point inward.

Conceptually:

Interface / Presentation
        ↓
Application
        ↓
Domain

Infrastructure implements ports/interfaces required by inner layers.

The Domain layer must not depend on:

- Laravel
- Eloquent
- HTTP
- PostgreSQL
- AWS
- Redis
- SQS
- external APIs

## 3. Domain Layer

Responsible for:

- Entities
- Value Objects
- Domain Rules
- Domain Services
- Repository interfaces when justified

Examples:

- Application status transition rules
- Candidate matching rules
- Compliance rules

The Domain layer must remain framework-independent.

## 4. Application Layer

Responsible for:

- Use Cases
- Application Services
- Actions
- Commands
- DTOs where useful
- Transaction orchestration

Examples:

- CreateCandidate
- CreateJob
- SubmitApplication
- ChangeApplicationStatus

## 5. Infrastructure Layer

Responsible for:

- Eloquent
- PostgreSQL
- S3
- Redis
- SQS
- external APIs
- repository implementations

## 6. Interface Layer

Responsible for:

- Controllers
- HTTP Requests
- API Resources
- Routes
- HTTP-specific validation and mapping

Controllers should remain thin.

## 7. Domain Modules

Initial modules:

- Organisation
- Identity / Access
- Candidate
- Recruitment
- Compliance

Modules should communicate through explicit application-level boundaries.

backend/app/

Domain/
    Organisation/
    Candidate/
    Recruitment/
    Compliance/

Application/
    Organisation/
    Candidate/
    Recruitment/
    Compliance/

Infrastructure/
    Persistence/
    Storage/
    Messaging/

Interfaces/
    Http/
        Controllers/
        Requests/
        Resources/

## 8. Pragmatism

Clean Architecture must be applied pragmatically rather than mechanically.

The objective is to protect meaningful business logic from framework
and infrastructure concerns.

Simple CRUD operations do not automatically require:

- repository interfaces
- factories
- mappers
- domain services
- command buses
- multiple DTO layers

Introduce abstractions only when they provide concrete value for:

- business rules
- testability
- separation of concerns
- external dependency isolation
- maintainability

Avoid architectural ceremony.