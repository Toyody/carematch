<?php

namespace App\Modules\Candidate\Application\Data;

final readonly class CandidateListCriteria
{
    public function __construct(
        public ?string $search,
        public ?string $occupation,
        public string $sort,
        public string $direction,
        public int $page,
        public int $perPage,
    ) {}
}
