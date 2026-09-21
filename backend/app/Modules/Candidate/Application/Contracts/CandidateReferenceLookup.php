<?php

namespace App\Modules\Candidate\Application\Contracts;

use App\Modules\Candidate\Application\Data\CandidateReference;

interface CandidateReferenceLookup
{
    public function find(int $organisationId, int $candidateId): ?CandidateReference;

    /**
     * @param  list<int>  $candidateIds
     * @return array<int, CandidateReference>
     */
    public function findMany(int $organisationId, array $candidateIds): array;
}
