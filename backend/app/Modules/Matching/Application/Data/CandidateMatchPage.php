<?php

namespace App\Modules\Matching\Application\Data;

final readonly class CandidateMatchPage
{
    /** @param list<CandidateMatch> $items */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}
}
