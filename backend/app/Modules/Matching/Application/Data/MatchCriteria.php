<?php

namespace App\Modules\Matching\Application\Data;

final readonly class MatchCriteria
{
    public function __construct(
        public int $page,
        public int $perPage,
        public ?float $maxDistanceKm,
    ) {}
}
