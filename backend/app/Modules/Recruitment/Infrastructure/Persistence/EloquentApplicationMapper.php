<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Recruitment\Application\Data\ApplicationRecord;
use App\Modules\Recruitment\Domain\ApplicationStatus;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;

final class EloquentApplicationMapper
{
    public static function toRecord(RecruitmentApplication $application, string $jobTitle): ApplicationRecord
    {
        $status = $application->getAttribute('status');
        $appliedAt = $application->getAttribute('applied_at');
        $createdAt = $application->getAttribute('created_at');
        $updatedAt = $application->getAttribute('updated_at');

        if (! $status instanceof ApplicationStatus
            || ! $appliedAt instanceof DateTimeInterface
            || ! $createdAt instanceof DateTimeInterface
            || ! $updatedAt instanceof DateTimeInterface) {
            throw new LogicException('The application has invalid persisted values.');
        }

        return new ApplicationRecord(
            id: (int) $application->getKey(),
            jobId: (int) $application->getAttribute('job_id'),
            jobTitle: $jobTitle,
            candidateId: (int) $application->getAttribute('candidate_id'),
            status: $status,
            appliedAt: DateTimeImmutable::createFromInterface($appliedAt),
            createdByUserId: (int) $application->getAttribute('created_by_user_id'),
            createdAt: DateTimeImmutable::createFromInterface($createdAt),
            updatedAt: DateTimeImmutable::createFromInterface($updatedAt),
        );
    }
}
