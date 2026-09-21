<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Recruitment\Application\Contracts\JobDetails;
use App\Modules\Recruitment\Application\Data\JobRecord;

final class EloquentJobDetails implements JobDetails
{
    public function find(int $organisationId, int $jobId): ?JobRecord
    {
        $job = Job::query()->where('organisation_id', $organisationId)->whereKey($jobId)->first();

        return $job === null ? null : EloquentJobMapper::toRecord($job);
    }
}
