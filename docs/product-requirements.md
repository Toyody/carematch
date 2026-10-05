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

### Implemented portfolio-ready v1.0

- Recruitment pipeline UI
- Candidate document upload and authorised download
- Minimal dashboard
- Complete loading, validation, error and empty states
- Automated backend and frontend tests
- Critical Playwright flow
- Static analysis and CI
- Demo data and account
- Portfolio documentation, diagrams and screenshots

Public HTTPS deployment and production infrastructure remain Phase 6 work. Phase 7A adds Organisation-defined qualification and certification tracking, expiry visibility and deterministic Job-requirement coverage. CareMatch reports only whether Organisation-recorded Candidate credentials satisfy Organisation-recorded Job requirements; it does not certify legal or regulatory compliance or determine whether a person is legally permitted to work.

### Qualification and credential tracking

Each Organisation maintains its own qualification catalogue. Definitions have a name, optional category and description, and an active/inactive lifecycle. Inactive definitions remain visible wherever historical Candidate credentials or Job requirements reference them, but cannot be selected for new records.

Admin and Recruiter memberships may record and correct Candidate credentials and manage required Job qualifications. Hiring Managers have read-only access. Qualification catalogue mutation is Admin-only. Candidate credentials may contain an optional issuer, reference number, issue date and expiry date. Multiple records for one definition are allowed so renewals are not destroyed.

Expiry uses UTC calendar dates and a configurable 30-day warning window. A credential is expired only when `expires_on` is before today; it remains valid through its expiry date and is labelled expiring when its expiry date is between today and the warning boundary inclusive. A null expiry is non-expiring and valid. Expiry tracking is synchronous and does not send notifications.

For each required Job qualification, the backend chooses the best Candidate evidence deterministically: valid, then expiring, then expired, otherwise missing. Overall requirement coverage is `satisfied`, `attention_required` when every requirement is usable but at least one is expiring, or `not_satisfied` when any requirement is expired or missing. This state is contextual to a Candidate and Job, never a global Candidate status.

### Organisation audit trail

CareMatch records successful, meaningful tenant business mutations with the
trusted authenticated actor, stable event and subject identifiers, occurrence
time and small allow-listed context. Only active Organisation Admins may read
the paginated trail. Recruiters and Hiring Managers cannot access it.

The trail does not record reads, authentication/password operations, failed
attempts or arbitrary request payloads. It excludes tokens, session data,
Candidate/Application notes, qualification reference numbers, document names
and storage information. Recruitment status history remains a separate
authoritative workflow timeline. Audit retention policy is deferred and must be
selected before production use with real Candidate data.

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

Phase 4 establishes the Application foundation and recruitment pipeline. Admin
and Recruiter may create an Application by selecting a
Candidate and an Open Job from the trusted Organisation; Hiring Manager is
read-only except that a Hiring Manager may decide an Application currently at
Interview by moving it to Offer or Rejected. The server always assigns `applied`, the current authenticated tenant
member as actor, and the UTC application time. Candidate, Job, actor, and
Organisation identifiers are protected by composite tenant foreign keys. A
Candidate may apply to a given Job only once. A tenant-valid Job that is not
Open and a duplicate Candidate/Job pair return `409`; missing and cross-tenant
targets use safe `404` semantics.

Creation locks the tenant-scoped Job row and rechecks its persisted Open state,
then writes the Application and initial `null -> applied` history entry in one
transaction. Lists filter by Job, Candidate, and
status; sort by applied or updated time; and use the common 20/default,
100/maximum pagination contract.

Status changes use an explicit transition endpoint rather than generic profile
editing. The server locks the tenant-scoped Application, authorises the exact
persisted current-to-target transition, applies the Domain graph, then updates
status and appends one history row in the same transaction. Admin and Recruiter
may perform all valid transitions; Hiring Manager may perform only Interview to
Offer or Interview to Rejected. Optional transition notes are trimmed, limited
to 1,000 characters and stored as null when empty. All active roles may read the
complete, append-only timeline in chronological `created_at`, then `id` order.

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

Phase 3A implements Candidate creation, listing, detail and editing as tenant-owned operations. Candidate ownership is always derived from the request-scoped `TenantContext`; the API does not accept `organisation_id`. All active roles may list and view Candidates, while only Admin and Recruiter memberships may create or edit them. Nested Candidate identifiers are resolved by Organisation and Candidate ID together so cross-tenant and nonexistent Candidates share the same `404` response.

Candidate lists support case-insensitive literal substring search across name and email, exact occupation filtering, allow-listed name or creation-date sorting, and pagination with a default of 20 and maximum of 100 records per page. Candidate email is trimmed and lowercased when present, but it is not an Identity account email and is not unique. Duplicate handling remains an open product decision. Candidate deletion, documents, structured skills and employment history are not part of Phase 3A. Phase 5A adds private Candidate documents without adding Candidate deletion.

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

