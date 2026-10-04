<?php

namespace App\Modules\Matching\Application\Data;

use App\Modules\Matching\Domain\OccupationMatchStatus;
use App\Modules\Matching\Domain\QualificationMatchStatus;

final readonly class CandidateMatch
{
    public function __construct(
        public int $rank,
        public int $candidateId,
        public string $firstName,
        public string $lastName,
        public ?string $occupation,
        public ?string $location,
        public QualificationMatchStatus $qualificationStatus,
        public int $qualificationRequirementCount,
        public int $qualificationSatisfiedCount,
        public int $qualificationAttentionCount,
        public OccupationMatchStatus $occupationStatus,
        public ?float $distanceKm,
        public ?string $applicationStatus,
    ) {}
}
