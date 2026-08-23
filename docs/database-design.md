# CareMatch Database Design

## 1. Principles

- PostgreSQL is the primary relational database.
- Foreign keys should enforce referential integrity.
- Tenant-owned data must reference an organisation.
- Appropriate unique constraints and indexes must be used.
- Database design should support future growth without premature optimisation.

## 2. Core Tables

### organisations

Represents a tenant.

Important fields:

- id
- name
- created_at
- updated_at

### users

Represents authenticated users.

Important fields:

- id
- name
- email
- password
- created_at
- updated_at

### organisation_memberships

Links users to organisations.

Important fields:

- id
- organisation_id
- user_id
- role
- created_at
- updated_at

Unique constraint:

- organisation_id + user_id

### candidates

- id
- organisation_id
- first_name
- last_name
- email
- phone
- occupation
- location
- notes
- created_at
- updated_at

### jobs

- id
- organisation_id
- title
- occupation
- location
- employment_type
- description
- status
- opened_at
- closes_at

### applications

- id
- organisation_id
- job_id
- candidate_id
- status
- applied_at
- created_at
- updated_at

Organisation
    1
    |
    N
Membership
    N
    |
    1
User


Organisation
    1
    |
    N
Candidate


Organisation
    1
    |
    N
Job


Candidate
    1
    |
    N
Application
    N
    |
    1
Job

## 3. Initial Index Strategy

Potential indexes:

- candidates(organisation_id)
- jobs(organisation_id)
- applications(organisation_id)
- applications(job_id, status)
- applications(candidate_id)
- organisation_memberships(organisation_id, user_id)

Indexes should be justified by actual query patterns.

