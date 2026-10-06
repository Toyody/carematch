\set ON_ERROR_STOP on

SELECT 'migration_count|' || count(*) FROM migrations;
SELECT 'users|' || count(*) FROM users;
SELECT 'organisations|' || count(*) FROM organisations;
SELECT 'organisation_memberships|' || count(*) FROM organisation_memberships;
SELECT 'candidates|' || count(*) FROM candidates;
SELECT 'jobs|' || count(*) FROM jobs;
SELECT 'applications|' || count(*) FROM applications;
SELECT 'application_status_history|' || count(*) FROM application_status_history;
SELECT 'qualification_definitions|' || count(*) FROM qualification_definitions;
SELECT 'candidate_qualifications|' || count(*) FROM candidate_qualifications;
SELECT 'job_qualification_requirements|' || count(*) FROM job_qualification_requirements;
SELECT 'candidate_documents|' || count(*) FROM candidate_documents;
SELECT 'audit_events|' || count(*) FROM audit_events;
SELECT 'compliance_expiry_digest_requests|' || count(*) FROM compliance_expiry_digest_requests;
SELECT 'ai_cv_extractions|' || count(*) FROM ai_cv_extractions;
SELECT 'ai_match_explanations|' || count(*) FROM ai_match_explanations;
SELECT 'postgis_extension|' || count(*) FROM pg_extension WHERE extname = 'postgis';
SELECT 'postgis_version|' || postgis_lib_version();
SELECT 'orphan_applications|' || count(*)
FROM applications a
LEFT JOIN candidates c ON c.organisation_id = a.organisation_id AND c.id = a.candidate_id
LEFT JOIN jobs j ON j.organisation_id = a.organisation_id AND j.id = a.job_id
WHERE c.id IS NULL OR j.id IS NULL;
SELECT 'orphan_history|' || count(*)
FROM application_status_history h
LEFT JOIN applications a ON a.organisation_id = h.organisation_id AND a.id = h.application_id
WHERE a.id IS NULL;
SELECT 'spatial_query_rows|' || count(*)
FROM candidates c
JOIN jobs j ON j.organisation_id = c.organisation_id
WHERE c.location_geography IS NOT NULL
  AND j.location_geography IS NOT NULL
  AND ST_DWithin(c.location_geography, j.location_geography, 200000);
