<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Candidate\Application\Contracts\CandidateReferenceLookup;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\ApplicationDetails;
use App\Modules\Recruitment\Application\Data\ApplicationSummary;
use App\Modules\Recruitment\Application\Exceptions\ApplicationNotFound;
use LogicException;

final readonly class ViewApplication
{
    public function __construct(
        private ApplicationDetails $applications,
        private CandidateReferenceLookup $candidates,
    ) {}

    public function handle(TenantContext $tenant, int $applicationId): ApplicationSummary
    {
        $application = $this->applications->find($tenant->organisationId, $applicationId)
            ?? throw new ApplicationNotFound;
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
