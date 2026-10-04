# CareMatch Database Design

## 1. Principles

- PostgreSQL is the primary relational database.
- Foreign keys, unique constraints, check constraints and nullability enforce important invariants.
- Every tenant-owned table contains `organisation_id` unless ownership is safely and explicitly derived.
- Cross-tenant relationships are prevented at the database level where practical.
- Indexes follow known API query patterns rather than speculation.
- Sensitive candidate information is not copied into unrelated tables or logs.
- Timestamps are stored consistently and returned by the API as UTC.

## 2. Identity and Organisation Tables

### `users`

Global authenticated identities.

Fields:

- `id`
- `name`
- `email`
- `password`
- `email_verified_at`, nullable
- `remember_token`, nullable
- `created_at`
- `updated_at`

Email is normalised to lowercase before persistence and is globally unique. Passwords use Laravel's current hashing facilities and are never returned by the API.

Email normalisation is implemented by one central Identity component and reused by registration, login and password-reset workflows. A PostgreSQL constraint that requires the stored value to equal its trimmed, lowercase representation provides defence in depth against writes that bypass the application path.

Phase 2-A password validation requires at least 12 characters, confirmation, no NUL bytes and a maximum of 72 bytes for the configured bcrypt hasher. It does not require arbitrary character-composition rules.

A user may exist without any row in `organisation_memberships`. Public registration creates only the global user and a database-backed authenticated session; it does not create an Organisation or membership.

### `password_reset_tokens`

Laravel Password Broker storage for password-reset tokens.

Fields:

- `email`, primary key
- `token`
- `created_at`, nullable

Reset tokens are stored using Laravel's standard hashed representation. A successful reset updates the password, rotates the remember token and invalidates every existing session for that user. Email verification remains deferred and is not a prerequisite for requesting or completing a password reset in Phase 2-A.

### `sessions`

Database-backed Laravel sessions used by the first-party Next.js SPA.

Fields:

- `id`, primary key
- `user_id`, nullable and indexed
- `ip_address`, nullable
- `user_agent`, nullable
- `payload`
- `last_activity`, indexed

Anonymous pre-authentication sessions may have no `user_id`. Authenticated sessions reference the global user so they can be invalidated after a successful password reset. Session identifiers and payloads are never exposed through the API.

### `personal_access_tokens`

The standard Laravel Sanctum infrastructure remains installed because Sanctum is the selected SPA authentication package.

Phase 2-A does not add `HasApiTokens` to the User model and does not expose token issuing, listing, revocation or management functionality. The table does not change the product authentication model: the first-party SPA uses stateful cookies and database sessions only. API-token functionality requires a later documented external-consumer requirement.

### `cache` and `cache_locks`

Laravel's standard database-cache tables support a shared production cache across
backend tasks. `cache` stores the cache key, serialised value and indexed expiry;
`cache_locks` stores the lock key, owner and indexed expiry. Both keys are primary
keys. Production uses this store for distributed authentication rate limits;
local development and isolated E2E continue to use the file cache. These are
framework infrastructure tables and do not contain tenant-owned business data.

### `organisations`

Tenant records.

Fields:

- `id`
- `name`
- `created_at`
- `updated_at`

Phase 2-B1 does not expose organisation deletion or add speculative soft-deletion columns. An organisation with dependent recruitment data will not be physically deleted through a normal user operation when deletion behaviour is designed later.

### `organisation_memberships`

Links a global user to an organisation and grants a role in that organisation.

Fields:

- `id`
- `organisation_id`
- `user_id`
- `role`: `admin`, `recruiter` or `hiring_manager`
- `deactivated_at`, nullable
- `created_at`
- `updated_at`

Constraints:

- unique `(organisation_id, user_id)`
- unique `(organisation_id, id)` for tenant-safe references
- foreign keys to `organisations` and `users`
- CHECK restricting `role` to supported values
- partial index on `organisation_id` where `role = 'admin'` and `deactivated_at IS NULL`

An active membership has no `deactivated_at` value. Membership removal deactivates the record rather than deleting audit identity. Admin membership listing includes both active and deactivated records. Deactivated memberships cannot have their role changed and are reactivated only by invitation acceptance in the MVP.

The application prevents removal or demotion of the last active Admin. A cross-row CHECK constraint cannot enforce this invariant, so membership-management mutations serialise on the parent Organisation row, lock the actor and target membership rows, and evaluate the remaining active Admin set in the same transaction. The partial active-Admin index supports that invariant query without changing the existing uniqueness or foreign-key constraints.

### `organisation_invitations`

