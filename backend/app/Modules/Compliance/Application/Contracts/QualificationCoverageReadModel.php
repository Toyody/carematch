<?php

namespace App\Modules\Compliance\Application\Contracts;

use App\Modules\Compliance\Domain\CredentialEvidence;
use App\Modules\Compliance\Domain\QualificationRequirement;

interface QualificationCoverageReadModel
{
    /** @return array{requirements: list<QualificationRequirement>, credentials: list<CredentialEvidence>}|null */
    public function forCandidateAndJob(int $organisationId, int $jobId, int $candidateId): ?array;
}
