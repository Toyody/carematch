<?php

namespace App\Modules\Dashboard\Application\Data;

use DateTimeImmutable;

final readonly class DashboardActivity
{
    public function __construct(
        public int $applicationId,
        public int $candidateId,
        public string $candidateFirstName,
        public string $candidateLastName,
        public int $jobId,
        public string $jobTitle,
        public ?string $fromStatus,
        public string $toStatus,
        public DateTimeImmutable $changedAt,
    ) {}
}
