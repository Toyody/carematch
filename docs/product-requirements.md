# CareMatch Product Requirements

## 1. Product Overview

CareMatch is a healthcare workforce and recruitment management platform.

It allows healthcare organisations and recruitment agencies to manage
candidates, vacancies and recruitment workflows in one system.

## 2. Target Users

### Organisation Admin
Can:
- manage organisation settings
- manage users and memberships
- manage roles

### Recruiter
Can:
- manage candidates
- create jobs
- manage applications
- move applicants through recruitment stages

### Hiring Manager
Can:
- review candidates
- review applications
- participate in hiring decisions

## 3. MVP Scope

The MVP must support:

- Authentication
- Organisation management
- Organisation memberships
- Role-based access control
- Candidate management
- Job management
- Application management
- Recruitment pipeline
- Dashboard
- Search
- Filtering
- Pagination
- Candidate document upload

## 4. Core User Flow

A recruiter should be able to:

1. Log in
2. Access their organisation
3. Create a job
4. Create or view candidates
5. Create an application
6. Review the application
7. Move the applicant through the recruitment pipeline

## 5. Recruitment Pipeline

Application statuses:

- Applied
- Screening
- Interview
- Offer
- Hired
- Rejected

Define valid transitions and business rules.

## 6. Candidate Information

Candidates may contain:

- name
- occupation
- email
- phone
- location
- skills
- availability
- employment history
- notes
- uploaded documents

## 7. Job Information

Jobs may contain:

- title
- occupation
- location
- employment type
- salary or hourly rate
- description
- required skills
- status
- opening date
- closing date

## 8. Non-MVP Features

The following are planned after the core MVP:

- Compliance management
- Candidate matching
- PostGIS distance matching
- Qualification expiry notifications
- SQS background processing
- Redis
- AI CV parsing
- AI-generated match explanations
- Advanced analytics
- Observability