Time-limited invitations to join an organisation.

Fields:

- `id`
- `organisation_id`
- `email`
- `role`
- `token_hash`
- `invited_by_user_id`
- `expires_at`
- `accepted_at`, nullable
- `revoked_at`, nullable
- `created_at`
- `updated_at`

Store only a SHA-256 hash of a 256-bit random invitation token. The default expiry is seven days. A composite foreign key `(organisation_id, invited_by_user_id)` references an organisation membership, ensuring the inviter belongs to the tenant. Only an active Admin may create, list or revoke an invitation. Resending is deferred.

Constraints and indexes:

- foreign key `organisation_id` to `organisations`
- composite foreign key `(organisation_id, invited_by_user_id)` to `organisation_memberships(organisation_id, user_id)`
- CHECK restricting `role` to the three fixed Organisation roles
- CHECK requiring trimmed lowercase `email`
- CHECK preventing both `accepted_at` and `revoked_at` from being set
- unique `token_hash`
- index `(organisation_id, email)` for tenant administration and conflict checks
- partial unique index `(organisation_id, email)` where both `accepted_at` and `revoked_at` are null

The partial unique index is the concurrency-safe authority for one unresolved invitation per Organisation and canonical email. PostgreSQL cannot use the volatile current time in this index predicate. When a new invitation replaces an expired unresolved invitation, the creation transaction first marks the expired row revoked and then inserts the new row. Already-revoked and accepted rows do not block a later invitation. Already-revoked revocation is idempotent; accepted invitations cannot be revoked.

Acceptance locks the invitation and any existing membership in one transaction. A missing membership is created; a deactivated membership is reactivated and receives the invitation's persisted role. The transaction then marks the invitation accepted. The existing unique `(organisation_id, user_id)` membership constraint prevents duplicate memberships.

## 3. Candidate Tables

### `qualification_definitions`

Organisation-scoped definitions shared by Candidate credentials and Recruitment requirements. Fields are `id`, `organisation_id`, `name`, nullable `category` and `description`, `is_active`, and timestamps. Unique `(organisation_id, id)` supports composite tenant foreign keys; `(organisation_id, is_active, name)` supports the catalogue. Definitions are deactivated rather than deleted. Existing references remain readable, while new credentials and requirements accept active definitions only.

### `candidate_qualifications`

Candidate-owned credential records contain `organisation_id`, `candidate_id`, `qualification_definition_id`, nullable `issuer`, `credential_number`, `issued_on`, `expires_on`, and timestamps. Composite foreign keys to Candidate and qualification definition prevent cross-tenant relationships. Multiple credentials for one Candidate/definition are deliberately allowed for renewals. A CHECK enforces `issued_on <= expires_on` when both dates exist. `(organisation_id, candidate_id, qualification_definition_id)` supports Candidate evaluation and `(organisation_id, expires_on)` supports the tenant expiry view. Current-time expiry is intentionally not a CHECK constraint.

### `job_qualification_requirements`

Required-only Job qualifications contain `organisation_id`, `job_id`, `qualification_definition_id`, and timestamps. Composite foreign keys prevent cross-tenant Job/definition relationships. Unique `(organisation_id, job_id, qualification_definition_id)` prevents duplicate requirements. Weighted, preferred and scored requirements remain intentionally absent; Phase 7C uses coverage states in a lexicographic ranking rather than requirement weights.

### `candidates`

Tenant-owned candidate records.

Initial fields:

- `id`
- `organisation_id`
- `first_name`
- `last_name`
- `email`, nullable
- `phone`, nullable
- `occupation`, nullable
- `location`, nullable
- `latitude numeric(8,6)`, nullable
- `longitude numeric(9,6)`, nullable
- generated `location_geography geography(Point,4326)`, nullable
- `availability`, nullable
- `notes`, nullable
- `created_at`
- `updated_at`

Constraints:

- non-cascading foreign key to `organisations`
- unique `(organisation_id, id)` for composite tenant foreign keys
- NOT NULL `organisation_id`, `first_name` and `last_name`
- CHECK that latitude and longitude are both null or both present
- CHECK latitude is between -90 and 90 and longitude between -180 and 180

Phase 3A bounds first and last names at 100 characters, phone at 50, email and the remaining short profile fields at 255, and notes at 5,000 characters through the API. Candidate email is trimmed and lowercased before persistence but is not an Identity email and does not create or link an Identity user.

Whether normalised candidate email is unique within an organisation remains a product decision. Do not add that constraint until duplicate handling is agreed.

