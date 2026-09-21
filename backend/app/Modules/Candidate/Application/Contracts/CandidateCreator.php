<?php

namespace App\Modules\Candidate\Application\Contracts;

use App\Modules\Candidate\Application\Data\CandidateData;
use App\Modules\Candidate\Application\Data\CandidateRecord;

interface CandidateCreator
{
    public function create(int $organisationId, CandidateData $data): CandidateRecord;
}