Phase 3B implements Job creation, paginated listing, detail, profile editing and
explicit lifecycle operations as tenant-owned Recruitment features. Ownership is
derived only from `TenantContext`; client-supplied ownership, user or role fields
cannot select a tenant. All active roles may list and view Jobs. Admin and
Recruiter may create, edit, open, close, reopen and archive Jobs; Hiring Manager
is read-only. New Jobs always start in Draft, and normal profile editing cannot
change status.

Nested Job identifiers are always resolved with the trusted Organisation ID, so
cross-tenant and nonexistent Jobs share `404` semantics. Invalid lifecycle
transitions return a stable `409`. Lifecycle changes do not automatically change
the optional opening or closing profile dates. Job lists use literal
case-insensitive title search, exact status/occupation/employment-type filters,
allow-listed opening-date or creation-date sorting, deterministic null ordering,
and pagination of 20 by default with a maximum of 100.

## 10. Search, Filtering and Pagination

Each list endpoint documents an allow-list of filters and sorts. Unknown filters are rejected rather than interpreted dynamically.

Initial behaviour:

- Candidates: search by name and email; filter by occupation; sort by name or creation date.
- Jobs: search by title; filter by status, occupation and employment type; sort by opening or creation date.
- Applications: filter by job, candidate and status; sort by applied or updated date.
- Pagination defaults to 20 and has a server-enforced maximum page size of 100.

Fuzzy search and full-text ranking are not MVP requirements. Phase 7C adds only the explicit deterministic matching contract below; it does not introduce fuzzy or AI ranking.

### 10.1 Deterministic Job-to-Candidate Matching

All active Organisation roles may view the read-only Candidate matches for a
tenant-owned Job. Draft, Open, Closed and Archived Jobs remain readable; an
Archived Job is not made actionable by matching. Results contain only Candidates
owned by the resolved Organisation and are paginated at 20 by default and 100 at
maximum.

CareMatch uses deterministic lexicographic ranking, not an arbitrary percentage:

1. qualification coverage: `satisfied`, then `attention_required`, then `not_satisfied`;
2. occupation: trimmed, case-insensitive exact `match`, then `unknown`, then `mismatch`;
3. known geographic distance ascending, with unavailable distance last;
4. Candidate ID ascending as the final stable tie-breaker.

Qualification coverage retains the Phase 7A meanings. A Job with no requirements
is satisfied. Expiring evidence requires attention, while expired or missing
evidence is not satisfied. Distance uses PostGIS geography in kilometres. An
optional `max_distance_km` greater than zero and at most 1,000 filters through
`ST_DWithin`; a Job must have coordinates and Candidates without coordinates
cannot satisfy the radius. Without that filter, unknown distance is returned as
`null`, never zero. The response also exposes any existing Application status
but never creates or changes an Application.

Candidate availability, Candidate notes, Job description, human-readable
location strings, names, email, phone, documents, audit history and credential
numbers are not matching signals. No fuzzy occupation taxonomy, free-text
heuristic, AI, external geocoder or hidden score is used. Matching is decision
support only and does not hire, reject or decide regulatory compliance; a human
user remains responsible for every recruitment decision.

Candidate and Job coordinates are optional approximate recruitment-location
data. Latitude and longitude must be supplied together and lie within valid
ranges. They are never derived from the location label. Users should enter
locality-level data, not a private residential address, and raw values must not
be written to audit metadata or application logs.

## 11. Candidate Documents and Sensitive Data

Candidate documents:

- are stored outside the public web root
- use random internal storage keys
- preserve the original filename only as metadata
- are validated by allow-listed type and maximum size
- require tenant membership and resource authorisation for upload, download and deletion
- are downloaded as attachments unless a specifically safe preview is implemented

Phase 5A supports non-empty PDF and DOCX files up to 10 MiB. The backend uses
server-side content MIME detection and does not trust the original extension or
browser-supplied MIME. Admin and Recruiter may list, upload, download and delete;
Hiring Manager may list and download but remains read-only. Every operation is
nested beneath the trusted Organisation and Candidate. Cross-tenant,
cross-Candidate and nonexistent document identifiers use the same safe `404`
semantics.

The local/portfolio MVP stores contents on a dedicated private Laravel disk with
cryptographically random 32-byte keys encoded as hexadecimal. Original names are
sanitised attachment/display metadata only. Upload stores content before metadata
and removes the new object if metadata persistence fails. Deletion removes the
private object before metadata so a later database failure cannot leave sensitive
content downloadable through the application.

Real personal information must not be used in demo data. Sensitive fields, document contents, tokens and storage keys must not appear in production application logs. Production HTTP access logging must omit or redact password-reset query strings. Organisation invitation URLs carry the token in a browser fragment, which is not sent to the frontend server or ordinary access logs, and the frontend removes that fragment from the current browser-history entry after reading it. Local development uses `MAIL_MAILER=log` as an email-delivery substitute, so password-reset and Organisation-invitation URLs and their tokens necessarily appear in the local development mail log. That local-only mechanism must not be used as the production mail-delivery strategy.

Malware scanning is a production prerequisite before real candidate documents
may be enabled. It is intentionally not simulated in the portfolio MVP; file-type
allow-listing is not represented as malware scanning. Public/demo environments
use synthetic documents only. A real deployment must select and integrate a
scanner, potentially around private object storage, before accepting personal
documents.

