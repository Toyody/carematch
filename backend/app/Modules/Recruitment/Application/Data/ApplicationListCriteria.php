<?php

namespace App\Modules\Recruitment\Application\Data;

use App\Modules\Recruitment\Domain\ApplicationStatus;

final readonly class ApplicationListCriteria
{
    public function __construct(
        public ?int $jobId,
        public ?int $candidateId,
        public ?ApplicationStatus $status,
        public string $sort,
        public string $direction,
        public int $page,
        public int $perPage,
    ) {}
}
