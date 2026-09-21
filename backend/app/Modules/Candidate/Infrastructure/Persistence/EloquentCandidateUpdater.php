<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use App\Modules\Candidate\Application\Contracts\CandidateUpdater;
use App\Modules\Candidate\Application\Data\CandidateRecord;

final class EloquentCandidateUpdater implements CandidateUpdater
{
    public function update(
        int $organisationId,
        int $candidateId,
        array $changes,
    ): ?CandidateRecord {
        $candidate = Candidate::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($candidateId)
            ->first();

        if ($candidate === null) {
            return null;
        }

        $candidate->update($changes);

        return EloquentCandidateMapper::toRecord($candidate);
    }
}
