<?php

namespace App\Modules\Recruitment\Application\Data;

final readonly class ApplicationSummaryPage
{
    /** @param list<ApplicationSummary> $items */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}
}
