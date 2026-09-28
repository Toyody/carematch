<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\JobQualificationRequirementStore;
use App\Modules\Recruitment\Application\Data\JobQualificationRequirementRecord;
use App\Modules\Recruitment\Application\Exceptions\JobQualificationRequirementNotFound;

final readonly class AddJobQualificationRequirement
{
    public function __construct(private JobQualificationRequirementStore $store) {}

    public function handle(TenantContext $tenant, int $jobId, int $definitionId): JobQualificationRequirementRecord
    {
        return $this->store->add($tenant->organisationId, $jobId, $definitionId) ?? throw new JobQualificationRequirementNotFound;
    }
}
