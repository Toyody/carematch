<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Recruitment\Application\Authorization\ApplicationTransitionPermissions;
use App\Modules\Recruitment\Application\Contracts\ApplicationTransitioner;
use App\Modules\Recruitment\Application\Data\ApplicationRecord;
use App\Modules\Recruitment\Application\Exceptions\ApplicationTransitionForbidden;
use App\Modules\Recruitment\Application\Exceptions\ApplicationTransitionNotAllowed;
use App\Modules\Recruitment\Domain\ApplicationStatus;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class EloquentApplicationTransitioner implements ApplicationTransitioner
{
    public function __construct(private ApplicationTransitionPermissions $permissions) {}

    public function transition(
        int $organisationId,
        int $applicationId,
        int $actorUserId,
        OrganisationRole $role,
        ApplicationStatus $target,
        ?string $note,
    ): ?ApplicationRecord {
        return DB::transaction(function () use (
            $organisationId,
            $applicationId,
            $actorUserId,
            $role,
            $target,
            $note,
        ): ?ApplicationRecord {
            $application = RecruitmentApplication::query()
                ->where('organisation_id', $organisationId)
                ->whereKey($applicationId)
                ->lockForUpdate()
                ->first();

            if ($application === null) {
                return null;
            }

            $current = $application->getAttribute('status');
            if (! $current instanceof ApplicationStatus) {
                throw new LogicException('The application has an invalid persisted status.');
            }

            if (! $this->permissions->allows($role, $current, $target)) {
                throw new ApplicationTransitionForbidden;
            }

            if (! $current->canTransitionTo($target)) {
                throw new ApplicationTransitionNotAllowed;
            }

            $application->update(['status' => $target]);

            ApplicationStatusHistory::query()->create([
                'organisation_id' => $organisationId,
                'application_id' => $applicationId,
                'from_status' => $current,
                'to_status' => $target,
                'changed_by_user_id' => $actorUserId,
                'note' => $note,
            ]);

            $jobTitle = Job::query()
                ->where('organisation_id', $organisationId)
                ->whereKey($application->getAttribute('job_id'))
                ->value('title');

            if (! is_string($jobTitle)) {
                throw new LogicException('An application references a missing job.');
            }

            return EloquentApplicationMapper::toRecord($application, $jobTitle);
        });
    }
}
