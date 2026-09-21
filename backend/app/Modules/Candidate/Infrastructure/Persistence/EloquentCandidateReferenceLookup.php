<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use App\Modules\Candidate\Application\Contracts\CandidateReferenceLookup;
use App\Modules\Candidate\Application\Data\CandidateReference;

final class EloquentCandidateReferenceLookup implements CandidateReferenceLookup
{
    public function find(int $organisationId, int $candidateId): ?CandidateReference
    {
        $candidate = Candidate::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($candidateId)
            ->first(['id', 'first_name', 'last_name']);

        return $candidate === null ? null : self::toReference($candidate);
    }

    public function findMany(int $organisationId, array $candidateIds): array
    {
        if ($candidateIds === []) {
            return [];
        }

        return Candidate::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($candidateIds)
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(static fn (Candidate $candidate): array => [
                (int) $candidate->getKey() => self::toReference($candidate),
            ])->all();
    }

    private static function toReference(Candidate $candidate): CandidateReference
    {
        return new CandidateReference(
            id: (int) $candidate->getKey(),
            firstName: (string) $candidate->getAttribute('first_name'),
            lastName: (string) $candidate->getAttribute('last_name'),
        );
    }
}
