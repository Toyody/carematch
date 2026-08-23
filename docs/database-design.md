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

### `organisations`

Tenant records.

Fields:

- `id`
- `name`
- `created_at`
- `updated_at`
- `deleted_at`, if soft deletion is adopted

An organisation with dependent recruitment data is not physically deleted through a normal user operation.

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

An active membership has no `deactivated_at` value. Membership removal deactivates the record rather than deleting audit identity. The application prevents removal or demotion of the last active Admin.

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

Store only a hash of the invitation token. A composite foreign key `(organisation_id, invited_by_user_id)` references an organisation membership, ensuring the inviter belongs to the tenant. Only an active Admin may create, revoke or resend an invitation.

## 3. Candidate Tables

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
- `availability`, nullable
- `notes`, nullable
- `created_at`
- `updated_at`
- `deleted_at`, if soft deletion is adopted

Constraints:

- foreign key to `organisations`
- unique `(organisation_id, id)` for composite tenant foreign keys

Whether normalised candidate email is unique within an organisation remains a product decision. Do not add that constraint until duplicate handling is agreed.

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
- `deleted_at`, if soft deletion is adopted

Constraints:

- unique `(organisation_id, id)`
- composite foreign key `(organisation_id, candidate_id)` to `candidates(organisation_id, id)`
- unique `storage_key`
- composite foreign key `(organisation_id, uploaded_by_user_id)` to `organisation_memberships(organisation_id, user_id)`
- CHECK that `size_bytes` is positive

Storage keys are random and are not exposed in normal API responses.

## 4. Recruitment Tables

### `jobs`

Tenant-owned vacancies.

Initial fields:

- `id`
- `organisation_id`
- `title`
- `occupation`, nullable
- `location`, nullable
- `employment_type`, nullable
- `description`, nullable
- `status`: `draft`, `open`, `closed` or `archived`
- `opened_at`, nullable
- `closes_at`, nullable
- `created_at`
- `updated_at`
- `deleted_at`, if soft deletion is adopted

Constraints:

- foreign key to `organisations`
- unique `(organisation_id, id)` for composite tenant foreign keys
- CHECK for supported `status` values
- CHECK that `closes_at` is null or not earlier than `opened_at`

Salary/rate representation and structured required skills remain unresolved and are excluded from the first schema migration.

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

Application creation writes its initial `applied` history row in the same transaction. Status history is append-only through normal application operations.

## 5. Deletion and Retention

- Applications and status history are not physically deleted through normal user operations.
- Jobs with applications are closed or archived rather than deleted.
- Candidates are archived or soft-deleted once retention requirements are agreed.
- Organisation deletion is a controlled administrative process, not a cascading UI action.
- Memberships referenced by audit fields are deactivated rather than deleted and never cascade into candidate or recruitment records.
- Candidate document deletion removes access immediately and arranges storage cleanup safely.

Exact retention periods and jurisdiction-specific privacy obligations must be decided before production use.

## 6. Initial Index Strategy

Expected indexes:

- unique `users(email)`
- unique `organisation_memberships(organisation_id, user_id)`
- `organisation_memberships(user_id, organisation_id)`
- `organisation_invitations(organisation_id, email)`
- `candidates(organisation_id, created_at)`
- `candidates(organisation_id, last_name, first_name)`
- `jobs(organisation_id, status, created_at)`
- `applications(organisation_id, job_id, status)`
- `applications(organisation_id, candidate_id)`
- `application_status_history(organisation_id, application_id, created_at)`
- `candidate_documents(organisation_id, candidate_id, created_at)`

Indexes must be checked against generated SQL and actual list/dashboard queries. Full-text, trigram and PostGIS indexes are deferred until measured requirements justify them.

## 7. Transactions and Concurrency

The following writes are atomic:

- organisation plus initial Admin membership
- invitation acceptance plus membership creation or activation
- application plus initial status history
- application status update plus status history append

Application status updates use row locking or an explicit version check. A stale transition fails rather than overwriting a concurrent change.

Document storage uses compensating cleanup because object storage and PostgreSQL do not share a transaction.
