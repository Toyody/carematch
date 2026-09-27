<?php

namespace App\Modules\Dashboard\Application\Data;

final readonly class DashboardSnapshot
{
    /**
     * @param  array{applied: int, screening: int, interview: int, offer: int, hired: int, rejected: int}  $applicationCounts
     * @param  list<DashboardActivity>  $recentApplicationActivity
     */
    public function __construct(
        public int $candidateCount,
        public int $openJobCount,
        public array $applicationCounts,
        public array $recentApplicationActivity,
    ) {}
}
