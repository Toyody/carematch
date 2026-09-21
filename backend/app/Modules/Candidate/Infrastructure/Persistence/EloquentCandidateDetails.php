<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use App\Modules\Candidate\Application\Contracts\CandidateDetails;
use App\Modules\Candidate\Application\Data\CandidateRecord;

final class EloquentCandidateDetails implements CandidateDetails
{
    public function find(int $organisationId, int $candidateId): ?CandidateRecord
    {
        $candidate = Candidate::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($candidateId)
            ->first();

        return $candidate === null
            ? null
            : EloquentCandidateMapper::toRecord($candidate);
    }
}
