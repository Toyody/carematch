<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\JobQualificationRequirementStore;
use App\Modules\Recruitment\Application\Exceptions\JobQualificationRequirementNotFound;

final readonly class DeleteJobQualificationRequirement
{
    public function __construct(private JobQualificationRequirementStore $store) {}

    public function handle(TenantContext $tenant, int $jobId, int $requirementId): void
    {
        if (! $this->store->delete($tenant->organisationId, $tenant->userId, $jobId, $requirementId)) {
            throw new JobQualificationRequirementNotFound;
        }
    }
}
