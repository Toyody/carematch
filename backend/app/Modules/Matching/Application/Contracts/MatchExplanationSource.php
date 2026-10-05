<?php

namespace App\Modules\Matching\Application\Contracts;

use App\Modules\Matching\Application\Data\MatchExplanationSourceData;
use DateTimeImmutable;

interface MatchExplanationSource
{
    public function find(int $organisationId, int $jobId, int $candidateId, DateTimeImmutable $today, int $warningDays): ?MatchExplanationSourceData;
}
