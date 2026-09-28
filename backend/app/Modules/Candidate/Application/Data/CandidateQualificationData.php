<?php

namespace App\Modules\Candidate\Application\Data;

use DateTimeImmutable;

final readonly class CandidateQualificationData
{
    public function __construct(
        public int $qualificationDefinitionId,
        public ?string $issuer,
        public ?string $credentialNumber,
        public ?DateTimeImmutable $issuedOn,
        public ?DateTimeImmutable $expiresOn,
    ) {}
}