Candidate deletion, archival and soft deletion remain deferred until retention requirements are resolved. Phase 3A creates no `deleted_at` column.

Latitude/longitude are the only editable coordinate source. PostgreSQL derives
the stored geography point as longitude/latitude with SRID 4326, preventing two
independently editable representations. Coordinates are approximate recruitment
location data, not residential addresses. A GiST index on Candidate geography
supports radius matching; no Job spatial index is justified because the target
Job is first resolved by tenant and primary identifier.

### `candidate_documents`

Private document metadata. File contents remain outside the public web root.

Fields:

- `id`
- `organisation_id`
- `candidate_id`
- `original_name`
- `storage_key`
- `mime_type`
- `size_bytes`
- `uploaded_by_user_id`
- `created_at`
- `updated_at`

Constraints:

- non-cascading foreign key from `organisation_id` to `organisations`
- unique `(organisation_id, id)`
- composite foreign key `(organisation_id, candidate_id)` to `candidates(organisation_id, id)`
- unique `storage_key`
- composite foreign key `(organisation_id, uploaded_by_user_id)` to `organisation_memberships(organisation_id, user_id)`
- CHECK that `size_bytes` is positive
- restrictive deletion for Organisation, Candidate and uploader membership references

`original_name` is bounded to 255 characters, `storage_key` to 64 hexadecimal
characters and `mime_type` to 100 characters. Storage keys encode 32 random bytes,
are never derived from a Candidate, filename or timestamp, and are not exposed in
normal API responses. The `(organisation_id, candidate_id, created_at, id)` index
supports the deterministic newest-first nested list; the unique
`(organisation_id, id)` index supports nested single-record resolution with a
Candidate ownership filter.

File contents are held on the private Candidate document disk, not in PostgreSQL
or a public web directory. PDF and DOCX are the only allowed MIME values in Phase
5A and the maximum object size is 10 MiB. MIME and size are observed by the server,
not supplied as authoritative client metadata.

## 4. Recruitment Tables

### `jobs`

Tenant-owned vacancies.

Initial fields:

- `id`
- `organisation_id`
- `title`
- `occupation`, nullable
- `location`, nullable
- `latitude numeric(8,6)`, nullable
- `longitude numeric(9,6)`, nullable
- generated `location_geography geography(Point,4326)`, nullable
- `employment_type`, nullable
- `description`, nullable
- `status`: `draft`, `open`, `closed` or `archived`
- `opened_at`, nullable
- `closes_at`, nullable
- `created_at`
- `updated_at`

Constraints:

- foreign key to `organisations`
- unique `(organisation_id, id)` for composite tenant foreign keys
- CHECK for supported `status` values
- CHECK that `closes_at` is null or not earlier than `opened_at`
- CHECK that latitude and longitude are both null or both present
- CHECK latitude is between -90 and 90 and longitude between -180 and 180

Salary/rate representation and structured required skills remain unresolved and are excluded from the first schema migration.

Phase 3B bounds title at 200 characters, employment type at 100, occupation and
location at 255, and description at 10,000 through the API. The Organisation
foreign key uses `RESTRICT`; Jobs have no soft-delete column in this slice. The
status CHECK and date-order CHECK are database-level final authorities even when
application validation is bypassed.

### `applications`

Connects a candidate to a job within the same organisation.

Fields:

- `id`
- `organisation_id`
- `job_id`
- `candidate_id`
- `status`: `applied`, `screening`, `interview`, `offer`, `hired` or `rejected`
- `applied_at`
- `created_by_user_id`
- `created_at`
- `updated_at`

Constraints:

- unique `(organisation_id, id)`
- unique `(organisation_id, job_id, candidate_id)` for the MVP
- composite foreign key `(organisation_id, job_id)` to `jobs(organisation_id, id)`
- composite foreign key `(organisation_id, candidate_id)` to `candidates(organisation_id, id)`
- composite foreign key `(organisation_id, created_by_user_id)` to `organisation_memberships(organisation_id, user_id)`
- CHECK for supported `status` values

Repeat applications by the same candidate to the same job are not supported in the MVP.

Application creation uses status `applied` and a server-controlled
UTC `applied_at`. All foreign keys use restrictive deletion. The composite Job,
Candidate, and creator-membership foreign keys make cross-tenant references
invalid even if application validation is bypassed. Application and initial
history creation share one transaction, and the Job row is locked before its
persisted Open state is evaluated.

Later status changes lock the Application using `(organisation_id, id)` before
reading current status. The allowed transition and persisted membership role are
evaluated after the lock. The normal `updated_at` changes with status; separate
per-stage timestamp columns are unnecessary because history records the audit
time.

