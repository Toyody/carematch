<?php

namespace App\Modules\Candidate\Application\Data;

final readonly class CandidatePage
{
    /**
     * @param  list<CandidateRecord>  $items
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}
}
