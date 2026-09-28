<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\JobQualificationRequirementStore;
use App\Modules\Recruitment\Application\Data\JobQualificationRequirementRecord;
use App\Modules\Recruitment\Application\Exceptions\JobQualificationRequirementNotFound;

final readonly class ListJobQualificationRequirements
{
    public function __construct(private JobQualificationRequirementStore $store) {}

    /** @return list<JobQualificationRequirementRecord> */
    public function handle(TenantContext $tenant, int $jobId): array
    {
        return $this->store->all($tenant->organisationId, $jobId) ?? throw new JobQualificationRequirementNotFound;
    }
}
