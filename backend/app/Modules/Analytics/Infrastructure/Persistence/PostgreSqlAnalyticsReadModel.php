<?php

namespace App\Modules\Analytics\Infrastructure\Persistence;

use App\Modules\Analytics\Application\Contracts\AnalyticsReadModel;
use App\Modules\Analytics\Application\Data\AnalyticsPeriod;
use App\Modules\Analytics\Application\Data\AnalyticsSnapshot;
use Illuminate\Support\Facades\DB;
use LogicException;

final class PostgreSqlAnalyticsReadModel implements AnalyticsReadModel
{
    public function forOrganisation(int $organisationId, AnalyticsPeriod $period): AnalyticsSnapshot
    {
        $bindings = [
            'organisation_id' => $organisationId,
            'period_start' => $period->start()->format(DATE_ATOM),
            'period_end' => $period->endExclusive()->format(DATE_ATOM),
        ];

        $pipeline = $this->pipeline($bindings);

        return new AnalyticsSnapshot(
            period: $period,
            summary: [
                'new_candidates' => (int) $pipeline->new_candidates,
                'new_jobs' => (int) $pipeline->new_jobs,
                'applications' => (int) $pipeline->applications,
                'hired' => (int) $pipeline->hired_reached,
                'rejected' => (int) $pipeline->rejected_reached,
            ],
            funnel: [
                'applied' => (int) $pipeline->applications,
                'screening' => (int) $pipeline->screening_reached,
                'interview' => (int) $pipeline->interview_reached,
                'offer' => (int) $pipeline->offer_reached,
                'hired' => (int) $pipeline->hired_reached,
            ],
            currentStatuses: [
                'applied' => (int) $pipeline->current_applied,
                'screening' => (int) $pipeline->current_screening,
                'interview' => (int) $pipeline->current_interview,
                'offer' => (int) $pipeline->current_offer,
                'hired' => (int) $pipeline->current_hired,
                'rejected' => (int) $pipeline->current_rejected,
            ],
            applicationSeries: $this->applicationSeries($bindings),
            timeToStage: $this->timeToStage($bindings),
            jobs: $this->jobs($bindings),
        );
    }

    /**
     * @param  array{organisation_id: int, period_start: string, period_end: string}  $bindings
     * @return object{new_candidates: int|string, new_jobs: int|string, applications: int|string, screening_reached: int|string, interview_reached: int|string, offer_reached: int|string, hired_reached: int|string, rejected_reached: int|string, current_applied: int|string, current_screening: int|string, current_interview: int|string, current_offer: int|string, current_hired: int|string, current_rejected: int|string}
     */
    private function pipeline(array $bindings): object
    {
        /** @var object{new_candidates: int|string, new_jobs: int|string, applications: int|string, screening_reached: int|string, interview_reached: int|string, offer_reached: int|string, hired_reached: int|string, rejected_reached: int|string, current_applied: int|string, current_screening: int|string, current_interview: int|string, current_offer: int|string, current_hired: int|string, current_rejected: int|string}|null $row */
        $row = DB::selectOne(<<<'SQL'
            WITH cohort AS (
                SELECT applications.id, applications.status
                FROM applications
                WHERE applications.organisation_id = :organisation_id
                  AND applications.applied_at >= CAST(:period_start AS timestamptz)
                  AND applications.applied_at < CAST(:period_end AS timestamptz)
            ), reached AS (
                SELECT
                    cohort.id,
                    cohort.status,
                    COALESCE(bool_or(history.to_status = 'screening'), false) AS screening,
                    COALESCE(bool_or(history.to_status = 'interview'), false) AS interview,
                    COALESCE(bool_or(history.to_status = 'offer'), false) AS offer,
                    COALESCE(bool_or(history.to_status = 'hired'), false) AS hired,
                    COALESCE(bool_or(history.to_status = 'rejected'), false) AS rejected
                FROM cohort
                LEFT JOIN application_status_history history
                  ON history.organisation_id = :organisation_id
                 AND history.application_id = cohort.id
                GROUP BY cohort.id, cohort.status
            )
            SELECT
                (SELECT count(*) FROM candidates
                  WHERE organisation_id = :organisation_id
                    AND created_at >= CAST(:period_start AS timestamptz)
                    AND created_at < CAST(:period_end AS timestamptz))::int AS new_candidates,
                (SELECT count(*) FROM jobs
                  WHERE organisation_id = :organisation_id
                    AND created_at >= CAST(:period_start AS timestamptz)
                    AND created_at < CAST(:period_end AS timestamptz))::int AS new_jobs,
                count(*)::int AS applications,
                count(*) FILTER (WHERE screening)::int AS screening_reached,
                count(*) FILTER (WHERE interview)::int AS interview_reached,
                count(*) FILTER (WHERE offer)::int AS offer_reached,
                count(*) FILTER (WHERE hired)::int AS hired_reached,
                count(*) FILTER (WHERE rejected)::int AS rejected_reached,
                count(*) FILTER (WHERE status = 'applied')::int AS current_applied,
                count(*) FILTER (WHERE status = 'screening')::int AS current_screening,
                count(*) FILTER (WHERE status = 'interview')::int AS current_interview,
                count(*) FILTER (WHERE status = 'offer')::int AS current_offer,
                count(*) FILTER (WHERE status = 'hired')::int AS current_hired,
                count(*) FILTER (WHERE status = 'rejected')::int AS current_rejected
            FROM reached
            SQL, $bindings);

        if ($row === null) {
            throw new LogicException('The analytics pipeline query returned no result.');
        }

        return $row;
    }

