<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Recruitment\Application\Contracts\JobLifecycleTransitioner;
use App\Modules\Recruitment\Application\Data\JobRecord;
use App\Modules\Recruitment\Application\Exceptions\JobTransitionNotAllowed;
use App\Modules\Recruitment\Domain\JobStatus;
use Illuminate\Support\Facades\DB;

final readonly class EloquentJobLifecycleTransitioner implements JobLifecycleTransitioner
{
    public function __construct(private AuditRecorder $audit) {}

    public function transition(int $organisationId, int $actorUserId, int $jobId, JobStatus $target): ?JobRecord
    {
        return DB::transaction(function () use ($organisationId, $actorUserId, $jobId, $target): ?JobRecord {
            $job = Job::query()->where('organisation_id', $organisationId)->whereKey($jobId)->lockForUpdate()->first();
            if ($job === null) {
                return null;
            }

            $current = $job->getAttribute('status');
            if (! $current instanceof JobStatus || ! $current->canTransitionTo($target)) {
                throw new JobTransitionNotAllowed;
            }

            $job->update(['status' => $target]);
            $eventType = match ($target) {
                JobStatus::Open => 'job.opened',
                JobStatus::Closed => 'job.closed',
                JobStatus::Archived => 'job.archived',
                JobStatus::Draft => 'job.updated',
            };
            $this->audit->record(new AuditEvent($organisationId, $actorUserId, $eventType, 'job', $jobId, [
                'from_status' => $current->value, 'to_status' => $target->value,
            ]));

            return EloquentJobMapper::toRecord($job);
        });
    }
}
