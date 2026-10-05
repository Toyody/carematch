<?php

namespace App\Modules\Ai\Application\Data;

final readonly class MatchExplanationInput
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

    /** @return array<string, float|int|string|null> */
    public function facts(): array
    {
        return get_object_vars($this);
    }

    public function fingerprint(): string
    {
        return hash('sha256', (string) json_encode($this->facts(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