### `application_status_history`

Immutable recruitment-pipeline history.

Fields:

- `id`
- `organisation_id`
- `application_id`
- `from_status`, nullable for the initial entry
- `to_status`
- `changed_by_user_id`
- `note`, nullable
- `created_at`

Constraints:

- composite foreign key `(organisation_id, application_id)` to `applications(organisation_id, id)`
- composite foreign key `(organisation_id, changed_by_user_id)` to `organisation_memberships(organisation_id, user_id)`
- CHECK constraints for supported status values

Application creation writes its initial `applied` history row in the same
transaction. Every valid transition updates the Application and appends exactly
one row in one transaction. `note` is optional, trimmed, limited to 1,000
characters by application validation, and empty values become null. Status
history is append-only through normal application operations: only create/read
paths exist, with no update or delete endpoint. History reads are tenant-scoped
through the parent Application and ordered by `created_at`, then `id`.

### `audit_events`

Append-only Organisation business activity contains `id`, `organisation_id`,
`actor_user_id`, `event_type`, `subject_type`, `subject_id`, object-shaped JSONB
`metadata` and immutable UTC `occurred_at`. There is deliberately no
`updated_at`.

A composite foreign key from `(organisation_id, actor_user_id)` to Organisation
membership proves the actor belonged to that tenant; deactivated membership
rows remain valid historical actors. CHECK constraints require non-blank types,
a positive subject ID and object metadata. A PostgreSQL trigger rejects UPDATE
and DELETE while allowing migration rollback to drop the table. No application
update/delete API exists.

## 5. Deletion and Retention

- Applications and status history are not physically deleted through normal user operations.
- Jobs with applications are closed or archived rather than deleted.
- Candidates are archived or soft-deleted once retention requirements are agreed.
- Organisation deletion is a controlled administrative process, not a cascading UI action.
- Memberships referenced by audit fields are deactivated rather than deleted and never cascade into candidate or recruitment records.
- Candidate documents persist until explicitly deleted by an authorised Admin or Recruiter; there is no automatic portfolio-MVP purge job.
- Candidate document deletion removes private content before metadata. A metadata failure can leave an inaccessible row, but cannot leave the content downloadable through the application.

Production retention periods and jurisdiction-specific privacy obligations must be decided before real Candidate data is accepted. Demo documents remain synthetic.

## 6. Initial Index Strategy

Expected indexes:

- unique `users(email)`
- `sessions(user_id)`
- `sessions(last_activity)`
- unique `personal_access_tokens(token)` from Sanctum's standard migration
- unique `organisation_memberships(organisation_id, user_id)`
- `organisation_memberships(user_id, organisation_id)`
- partial `organisation_memberships(organisation_id)` for active Admin rows
- `organisation_invitations(organisation_id, email)`
- `candidates(organisation_id, created_at)`
- `candidates(organisation_id, last_name, first_name)`
- GiST `candidates(location_geography)`
- `jobs(organisation_id, status, created_at)`
- unique `applications(organisation_id, job_id, candidate_id)`
- `applications(organisation_id, candidate_id)`
- `applications(organisation_id, status, applied_at, id)`
- `applications(organisation_id, applied_at, id)`
- `applications(organisation_id, updated_at, id)`
- `application_status_history(organisation_id, application_id, created_at)`
- `application_status_history(organisation_id, created_at DESC, id DESC)`
- `candidate_documents(organisation_id, candidate_id, created_at, id)`
- `audit_events(organisation_id, occurred_at, id)`
- `audit_events(organisation_id, event_type, occurred_at, id)`
- `audit_events(organisation_id, actor_user_id, occurred_at, id)`

Indexes must be checked against generated SQL and actual list/dashboard queries. Full-text and trigram indexes remain deferred until measured requirements justify them. Phase 7C adds only the Candidate geography GiST index used by `ST_DWithin` radius queries.

Phase 7D adds no table, materialized view, cache or index. Analytics derives a
bounded UTC `applications.applied_at` cohort using the existing
`(organisation_id, applied_at, id)` index, then joins immutable history through
`(organisation_id, application_id, created_at)`. Candidate creation counts reuse
`(organisation_id, created_at)`. Job creation counts are intentionally defined by
`jobs.created_at`; the existing tenant-leading Job index and current portfolio
scale are sufficient, so no speculative `opened_at` or analytics-only index is
added. Reports are recomputed from authoritative records.

