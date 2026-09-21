<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Recruitment\Application\Contracts\ApplicationDetails;
use App\Modules\Recruitment\Application\Data\ApplicationRecord;

final class EloquentApplicationDetails implements ApplicationDetails
{
    public function find(int $organisationId, int $applicationId): ?ApplicationRecord
    {
        $application = RecruitmentApplication::query()
            ->join('jobs', function ($join): void {
                $join->on('jobs.organisation_id', '=', 'applications.organisation_id')
                    ->on('jobs.id', '=', 'applications.job_id');
            })
            ->where('applications.organisation_id', $organisationId)
            ->where('applications.id', $applicationId)
            ->first(['applications.*', 'jobs.title as job_title']);

        return $application === null ? null : EloquentApplicationMapper::toRecord(
            $application,
            (string) $application->getAttribute('job_title'),
        );
    }
}
