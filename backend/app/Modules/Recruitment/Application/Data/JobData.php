<?php

namespace App\Modules\Recruitment\Application\Data;

use DateTimeImmutable;

final readonly class JobData
{
    public function __construct(
        public string $title,
        public ?string $occupation,
        public ?string $location,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $employmentType,
        public ?string $description,
        public ?DateTimeImmutable $openedAt,
        public ?DateTimeImmutable $closesAt,
    ) {}
}
