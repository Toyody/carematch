<?php

namespace App\Modules\Matching\Infrastructure\Persistence;

use App\Modules\Matching\Application\Contracts\CandidateMatchReadModel;
use App\Modules\Matching\Application\Data\CandidateMatch;
use App\Modules\Matching\Application\Data\CandidateMatchPage;
use App\Modules\Matching\Application\Data\MatchCriteria;
use App\Modules\Matching\Application\Exceptions\JobCoordinatesRequired;
use App\Modules\Matching\Domain\OccupationMatchStatus;
use App\Modules\Matching\Domain\QualificationMatchStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class PostgreSqlCandidateMatchReadModel implements CandidateMatchReadModel
{
    public function forJob(
        int $organisationId,
        int $jobId,
        MatchCriteria $criteria,
        DateTimeImmutable $today,
        int $qualificationWarningDays,
    ): ?CandidateMatchPage {
        $job = DB::table('jobs')
            ->where('organisation_id', $organisationId)
            ->where('id', $jobId)
            ->selectRaw('id, location_geography IS NOT NULL AS has_coordinates')
            ->first();

        if ($job === null) {
            return null;
        }

        if ($criteria->maxDistanceKm !== null && ! (bool) $job->has_coordinates) {
            throw new JobCoordinatesRequired;
        }

        $todayValue = $today->format('Y-m-d');
        $warningBoundary = $today->modify(sprintf('+%d days', max(0, $qualificationWarningDays)))->format('Y-m-d');
        $radiusPredicate = $criteria->maxDistanceKm === null
            ? ''
            : 'AND candidates.location_geography IS NOT NULL AND ST_DWithin(candidates.location_geography, target_job.location_geography, :radius_metres)';

        $bindings = [
            'organisation_id' => $organisationId,
            'job_id' => $jobId,
            'today' => $todayValue,
            'warning_boundary' => $warningBoundary,
        ];
        if ($criteria->maxDistanceKm !== null) {
            $bindings['radius_metres'] = $criteria->maxDistanceKm * 1000;
        }

        $offset = ($criteria->page - 1) * $criteria->perPage;
        $limit = $criteria->perPage;
        /** @var list<object{candidate_id: int|string|null, first_name: string|null, last_name: string|null, occupation: string|null, location: string|null, qualification_requirement_count: int|string|null, qualification_satisfied_count: int|string|null, qualification_attention_count: int|string|null, qualification_rank: int|string|null, occupation_rank: int|string|null, distance_km: float|string|null, application_status: string|null, match_rank: int|string|null, total_count: int|string}> $rows */
        $rows = DB::select(<<<SQL
            WITH target_job AS (
                SELECT id, organisation_id, occupation, location_geography
                FROM jobs
                WHERE organisation_id = :organisation_id AND id = :job_id
            ), candidate_signals AS (
                SELECT
                    candidates.id AS candidate_id,
                    candidates.first_name,
                    candidates.last_name,
                    candidates.occupation,
                    candidates.location,
                    COALESCE(qualification.required_count, 0)::int AS qualification_requirement_count,
                    COALESCE(qualification.satisfied_count, 0)::int AS qualification_satisfied_count,
                    COALESCE(qualification.attention_count, 0)::int AS qualification_attention_count,
                    COALESCE(qualification.qualification_rank, 0)::int AS qualification_rank,
                    CASE
                        WHEN target_job.occupation IS NULL OR btrim(target_job.occupation) = ''
                            OR candidates.occupation IS NULL OR btrim(candidates.occupation) = '' THEN 1
                        WHEN lower(btrim(target_job.occupation)) = lower(btrim(candidates.occupation)) THEN 0
                        ELSE 2
                    END AS occupation_rank,
                    CASE
                        WHEN target_job.location_geography IS NULL OR candidates.location_geography IS NULL THEN NULL
                        ELSE ST_Distance(candidates.location_geography, target_job.location_geography) / 1000.0
                    END AS distance_km,
                    applications.status AS application_status
                FROM candidates
                CROSS JOIN target_job
                LEFT JOIN applications
                    ON applications.organisation_id = candidates.organisation_id
                    AND applications.job_id = target_job.id
                    AND applications.candidate_id = candidates.id
                LEFT JOIN LATERAL (
                    SELECT
                        count(*) AS required_count,
                        count(*) FILTER (WHERE requirement_state = 0) AS satisfied_count,
                        count(*) FILTER (WHERE requirement_state = 1) AS attention_count,
                        max(requirement_state) AS qualification_rank
                    FROM (
                        SELECT CASE
                            WHEN EXISTS (
                                SELECT 1 FROM candidate_qualifications credentials
                                WHERE credentials.organisation_id = requirements.organisation_id
                                  AND credentials.candidate_id = candidates.id
                                  AND credentials.qualification_definition_id = requirements.qualification_definition_id
                                  AND (credentials.expires_on IS NULL OR credentials.expires_on > :warning_boundary)
                            ) THEN 0
                            WHEN EXISTS (
                                SELECT 1 FROM candidate_qualifications credentials
                                WHERE credentials.organisation_id = requirements.organisation_id
                                  AND credentials.candidate_id = candidates.id
                                  AND credentials.qualification_definition_id = requirements.qualification_definition_id
                                  AND credentials.expires_on BETWEEN :today AND :warning_boundary
                            ) THEN 1
                            ELSE 2
                        END AS requirement_state
                        FROM job_qualification_requirements requirements
                        WHERE requirements.organisation_id = target_job.organisation_id
                          AND requirements.job_id = target_job.id
                    ) requirement_states
                ) qualification ON true
                WHERE candidates.organisation_id = target_job.organisation_id
                {$radiusPredicate}
            ), ranked AS (
                SELECT
                    candidate_signals.*,
                    row_number() OVER (
                        ORDER BY qualification_rank ASC, occupation_rank ASC,
                            distance_km ASC NULLS LAST, candidate_id ASC
                    )::int AS match_rank
                FROM candidate_signals
            ), page_rows AS (
                SELECT * FROM ranked
                WHERE match_rank > {$offset} AND match_rank <= {$offset} + {$limit}
            ), summary AS (
                SELECT count(*)::int AS total_count FROM candidate_signals
            )
            SELECT page_rows.*, summary.total_count
            FROM summary
            LEFT JOIN page_rows ON true
            ORDER BY page_rows.match_rank
            SQL, $bindings);

        $total = (int) $rows[0]->total_count;
        /** @var list<object{candidate_id: int|string, first_name: string, last_name: string, occupation: string|null, location: string|null, qualification_requirement_count: int|string, qualification_satisfied_count: int|string, qualification_attention_count: int|string, qualification_rank: int|string, occupation_rank: int|string, distance_km: float|string|null, application_status: string|null, match_rank: int|string, total_count: int|string}> $matchRows */
        $matchRows = array_values(array_filter($rows, static fn (object $row): bool => $row->candidate_id !== null));
        $items = array_map(fn (object $row): CandidateMatch => $this->map($row), $matchRows);

        return new CandidateMatchPage(
            items: $items,
            currentPage: $criteria->page,
            lastPage: max(1, (int) ceil($total / $criteria->perPage)),
            perPage: $criteria->perPage,
            total: $total,
        );
    }

    /**
     * @param  object{candidate_id: int|string, first_name: string, last_name: string, occupation: string|null, location: string|null, qualification_requirement_count: int|string, qualification_satisfied_count: int|string, qualification_attention_count: int|string, qualification_rank: int|string, occupation_rank: int|string, distance_km: float|string|null, application_status: string|null, match_rank: int|string, total_count: int|string}  $row
     */
    private function map(object $row): CandidateMatch
    {
        $qualificationRank = (int) $row->qualification_rank;
        $occupationRank = (int) $row->occupation_rank;

        return new CandidateMatch(
            rank: (int) $row->match_rank,
            candidateId: (int) $row->candidate_id,
            firstName: (string) $row->first_name,
            lastName: (string) $row->last_name,
            occupation: $row->occupation === null ? null : (string) $row->occupation,
            location: $row->location === null ? null : (string) $row->location,
            qualificationStatus: match ($qualificationRank) {
                0 => QualificationMatchStatus::Satisfied,
                1 => QualificationMatchStatus::AttentionRequired,
                default => QualificationMatchStatus::NotSatisfied,
            },
            qualificationRequirementCount: (int) $row->qualification_requirement_count,
            qualificationSatisfiedCount: (int) $row->qualification_satisfied_count,
            qualificationAttentionCount: (int) $row->qualification_attention_count,
            occupationStatus: match ($occupationRank) {
                0 => OccupationMatchStatus::Match,
                1 => OccupationMatchStatus::Unknown,
                default => OccupationMatchStatus::Mismatch,
            },
            distanceKm: $row->distance_km === null ? null : (float) $row->distance_km,
            applicationStatus: $row->application_status === null ? null : (string) $row->application_status,
        );
    }
}
