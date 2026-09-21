<?php

namespace App\Modules\Recruitment\Application\Data;

use App\Modules\Recruitment\Domain\JobStatus;
use DateTimeImmutable;

final readonly class JobRecord
{
    public function __construct(
        public int $id,
        public int $organisationId,
        public string $title,
        public ?string $occupation,
        public ?string $location,
        public ?string $employmentType,
        public ?string $description,
        public JobStatus $status,
        public ?DateTimeImmutable $openedAt,
        public ?DateTimeImmutable $closesAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