Representative rollback-only `EXPLAIN ANALYZE` used 20,000 synthetic Applications,
100 Jobs and 40,000 status-history rows. With the selected period covering the
whole synthetic tenant, PostgreSQL correctly preferred sequential scans plus hash
aggregation for cohort/funnel (about 59 ms), time-to-stage (about 74 ms) and Job
aggregation (about 23 ms). The 92-day zero-filled series used the existing
`applications(organisation_id, applied_at, id)` index for each bounded day range
(about 32 ms). These local figures justify adding no Phase 7D index and are not a
production SLA; production cardinality and selectivity must be measured again.

PostGIS is enabled by migration and deliberately retained on rollback because it
is a shared database capability. Candidate and Job geography columns are stored
generated values; rolling the migration back removes the dependent columns and
Candidate index but does not casually remove the extension. Matching results are
derived on demand and no stale `candidate_job_matches` table is persisted.

The Phase 3A Candidate list uses the two Candidate indexes above for tenant/date and tenant/name access patterns. Occupation filtering is implemented, but an additional occupation index is deferred until representative production cardinality and query plans demonstrate a benefit. Simple substring `ILIKE` search remains intentionally unindexed for the MVP dataset; trigram and full-text indexes are not introduced speculatively.

The Phase 3B Job list uses `(organisation_id, status, created_at)` for its primary
tenant/status/date access pattern. Literal substring `ILIKE` title search and
optional occupation, employment-type and opening-date paths remain unindexed in
the initial MVP: PostgreSQL plans were inspected against representative local
data, and additional or trigram indexes are deferred until production volume and
selectivity justify their write/storage cost.

The Phase 4 Application indexes support duplicate/Job lookup, Candidate and
status filters, and deterministic applied/updated sorting. Representative
PostgreSQL `EXPLAIN ANALYZE` plans were reviewed with 5,000 temporary rows. The
tenant applied-time list used `(organisation_id, applied_at, id)`, status lists
used `(organisation_id, status, applied_at, id)`, Candidate filters used
`(organisation_id, candidate_id)`, updated-time sorting used
`(organisation_id, updated_at, id)`, and Job filters used the duplicate-prevention
unique index before a small bounded sort. The status-history timeline is bounded
by the fixed transition graph and uses the existing
`(organisation_id, application_id, created_at)` index; the `id` tie-break does
not justify another index at this size. No additional Phase 4 index was
justified; temporary plan data was rolled back.

The Phase 5B dashboard uses existing tenant-leading Candidate, Job and Application
indexes for its count and grouped-status queries. Representative PostgreSQL plans
were inspected with 10,000 Candidates, 10,000 Jobs, 10,000 Applications and
50,000 status-history rows in one rolled-back test transaction. Candidate count
and grouped Application status appropriately used sequential scans when the
representative tenant owned every generated row; the Open Job count used the
existing `(organisation_id, status, created_at)` index. The latest-five activity
query improved from a full history scan, top-N sort and joins (about 35.5 ms) to a
tenant-leading history index scan with bounded joins (about 0.09 ms), justifying
`(organisation_id, created_at DESC, id DESC)`. These local timings explain the
decision but are not production performance guarantees.

## 7. Transactions and Concurrency

The following writes are atomic:

- organisation plus initial Admin membership
- invitation acceptance plus membership creation or activation
- membership role changes and deactivation, including last-active-Admin evaluation
- tenant-scoped Job lifecycle transitions
- application plus initial status history
- application status update plus status history append

Application status updates lock the tenant-scoped row with `FOR UPDATE`. A
competing request waits, then re-evaluates its requested target against the
persisted status. An incompatible stale transition fails instead of overwriting
the first result. The status update and history append share the transaction.

Job lifecycle transitions lock the tenant-scoped Job row before checking the
persisted state and updating it. Profile edits cannot change status. Profile date
updates also lock the Job while evaluating the combined persisted-and-requested
date range, with the PostgreSQL CHECK constraint as defence in depth.

Membership-management mutations lock the Organisation row before membership rows, making the last-active-Admin check and write one serial decision per Organisation. Invitation creation and acceptance follow the same Organisation-first root lock before taking invitation and membership locks. This prevents concurrent demotion/deactivation operations from leaving zero active Admins and avoids inconsistent lock ordering with invitation membership reactivation.

Document storage uses compensating cleanup because object storage and PostgreSQL do not share a transaction. Phase 5A upload writes the private object first and compensates by deleting it if the metadata insert fails. Storage failure prevents metadata insertion. Delete removes the object first and metadata second; this deliberately prioritises removal of sensitive content if the second operation fails.
