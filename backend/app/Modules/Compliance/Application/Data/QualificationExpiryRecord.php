<?php

namespace App\Modules\Compliance\Application\Data;

use App\Modules\Compliance\Domain\QualificationStatus;
use DateTimeImmutable;

final readonly class QualificationExpiryRecord
{
    public function __construct(
        public int $candidateQualificationId,
        public int $candidateId,
        public string $candidateName,
        public int $qualificationDefinitionId,
        public string $qualificationName,
        public DateTimeImmutable $expiresOn,
        public QualificationStatus $status,
    ) {}
}
