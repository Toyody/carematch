<?php

namespace App\Modules\Matching\Infrastructure\Persistence;

use App\Modules\Matching\Application\Contracts\MatchExplanationSource;
use App\Modules\Matching\Application\Data\MatchExplanationSourceData;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class PostgreSqlMatchExplanationSource implements MatchExplanationSource
{
    public function find(int $organisationId, int $jobId, int $candidateId, DateTimeImmutable $today, int $warningDays): ?MatchExplanationSourceData
    {
        $warningBoundary = $today->modify(sprintf('+%d days', max(0, $warningDays)))->format('Y-m-d');
        /** @var object{job_title: string, job_occupation: string|null, candidate_occupation: string|null, required_count: int|string, satisfied_count: int|string, attention_count: int|string, qualification_rank: int|string, occupation_rank: int|string, distance_km: float|string|null, application_status: string|null}|null $row */
        $row = DB::selectOne(<<<'SQL'
            SELECT jobs.title AS job_title, jobs.occupation AS job_occupation,
                candidates.occupation AS candidate_occupation,
                COALESCE(qualification.required_count, 0)::int AS required_count,
                COALESCE(qualification.satisfied_count, 0)::int AS satisfied_count,
                COALESCE(qualification.attention_count, 0)::int AS attention_count,
                COALESCE(qualification.qualification_rank, 0)::int AS qualification_rank,
                CASE
                    WHEN jobs.occupation IS NULL OR btrim(jobs.occupation) = ''
                      OR candidates.occupation IS NULL OR btrim(candidates.occupation) = '' THEN 1
                    WHEN lower(btrim(jobs.occupation)) = lower(btrim(candidates.occupation)) THEN 0
                    ELSE 2
                END AS occupation_rank,
                CASE WHEN jobs.location_geography IS NULL OR candidates.location_geography IS NULL THEN NULL
                    ELSE ST_Distance(candidates.location_geography, jobs.location_geography) / 1000.0 END AS distance_km,
                applications.status AS application_status
            FROM jobs
            JOIN candidates ON candidates.organisation_id = jobs.organisation_id AND candidates.id = :candidate_id
            LEFT JOIN applications ON applications.organisation_id = jobs.organisation_id
                AND applications.job_id = jobs.id AND applications.candidate_id = candidates.id
            LEFT JOIN LATERAL (
                SELECT count(*) AS required_count,
                    count(*) FILTER (WHERE state = 0) AS satisfied_count,
                    count(*) FILTER (WHERE state = 1) AS attention_count,
                    max(state) AS qualification_rank
                FROM (
                    SELECT CASE
                        WHEN EXISTS (SELECT 1 FROM candidate_qualifications cq
                            WHERE cq.organisation_id = req.organisation_id AND cq.candidate_id = candidates.id
                              AND cq.qualification_definition_id = req.qualification_definition_id
                              AND (cq.expires_on IS NULL OR cq.expires_on > :warning_boundary)) THEN 0
                        WHEN EXISTS (SELECT 1 FROM candidate_qualifications cq
                            WHERE cq.organisation_id = req.organisation_id AND cq.candidate_id = candidates.id
                              AND cq.qualification_definition_id = req.qualification_definition_id
                              AND cq.expires_on BETWEEN :today AND :warning_boundary) THEN 1
                        ELSE 2 END AS state
                    FROM job_qualification_requirements req
                    WHERE req.organisation_id = jobs.organisation_id AND req.job_id = jobs.id
                ) states
            ) qualification ON true
            WHERE jobs.organisation_id = :organisation_id AND jobs.id = :job_id
            SQL, [
            'organisation_id' => $organisationId,
            'job_id' => $jobId,
            'candidate_id' => $candidateId,
            'today' => $today->format('Y-m-d'),
            'warning_boundary' => $warningBoundary,
        ]);

        if ($row === null) {
            return null;
        }

        return new MatchExplanationSourceData(
            (string) $row->job_title,
            $row->job_occupation === null ? null : (string) $row->job_occupation,
            $row->candidate_occupation === null ? null : (string) $row->candidate_occupation,
            match ((int) $row->qualification_rank) {
                0 => 'satisfied', 1 => 'attention_required', default => 'not_satisfied'
            },
            (int) $row->required_count,
            (int) $row->satisfied_count,
            (int) $row->attention_count,
            match ((int) $row->occupation_rank) {
                0 => 'match', 1 => 'unknown', default => 'mismatch'
            },
            $row->distance_km === null ? null : (float) $row->distance_km,
            $row->application_status === null ? null : (string) $row->application_status,
        );
    }
}