    /**
     * @param  array{organisation_id: int, period_start: string, period_end: string}  $bindings
     * @return list<array{date: string, applications: int}>
     */
    private function applicationSeries(array $bindings): array
    {
        /** @var list<object{date: string, applications: int|string}> $rows */
        $rows = DB::select(<<<'SQL'
            SELECT
                to_char(days.day AT TIME ZONE 'UTC', 'YYYY-MM-DD') AS date,
                count(applications.id)::int AS applications
            FROM generate_series(
                CAST(:period_start AS timestamptz),
                CAST(:period_end AS timestamptz) - interval '1 day',
                interval '1 day'
            ) AS days(day)
            LEFT JOIN applications
              ON applications.organisation_id = :organisation_id
             AND applications.applied_at >= days.day
             AND applications.applied_at < days.day + interval '1 day'
            GROUP BY days.day
            ORDER BY days.day
            SQL, $bindings);

        return array_map(static fn (object $row): array => [
            'date' => (string) $row->date,
            'applications' => (int) $row->applications,
        ], $rows);
    }

    /**
     * @param  array{organisation_id: int, period_start: string, period_end: string}  $bindings
     * @return array{interview: array{median_days: float|null, sample_size: int}, hired: array{median_days: float|null, sample_size: int}}
     */
    private function timeToStage(array $bindings): array
    {
        $rows = DB::select(<<<'SQL'
            WITH cohort AS (
                SELECT id, applied_at
                FROM applications
                WHERE organisation_id = :organisation_id
                  AND applied_at >= CAST(:period_start AS timestamptz)
                  AND applied_at < CAST(:period_end AS timestamptz)
            ), first_stages AS (
                SELECT
                    cohort.id,
                    cohort.applied_at,
                    min(history.created_at) FILTER (WHERE history.to_status = 'interview') AS interview_at,
                    min(history.created_at) FILTER (WHERE history.to_status = 'hired') AS hired_at
                FROM cohort
                LEFT JOIN application_status_history history
                  ON history.organisation_id = :organisation_id
                 AND history.application_id = cohort.id
                GROUP BY cohort.id, cohort.applied_at
            ), durations AS (
                SELECT 'interview' AS stage,
                       extract(epoch FROM (interview_at - applied_at)) / 86400.0 AS days
                FROM first_stages WHERE interview_at >= applied_at
                UNION ALL
                SELECT 'hired' AS stage,
                       extract(epoch FROM (hired_at - applied_at)) / 86400.0 AS days
                FROM first_stages WHERE hired_at >= applied_at
            )
            SELECT stages.stage,
                   percentile_cont(0.5) WITHIN GROUP (ORDER BY durations.days) AS median_days,
                   count(durations.days)::int AS sample_size
            FROM (VALUES ('interview'), ('hired')) AS stages(stage)
            LEFT JOIN durations ON durations.stage = stages.stage
            GROUP BY stages.stage
            ORDER BY stages.stage
            SQL, $bindings);

        $result = [
            'interview' => ['median_days' => null, 'sample_size' => 0],
            'hired' => ['median_days' => null, 'sample_size' => 0],
        ];
        foreach ($rows as $row) {
            $stage = (string) $row->stage;
            if (! array_key_exists($stage, $result)) {
                throw new LogicException('The analytics query returned an unsupported stage.');
            }
            $result[$stage] = [
                'median_days' => $row->median_days === null ? null : round((float) $row->median_days, 2),
                'sample_size' => (int) $row->sample_size,
            ];
        }

        return $result;
    }

    /**
     * @param  array{organisation_id: int, period_start: string, period_end: string}  $bindings
     * @return list<array{job_id: int, job_title: string, applications: int, interview: int, offer: int, hired: int, rejected: int}>
     */
    private function jobs(array $bindings): array
    {
        /** @var list<object{job_id: int|string, job_title: string, applications: int|string, interview: int|string, offer: int|string, hired: int|string, rejected: int|string}> $rows */
        $rows = DB::select(<<<'SQL'
            WITH cohort AS (
                SELECT applications.id, applications.job_id
                FROM applications
                WHERE applications.organisation_id = :organisation_id
                  AND applications.applied_at >= CAST(:period_start AS timestamptz)
                  AND applications.applied_at < CAST(:period_end AS timestamptz)
            ), reached AS (
                SELECT
                    cohort.id,
                    cohort.job_id,
                    COALESCE(bool_or(history.to_status = 'interview'), false) AS interview,
                    COALESCE(bool_or(history.to_status = 'offer'), false) AS offer,
                    COALESCE(bool_or(history.to_status = 'hired'), false) AS hired,
                    COALESCE(bool_or(history.to_status = 'rejected'), false) AS rejected
                FROM cohort
                LEFT JOIN application_status_history history
                  ON history.organisation_id = :organisation_id
                 AND history.application_id = cohort.id
                GROUP BY cohort.id, cohort.job_id
            )
            SELECT
                jobs.id AS job_id,
                jobs.title AS job_title,
                count(reached.id)::int AS applications,
                count(*) FILTER (WHERE reached.interview)::int AS interview,
                count(*) FILTER (WHERE reached.offer)::int AS offer,
                count(*) FILTER (WHERE reached.hired)::int AS hired,
                count(*) FILTER (WHERE reached.rejected)::int AS rejected
            FROM reached
            JOIN jobs
              ON jobs.organisation_id = :organisation_id
             AND jobs.id = reached.job_id
            GROUP BY jobs.id, jobs.title
            ORDER BY applications DESC, hired DESC, jobs.id ASC
            LIMIT 10
            SQL, $bindings);

        return array_map(static fn (object $row): array => [
            'job_id' => (int) $row->job_id,
            'job_title' => (string) $row->job_title,
            'applications' => (int) $row->applications,
            'interview' => (int) $row->interview,
            'offer' => (int) $row->offer,
            'hired' => (int) $row->hired,
            'rejected' => (int) $row->rejected,
        ], $rows);
    }
}
