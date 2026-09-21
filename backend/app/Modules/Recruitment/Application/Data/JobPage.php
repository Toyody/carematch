<?php

namespace App\Modules\Recruitment\Application\Data;

final readonly class JobPage
{
    /** @param list<JobRecord> $items */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}
}
