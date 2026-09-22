<?php

namespace App\Modules\Candidate\Application\Contracts;

use App\Modules\Candidate\Application\Data\CandidateDocumentRecord;
use App\Modules\Candidate\Application\Data\CandidateDocumentUpload;

interface CandidateDocumentStore
{
    /**
     * @return list<CandidateDocumentRecord>
     */
    public function list(int $organisationId, int $candidateId): array;

    public function create(
        int $organisationId,
        int $candidateId,
        int $uploadedByUserId,
        string $storageKey,
        CandidateDocumentUpload $upload,
    ): CandidateDocumentRecord;

    public function find(
        int $organisationId,
        int $candidateId,
        int $documentId,
    ): ?CandidateDocumentRecord;

    public function delete(
        int $organisationId,
        int $candidateId,
        int $documentId,
    ): bool;
}
