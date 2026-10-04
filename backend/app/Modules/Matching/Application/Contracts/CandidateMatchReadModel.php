<?php

namespace App\Modules\Matching\Application\Contracts;

use App\Modules\Matching\Application\Data\CandidateMatchPage;
use App\Modules\Matching\Application\Data\MatchCriteria;
use DateTimeImmutable;

interface CandidateMatchReadModel
{
    public function forJob(
        int $organisationId,
        int $jobId,
        MatchCriteria $criteria,
        DateTimeImmutable $today,
        int $qualificationWarningDays,
    ): ?CandidateMatchPage;
}
