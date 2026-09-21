<?php

namespace App\Modules\Recruitment\Application\Data;

use App\Modules\Recruitment\Domain\JobStatus;

final readonly class JobListCriteria
{
    public function __construct(
        public ?string $search,
        public ?JobStatus $status,
        public ?string $occupation,
        public ?string $employmentType,
        public string $sort,
        public string $direction,
        public int $page,
        public int $perPage,
    ) {}
}
