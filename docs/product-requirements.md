# CareMatch Product Requirements

## 1. Product Overview

CareMatch is a multi-tenant healthcare workforce and recruitment management platform. Healthcare organisations and recruitment agencies use separate tenant workspaces to manage candidates, vacancies and recruitment workflows.

Candidate and recruitment data belongs to one organisation. A global user may belong to multiple organisations, but access in one organisation never grants access to another.

## 2. Target Users and MVP Permissions

MVP roles are fixed and assigned per organisation membership.

| Capability | Admin | Recruiter | Hiring Manager |
| --- | --- | --- | --- |
| View organisation | Yes | Yes | Yes |
| Edit organisation settings | Yes | No | No |
| Invite/remove members | Yes | No | No |
| Assign fixed roles | Yes | No | No |
| View candidates, jobs and applications | Yes | Yes | Yes |
| Create/edit candidates | Yes | Yes | No |
| Create/edit/open/close jobs | Yes | Yes | No |
| Create applications | Yes | Yes | No |
| Move applications through recruiter-owned stages | Yes | Yes | Limited |
| Move an Interview application to Offer or Rejected | Yes | Yes | Yes |
| Upload/delete candidate documents | Yes | Yes | No |

Hiring Manager participation in the MVP consists of reviewing tenant records and deciding whether an application at Interview moves to Offer or Rejected. Comments and collaborative review threads are not part of the MVP.

An Admin cannot remove or demote the organisation's final Admin.

## 3. Authentication and Organisation Access

The first-party Next.js SPA uses Laravel Sanctum stateful cookie authentication. Public user registration is included in Phase 2-A. A successful registration creates a global user, starts a database-backed session and signs that user in automatically.

A global user may exist without any Organisation membership. Registration does not create an Organisation or membership, and an authenticated user without a membership cannot access tenant-owned operations. Phase 2-B1 adds Organisation creation with an atomic initial Admin membership and listing of the authenticated user's active Organisation memberships. Phase 2-B2 establishes active-membership tenant resolution for Organisation detail and settings routes: all active roles may view an Organisation, while only an Admin may update its name. Phase 2-B3a adds Admin-managed, seven-day Organisation invitations and atomic membership acceptance. Phase 2-B3b adds Admin-only membership history listing, fixed-role changes and membership deactivation. Phase 2-B4 adds frontend Organisation selection for users with zero, one or multiple active memberships. Membership-administration UI remains later work.

The MVP supports:

- public user registration with automatic sign-in
- login and logout
- retrieval of the currently authenticated user
- password reset
- authentication rate limiting
- organisation creation
- automatic Admin membership for the organisation creator
- time-limited organisation invitations
- organisation selection for users with multiple memberships
- membership deactivation that preserves historical actor attribution

Protected SPA API routes use Laravel's standard `auth:sanctum` middleware. Sanctum's `personal_access_tokens` infrastructure remains installed, but CareMatch does not issue, list, revoke or otherwise manage API tokens in Phase 2-A. Browser authentication remains cookie-session only from the product's perspective.

Email addresses are normalised through one central Identity component before authentication or persistence. Passwords require at least 12 characters, must be confirmed, must not contain a NUL byte and must not exceed 72 bytes so they remain compatible with the configured bcrypt hasher. Arbitrary composition rules are not required unless Laravel's supported defaults later justify them. A successful password reset changes the password and invalidates the user's existing sessions.

Email verification is not required for MVP invitation acceptance. Acceptance requires possession of the invitation bearer token and an authenticated account whose canonical email matches the invitation email. The invitation determines the Organisation and fixed role; the client cannot override them. Invitation email uses Laravel's mail/notification abstraction. Local development uses the log mailer, while the production provider is deployment configuration.

All tenant-owned API operations identify an organisation in the URL. The backend resolves access from the authenticated global user, the route organisation identifier and a persisted active membership. A missing Organisation, absent membership or deactivated membership is exposed through the same `404` response; an active member whose role cannot perform an operation receives `403`. The backend never trusts client-provided organisation, user, membership or role identifiers to establish access or ownership.

The frontend uses `/organisations/{organisation}` as the selected-workspace source of truth. Selection is only navigation and user-interface state: it is not an authorisation boundary and is not persisted in browser storage or the backend session. Every tenant-owned backend request continues to resolve access independently. Users with no Organisation receive an empty state and may create one; users with one or multiple Organisations navigate through the same explicit workspace links.

An active Admin may list active and deactivated memberships, change an active membership's fixed role, and deactivate a membership without deleting its historical row. Self-management is allowed when the same invariant as every other change is satisfied: every Organisation must retain at least one active Admin. The final active Admin cannot be demoted or deactivated. Invitation acceptance is the only MVP mechanism that reactivates a deactivated membership; a general reactivation endpoint is not provided.

## 4. MVP Scope

### Core recruitment MVP

