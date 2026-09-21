<?php

namespace App\Modules\Candidate\Application\Contracts;

use App\Modules\Candidate\Application\Data\CandidateRecord;

interface CandidateUpdater
{
    /**
     * @param  array<string, string|null>  $changes
     */
    public function update(int $organisationId, int $candidateId, array $changes): ?CandidateRecord;
}