The portfolio MVP has no automatic retention or purge job. Documents remain until
an authorised Admin or Recruiter explicitly deletes them. Before real candidate
data is handled, each deployment must define retention periods, deletion duties
and the applicable privacy jurisdiction. This implementation does not by itself
claim healthcare or recruitment compliance.

## 12. Minimal Dashboard

The Organisation workspace includes an intentionally small tenant-scoped dashboard showing:

- open job count
- Candidate count, meaning every currently persisted Candidate because the MVP has no Candidate active/inactive lifecycle
- application counts for every current pipeline status, including zero values
- the latest five immutable Application status-history events, enriched with compact Candidate and Job identities

All active Organisation roles may view the dashboard. Counts and activity are
computed only for the route Organisation after active membership resolution.
Advanced analytics, date-range reporting, charts and cross-tenant reporting are
provided only through the separate Analytics experience below; the Dashboard
itself remains deliberately small.

### 12.1 Operational Recruitment Analytics

All active Organisation roles may view a separate tenant-scoped Analytics report.
Its Application cohort contains Applications whose `applied_at` falls within an
inclusive UTC calendar-date range, implemented as `[from 00:00 UTC, day after to
00:00 UTC)`. The default is the last 90 UTC calendar days including today; both
dates must be supplied together for an explicit period, and the maximum is 365
days. CareMatch does not yet model an Organisation reporting timezone.

For that cohort, the reached-stage funnel reports Applied, Screening, Interview,
Offer and Hired. Screening and later stages come from immutable status history,
so a Hired Application also contributes to earlier stages it reached. Rejected
is a separate terminal outcome rather than a funnel stage. A second distribution
reports the cohort's current persisted status, with zero counts for absent states.
Recent cohorts may not yet have matured through the funnel.

Period summary metrics are new Candidate records, new Job records, Applications,
Hired outcomes and Rejected outcomes. “New Jobs” deliberately means rows created
during the period: normal Job lifecycle transitions do not currently maintain an
authoritative first-open event timestamp in `jobs.opened_at`. Daily Application
volume includes zero-value UTC dates. Time-to-Interview and time-to-Hired are the
median elapsed days from `applications.applied_at` to the first authoritative
matching transition, accompanied by sample size and returned as `null` when no
valid sample exists. Negative malformed durations are excluded. Job rows aggregate
cohort volume and reached stages, ordered by Application count, Hired count and
Job ID, and are limited to ten.

Analytics is operational reporting, not a warehouse or decision engine. It does
not expose Candidate identities and does not use notes, descriptions, documents,
credential numbers, location, audit metadata, Matching results, actor/recruiter
performance, demographic inference, benchmarks, predictive analytics or AI. It
is read-only, derived synchronously from authoritative PostgreSQL records, creates
no Audit events and persists no report, cache or analytics event.

## 13. Portfolio Demo Data

Local portfolio review uses an explicit, opt-in `PortfolioDemoSeeder`. It creates
only deterministic synthetic identities and recruitment records under the
reserved `example.test` domain. Its password is supplied through the environment,
it does not seed Candidate documents, it never runs during normal setup or CI,
and it refuses production execution. Re-running it must reuse its own known demo
records without duplicating or overwriting unrelated data.

Phase 6A provides a guarded production-only command for provisioning a dedicated
public-demo account and synthetic dataset from secrets/configuration. Public-demo
mode disables public registration, Organisation creation, password recovery,
invitation creation and Candidate-document upload/deletion while retaining the
portfolio recruitment workflow. The command is never run automatically, performs
no destructive reset and must not reuse a developer account, commit a shared
password or contain real Candidate information. A live public demo remains
unverified until Phase 6B/6C deployment and operational checks are complete.

## 14. Non-MVP Features

- Custom roles and permission builders
- Fuzzy, semantic or AI matching
- Automated geocoding and occupation taxonomies
- AI CV parsing and match explanations
- Microservices and distributed architecture

Phase 8 adds one bounded asynchronous product operation: an active Organisation
Admin may request a credential-expiry digest. The request is durably idempotent,
returns `202`, and delivers only expired/expiring aggregate counts plus a link to
the authorised Compliance view. It contains no Candidate identity, credential
number, notes, document information or legal-compliance claim. Invitation and
password-reset token delivery remains synchronous so raw security tokens never
enter SQS. Public-demo deployments reject the digest request server-side.

## 15. Open Product Decisions

These decisions do not block the local foundation but must be resolved before the affected feature:

1. Whether candidate email is unique within an organisation and how duplicate candidates are merged.
2. Whether structured skills and employment history belong in v1.0.
3. The salary/rate model, including currency, range and pay period.
4. The production malware-scanning provider and behaviour for files awaiting a scan. Scanning remains a prerequisite for real uploads, not an MVP simulation.
5. Production Candidate/document retention periods and the governing privacy jurisdiction. The portfolio MVP uses explicit deletion only.
6. Whether an Admin-only correction flow for terminal application statuses is required after MVP.
