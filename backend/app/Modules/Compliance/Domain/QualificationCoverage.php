<?php

namespace App\Modules\Compliance\Domain;

final readonly class QualificationCoverage
{
    /** @param list<QualificationRequirementResult> $requirements */
    public function __construct(
        public QualificationCoverageStatus $status,
        public array $requirements,
    ) {}
}
