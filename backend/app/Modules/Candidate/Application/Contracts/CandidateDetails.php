<?php

namespace App\Modules\Candidate\Application\Contracts;

use App\Modules\Candidate\Application\Data\CandidateRecord;

interface CandidateDetails
{
    public function find(int $organisationId, int $candidateId): ?CandidateRecord;
}
