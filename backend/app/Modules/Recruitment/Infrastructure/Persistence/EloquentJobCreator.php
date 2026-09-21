<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Recruitment\Application\Contracts\JobCreator;
use App\Modules\Recruitment\Application\Data\JobData;
use App\Modules\Recruitment\Application\Data\JobRecord;
use App\Modules\Recruitment\Domain\JobStatus;

final class EloquentJobCreator implements JobCreator
{
    public function create(int $organisationId, JobData $data): JobRecord
    {
        $job = Job::query()->create([
            'organisation_id' => $organisationId,
            'title' => $data->title,
            'occupation' => $data->occupation,
            'location' => $data->location,
            'employment_type' => $data->employmentType,
            'description' => $data->description,
            'status' => JobStatus::Draft,
            'opened_at' => $data->openedAt,
            'closes_at' => $data->closesAt,
        ]);

        return EloquentJobMapper::toRecord($job);
    }
}
