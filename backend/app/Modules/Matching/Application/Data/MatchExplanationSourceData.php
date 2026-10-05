<?php

namespace App\Modules\Matching\Application\Data;

final readonly class MatchExplanationSourceData
{
    public function __construct(
        public string $jobTitle,
        public ?string $jobOccupation,
        public ?string $candidateOccupation,
        public string $qualificationStatus,
        public int $qualificationRequiredCount,
        public int $qualificationSatisfiedCount,
        public int $qualificationAttentionCount,
        public string $occupationStatus,
        public ?float $distanceKm,
        public ?string $applicationStatus,
    ) {}
}
