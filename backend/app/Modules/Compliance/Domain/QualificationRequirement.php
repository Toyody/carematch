<?php

namespace App\Modules\Compliance\Domain;

final readonly class QualificationRequirement
{
    public function __construct(
        public int $qualificationDefinitionId,
        public string $name,
    ) {}
}
