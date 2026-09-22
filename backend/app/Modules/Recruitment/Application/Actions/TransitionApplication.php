<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Candidate\Application\Contracts\CandidateReferenceLookup;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\ApplicationTransitioner;
use App\Modules\Recruitment\Application\Data\ApplicationSummary;
use App\Modules\Recruitment\Application\Exceptions\ApplicationNotFound;
use App\Modules\Recruitment\Domain\ApplicationStatus;
use LogicException;

final readonly class TransitionApplication
{
    public function __construct(
        private ApplicationTransitioner $applications,
        private CandidateReferenceLookup $candidates,
    ) {}

    public function handle(
        TenantContext $tenant,
        int $applicationId,
        ApplicationStatus $target,
        ?string $note,
    ): ApplicationSummary {
        $application = $this->applications->transition(
            $tenant->organisationId,
            $applicationId,
            $tenant->userId,
            $tenant->role,
            $target,
            $note,
        ) ?? throw new ApplicationNotFound;

        $candidate = $this->candidates->find($tenant->organisationId, $application->candidateId);
        if ($candidate === null) {
            throw new LogicException('An application references a missing candidate.');
        }

        return new ApplicationSummary(
            id: $application->id,
            jobId: $application->jobId,
            jobTitle: $application->jobTitle,
            candidateId: $candidate->id,
            candidateFirstName: $candidate->firstName,
            candidateLastName: $candidate->lastName,
            status: $application->status,
            appliedAt: $application->appliedAt,
            createdByUserId: $application->createdByUserId,
            createdAt: $application->createdAt,
            updatedAt: $application->updatedAt,
        );
    }
}
