<?php

namespace App\Modules\Candidate\Application\Data;

use DateTimeImmutable;

final readonly class CandidateDocumentRecord
{
    public function __construct(
        public int $id,
        public int $organisationId,
        public int $candidateId,
        public string $originalName,
        public string $storageKey,
        public string $mimeType,
        public int $sizeBytes,
        public int $uploadedByUserId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
