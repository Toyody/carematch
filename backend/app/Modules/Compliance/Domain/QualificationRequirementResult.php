<?php

namespace App\Modules\Compliance\Domain;

use DateTimeImmutable;

final readonly class QualificationRequirementResult
{
    public function __construct(
        public int $qualificationDefinitionId,
        public string $name,
        public QualificationStatus $status,
        public ?int $candidateQualificationId,
        public ?DateTimeImmutable $expiresOn,
    ) {}
}
