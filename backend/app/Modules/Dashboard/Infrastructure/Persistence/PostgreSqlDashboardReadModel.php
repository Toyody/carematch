<?php

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Modules\Dashboard\Application\Contracts\DashboardReadModel;
use App\Modules\Dashboard\Application\Data\DashboardActivity;
use App\Modules\Dashboard\Application\Data\DashboardSnapshot;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use stdClass;

final class PostgreSqlDashboardReadModel implements DashboardReadModel
{
    private const array APPLICATION_STATUSES = [
        'applied',
        'screening',
        'interview',
        'offer',
        'hired',
        'rejected',
    ];

    private const int ACTIVITY_LIMIT = 5;

    public function forOrganisation(int $organisationId): DashboardSnapshot
    {
        $applicationCounts = array_fill_keys(self::APPLICATION_STATUSES, 0);

        foreach ($this->applicationCounts($organisationId) as $row) {
            $status = $row->status;

            if (! is_string($status) || ! array_key_exists($status, $applicationCounts)) {
                throw new LogicException('The dashboard encountered an invalid application status.');
            }

            $applicationCounts[$status] = (int) $row->total;
        }

        return new DashboardSnapshot(
            candidateCount: DB::table('candidates')
                ->where('organisation_id', $organisationId)
                ->count(),
            openJobCount: DB::table('jobs')
                ->where('organisation_id', $organisationId)
                ->where('status', 'open')
                ->count(),
            applicationCounts: $applicationCounts,
            recentApplicationActivity: $this->recentActivity($organisationId),
        );
    }

    /** @return list<stdClass> */
    private function applicationCounts(int $organisationId): array
    {
        return array_values(DB::table('applications')
            ->selectRaw('status, count(*) as total')
            ->where('organisation_id', $organisationId)
            ->groupBy('status')
            ->get()
            ->all());
    }

    /** @return list<DashboardActivity> */
    private function recentActivity(int $organisationId): array
    {
        return array_values(DB::table('application_status_history as history')
            ->join('applications', function ($join): void {
                $join->on('applications.organisation_id', '=', 'history.organisation_id')
                    ->on('applications.id', '=', 'history.application_id');
            })
            ->join('candidates', function ($join): void {
                $join->on('candidates.organisation_id', '=', 'applications.organisation_id')
                    ->on('candidates.id', '=', 'applications.candidate_id');
            })
            ->join('jobs', function ($join): void {
                $join->on('jobs.organisation_id', '=', 'applications.organisation_id')
                    ->on('jobs.id', '=', 'applications.job_id');
            })
            ->where('history.organisation_id', $organisationId)
            ->orderByDesc('history.created_at')
            ->orderByDesc('history.id')
            ->limit(self::ACTIVITY_LIMIT)
            ->get([
                'history.application_id',
                'history.from_status',
                'history.to_status',
                'history.created_at as changed_at',
                'candidates.id as candidate_id',
                'candidates.first_name as candidate_first_name',
                'candidates.last_name as candidate_last_name',
                'jobs.id as job_id',
                'jobs.title as job_title',
            ])
            ->map(static function (stdClass $row): DashboardActivity {
                $changedAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $row->changed_at)
                    ?: new DateTimeImmutable((string) $row->changed_at);

                return new DashboardActivity(
                    applicationId: (int) $row->application_id,
                    candidateId: (int) $row->candidate_id,
                    candidateFirstName: (string) $row->candidate_first_name,
                    candidateLastName: (string) $row->candidate_last_name,
                    jobId: (int) $row->job_id,
                    jobTitle: (string) $row->job_title,
                    fromStatus: is_string($row->from_status) ? $row->from_status : null,
                    toStatus: (string) $row->to_status,
                    changedAt: $changedAt,
                );
            })
            ->values()
            ->all());
    }
}
