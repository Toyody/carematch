<?php

namespace App\Modules\Recruitment\Application\Data;

use DateTimeImmutable;

final readonly class JobQualificationRequirementRecord
{
    public function __construct(
        public int $id,
        public int $qualificationDefinitionId,
        public string $qualificationName,
        public DateTimeImmutable $createdAt,
    ) {}
}