- Authentication
- Organisation management
- Organisation memberships and invitations
- Fixed role-based access control
- Strict tenant isolation
- Candidate creation, view and editing
- Job creation, view, editing and lifecycle
- Application creation and view
- Recruitment status workflow and history
- Search, filtering and pagination for core lists

### Portfolio-ready v1.0 completion

- Recruitment pipeline UI
- Candidate document upload and authorised download
- Minimal dashboard
- Complete loading, validation, error and empty states
- Automated backend and frontend tests
- Critical Playwright flow
- Static analysis and CI
- Demo data and account
- Production documentation and diagrams
- HTTPS deployment

## 5. Core User Flow

A Recruiter can:

1. Log in.
2. Select an organisation for which they have an active membership.
3. Create and open a job.
4. Create or review a candidate.
5. Create an application linking that candidate to the open job.
6. Review the application.
7. Move the application through valid recruitment stages.

Every step is authorised and scoped to the active organisation on the server.

## 6. Job Lifecycle

Job statuses are `draft`, `open`, `closed` and `archived`.

MVP transitions:

- Draft to Open
- Open to Closed
- Closed to Open
- Draft, Open or Closed to Archived

Archived is terminal in the MVP. Applications can be created only for an Open job. Jobs with applications are closed or archived, not physically deleted.

## 7. Recruitment Pipeline

Application statuses are `applied`, `screening`, `interview`, `offer`, `hired` and `rejected`.

Valid forward transitions:

- Applied to Screening
- Screening to Interview
- Interview to Offer
- Offer to Hired
- Applied, Screening, Interview or Offer to Rejected

Hired and Rejected are terminal in the MVP. Terminal-state correction or reopening is not supported until an explicit correction workflow is designed.

Each status change records the previous status, new status, actor, timestamp and optional note. Status changes are atomic and protected from concurrent overwrite.

## 8. Candidate Information

Initial candidate records support:

- first and last name
- occupation
- email
- phone
- location
- availability
- notes

Uploaded documents are included in portfolio-ready v1.0 but are private and available only through authorised endpoints.

Structured skills and employment history are excluded from the first Candidate vertical slice. Their inclusion later in v1.0 remains an open scope decision.

## 9. Job Information

Initial jobs support:

- title
- occupation
- location
- employment type
- description
- status
- opening date
- closing date

Salary/hourly rate and structured required skills remain open product and data-modelling decisions and are excluded from the first Job vertical slice.

## 10. Search, Filtering and Pagination

Each list endpoint documents an allow-list of filters and sorts. Unknown filters are rejected rather than interpreted dynamically.

Initial behaviour:

- Candidates: search by name and email; filter by occupation; sort by name or creation date.
- Jobs: search by title; filter by status, occupation and employment type; sort by opening or creation date.
- Applications: filter by job, candidate and status; sort by applied or updated date.
- Pagination has a server-enforced maximum page size.

Fuzzy search, full-text ranking, PostGIS and advanced matching are not MVP requirements.

## 11. Candidate Documents and Sensitive Data

Candidate documents:

- are stored outside the public web root
- use random internal storage keys
- preserve the original filename only as metadata
- are validated by allow-listed type and maximum size
- require tenant membership and resource authorisation for upload, download and deletion
- are downloaded as attachments unless a specifically safe preview is implemented

Real personal information must not be used in demo data. Sensitive fields, document contents, tokens and storage keys must not appear in production application logs. Production HTTP access logging must omit or redact password-reset query strings. Organisation invitation URLs carry the token in a browser fragment, which is not sent to the frontend server or ordinary access logs, and the frontend removes that fragment from the current browser-history entry after reading it. Local development uses `MAIL_MAILER=log` as an email-delivery substitute, so password-reset and Organisation-invitation URLs and their tokens necessarily appear in the local development mail log. That local-only mechanism must not be used as the production mail-delivery strategy.

The malware-scanning approach, retention periods and applicable privacy jurisdiction must be decided before production use.

## 12. Minimal Dashboard

The MVP dashboard is intentionally small and may show:

- open job count
- active candidate count
- application counts grouped by current pipeline status
- recent application activity

Advanced analytics and cross-tenant reporting are excluded.

## 13. Non-MVP Features

- Custom roles and permission builders
- Compliance management
- Qualifications and certifications
- Candidate matching
- PostGIS distance matching
- Qualification expiry notifications
- Redis and SQS processing
- AI CV parsing and match explanations
- Advanced analytics and observability
- Microservices and distributed architecture

## 14. Open Product Decisions

These decisions do not block the local foundation but must be resolved before the affected feature:

1. Whether candidate email is unique within an organisation and how duplicate candidates are merged.
2. Whether structured skills and employment history belong in v1.0.
3. The salary/rate model, including currency, range and pay period.
4. The malware-scanning mechanism and behaviour for files awaiting a scan.
5. Candidate and document retention periods and the governing privacy jurisdiction.
6. Whether an Admin-only correction flow for terminal application statuses is required after MVP.
