<?php

namespace App\Modules\Recruitment\Application\Data;

use App\Modules\Recruitment\Domain\ApplicationStatus;
use DateTimeImmutable;

final readonly class ApplicationRecord
{
    public function __construct(
        public int $id,
        public int $jobId,
        public string $jobTitle,
        public int $candidateId,
        public ApplicationStatus $status,
        public DateTimeImmutable $appliedAt,
        public int $createdByUserId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
