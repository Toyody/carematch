<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Recruitment\Application\Contracts\JobLifecycleTransitioner;
use App\Modules\Recruitment\Application\Data\JobRecord;
use App\Modules\Recruitment\Application\Exceptions\JobTransitionNotAllowed;
use App\Modules\Recruitment\Domain\JobStatus;
use Illuminate\Support\Facades\DB;

final class EloquentJobLifecycleTransitioner implements JobLifecycleTransitioner
{
    public function transition(int $organisationId, int $jobId, JobStatus $target): ?JobRecord
    {
        return DB::transaction(function () use ($organisationId, $jobId, $target): ?JobRecord {
            $job = Job::query()->where('organisation_id', $organisationId)->whereKey($jobId)->lockForUpdate()->first();
            if ($job === null) {
                return null;
            }

            $current = $job->getAttribute('status');
            if (! $current instanceof JobStatus || ! $current->canTransitionTo($target)) {
                throw new JobTransitionNotAllowed;
            }

            $job->update(['status' => $target]);

            return EloquentJobMapper::toRecord($job);
        });
    }
}
