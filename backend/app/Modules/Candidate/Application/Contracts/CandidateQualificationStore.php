<?php

namespace App\Modules\Candidate\Application\Contracts;

use App\Modules\Candidate\Application\Data\CandidateQualificationData;
use App\Modules\Candidate\Application\Data\CandidateQualificationRecord;

interface CandidateQualificationStore
{
    /** @return list<CandidateQualificationRecord>|null */
    public function all(int $organisationId, int $candidateId): ?array;

    public function create(int $organisationId, int $actorUserId, int $candidateId, CandidateQualificationData $data): ?CandidateQualificationRecord;

    public function update(int $organisationId, int $actorUserId, int $candidateId, int $credentialId, CandidateQualificationData $data): ?CandidateQualificationRecord;

    public function delete(int $organisationId, int $actorUserId, int $candidateId, int $credentialId): bool;
}
