<?php

namespace App\Modules\Candidate\Application\Data;

use DateTimeImmutable;

final readonly class CandidateQualificationRecord
{
    public function __construct(
        public int $id,
        public int $qualificationDefinitionId,
        public string $qualificationName,
        public ?string $issuer,
        public ?string $credentialNumber,
        public ?DateTimeImmutable $issuedOn,
        public ?DateTimeImmutable $expiresOn,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
