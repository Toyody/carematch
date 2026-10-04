<?php

namespace App\Modules\Analytics\Application\Data;

final readonly class AnalyticsSnapshot
{
    /**
     * @param  array{new_candidates: int, new_jobs: int, applications: int, hired: int, rejected: int}  $summary
     * @param  array{applied: int, screening: int, interview: int, offer: int, hired: int}  $funnel
     * @param  array{applied: int, screening: int, interview: int, offer: int, hired: int, rejected: int}  $currentStatuses
     * @param  list<array{date: string, applications: int}>  $applicationSeries
     * @param  array{interview: array{median_days: float|null, sample_size: int}, hired: array{median_days: float|null, sample_size: int}}  $timeToStage
     * @param  list<array{job_id: int, job_title: string, applications: int, interview: int, offer: int, hired: int, rejected: int}>  $jobs
     */
    public function __construct(
        public AnalyticsPeriod $period,
        public array $summary,
        public array $funnel,
        public array $currentStatuses,
        public array $applicationSeries,
        public array $timeToStage,
        public array $jobs,
    ) {}
}
