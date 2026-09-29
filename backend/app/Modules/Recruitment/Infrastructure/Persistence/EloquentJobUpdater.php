<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Recruitment\Application\Contracts\JobUpdater;
use App\Modules\Recruitment\Application\Data\JobRecord;
use App\Modules\Recruitment\Application\Exceptions\InvalidJobSchedule;
use App\Modules\Recruitment\Domain\JobSchedule;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final readonly class EloquentJobUpdater implements JobUpdater
{
    public function __construct(private AuditRecorder $audit) {}

    public function update(int $organisationId, int $actorUserId, int $jobId, array $changes): ?JobRecord
    {
        return DB::transaction(function () use ($organisationId, $actorUserId, $jobId, $changes): ?JobRecord {
            $job = Job::query()->where('organisation_id', $organisationId)->whereKey($jobId)->lockForUpdate()->first();
            if ($job === null) {
                return null;
            }

            $openedAt = array_key_exists('opened_at', $changes) ? $changes['opened_at'] : $job->getAttribute('opened_at');
            $closesAt = array_key_exists('closes_at', $changes) ? $changes['closes_at'] : $job->getAttribute('closes_at');
            if (($openedAt !== null && ! $openedAt instanceof DateTimeInterface)
                || ($closesAt !== null && ! $closesAt instanceof DateTimeInterface)
                || ! JobSchedule::isValid($openedAt, $closesAt)) {
                throw new InvalidJobSchedule;
            }

            $job->fill($changes);
            $changedFields = array_keys($job->getDirty());
            if ($changedFields !== []) {
                $job->save();
                $this->audit->record(new AuditEvent($organisationId, $actorUserId, 'job.updated', 'job', $jobId, ['changed_fields' => $changedFields]));
            }

            return EloquentJobMapper::toRecord($job);
        });
    }
}
