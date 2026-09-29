<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Recruitment\Application\Contracts\JobCreator;
use App\Modules\Recruitment\Application\Data\JobData;
use App\Modules\Recruitment\Application\Data\JobRecord;
use App\Modules\Recruitment\Domain\JobStatus;
use Illuminate\Support\Facades\DB;

final readonly class EloquentJobCreator implements JobCreator
{
    public function __construct(private AuditRecorder $audit) {}

    public function create(int $organisationId, int $actorUserId, JobData $data): JobRecord
    {
        return DB::transaction(function () use ($organisationId, $actorUserId, $data): JobRecord {
            $job = Job::query()->create([
                'organisation_id' => $organisationId, 'title' => $data->title,
                'occupation' => $data->occupation, 'location' => $data->location,
                'employment_type' => $data->employmentType, 'description' => $data->description,
                'status' => JobStatus::Draft, 'opened_at' => $data->openedAt, 'closes_at' => $data->closesAt,
            ]);
            $this->audit->record(new AuditEvent($organisationId, $actorUserId, 'job.created', 'job', (int) $job->getKey()));

            return EloquentJobMapper::toRecord($job);
        });
    }
}
