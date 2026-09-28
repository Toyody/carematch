<?php

namespace App\Modules\Recruitment\Application\Contracts;

use App\Modules\Recruitment\Application\Data\JobQualificationRequirementRecord;

interface JobQualificationRequirementStore
{
    /** @return list<JobQualificationRequirementRecord>|null */
    public function all(int $organisationId, int $jobId): ?array;

    public function add(int $organisationId, int $jobId, int $definitionId): ?JobQualificationRequirementRecord;

    public function delete(int $organisationId, int $jobId, int $requirementId): bool;
}
