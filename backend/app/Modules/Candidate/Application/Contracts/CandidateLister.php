<?php

namespace App\Modules\Candidate\Application\Contracts;

use App\Modules\Candidate\Application\Data\CandidateListCriteria;
use App\Modules\Candidate\Application\Data\CandidatePage;

interface CandidateLister
{
    public function list(int $organisationId, CandidateListCriteria $criteria): CandidatePage;
}
